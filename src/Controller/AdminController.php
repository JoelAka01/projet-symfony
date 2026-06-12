<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditStatus;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Exception\InvalidWebsiteUrlException;
use App\Form\AdminProjectType;
use App\Form\AdminUserType;
use App\Repository\AiUsageRepository;
use App\Repository\AuditRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\Project\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'app_admin_')]
final class AdminController extends AbstractController
{
    /** @var array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int} */
    private const EMPTY_USAGE = [
        'credits' => 0,
        'inputTokens' => 0,
        'outputTokens' => 0,
        'cachedInputTokens' => 0,
        'calls' => 0,
    ];

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $userRepository,
        ProjectRepository $projectRepository,
        AuditRepository $auditRepository,
        AiUsageRepository $aiUsageRepository,
        PaymentRepository $paymentRepository,
    ): Response {
        $usageByUser = $aiUsageRepository->getSummariesByUser();
        $userRows = $this->buildUserRows($userRepository->findForAdmin(), $usageByUser);
        usort(
            $userRows,
            static fn(array $left, array $right): int => $right['usage']['credits'] <=> $left['usage']['credits'],
        );

        return $this->render('admin/dashboard.html.twig', [
            'metrics' => [
                'users' => $userRepository->count([]),
                'verifiedUsers' => $userRepository->count(['isVerified' => true]),
                'projects' => $projectRepository->count([]),
                'activeProjects' => $projectRepository->count(['status' => ProjectStatus::ACTIVE]),
                'audits' => $auditRepository->count([]),
                'failedAudits' => $auditRepository->count(['status' => AuditStatus::FAILED]),
                'paidPayments' => $paymentRepository->countPaid(),
                'simulatedRevenueCents' => $paymentRepository->sumPaidAmountCents(),
            ],
            'usage' => $aiUsageRepository->getGlobalSummary(),
            'topUsers' => array_slice($userRows, 0, 5),
            'recentUsage' => $aiUsageRepository->findRecent(10),
        ]);
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(
        Request $request,
        UserRepository $userRepository,
        AiUsageRepository $aiUsageRepository,
    ): Response {
        $search = trim($request->query->getString('q'));
        $roleValue = strtoupper(trim($request->query->getString('role')));
        $role = '' === $roleValue ? null : UserRole::tryFrom($roleValue);

        return $this->render('admin/users/index.html.twig', [
            'rows' => $this->buildUserRows(
                $userRepository->findForAdmin($search, $role),
                $aiUsageRepository->getSummariesByUser(),
            ),
            'search' => $search,
            'selectedRole' => null === $role ? '' : $role->value,
            'roles' => UserRole::cases(),
        ]);
    }

    #[Route('/users/new', name: 'user_new', methods: ['GET', 'POST'])]
    public function newUser(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, ['password_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('plainPassword')->getData();
            $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
            'managedUser' => $user,
            'pageTitle' => 'Create user',
            'buttonLabel' => 'Create user',
            'recentUsage' => [],
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/users/{id}/edit', name: 'user_edit', methods: ['GET', 'POST'])]
    public function editUser(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AiUsageRepository $aiUsageRepository,
    ): Response {
        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->getUser() === $user && UserRole::ADMIN !== $user->getRole()) {
                $form->get('role')->addError(new FormError('You cannot remove your own administrator role.'));
            } else {
                $password = trim((string) $form->get('plainPassword')->getData());
                if ('' !== $password) {
                    $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                }

                $user->touch();
                $entityManager->flush();
                $this->addFlash('success', 'User updated.');

                return $this->redirectToRoute('app_admin_users');
            }
        }

        $usageByUser = $aiUsageRepository->getSummariesByUser();

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
            'managedUser' => $user,
            'pageTitle' => 'Edit user',
            'buttonLabel' => 'Save user',
            'usage' => $usageByUser[$user->getId()] ?? self::EMPTY_USAGE,
            'recentUsage' => $aiUsageRepository->findRecentForUser($user),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function deleteUser(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_delete_user_' . $user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid user delete CSRF token.');
        }

        if ($this->getUser() === $user) {
            $this->addFlash('error', 'You cannot delete your own administrator account.');

            return $this->redirectToRoute('app_admin_users');
        }

        $entityManager->remove($user);
        $entityManager->flush();
        $this->addFlash('success', 'User deleted. Historical AI usage remains anonymized.');

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(
        Request $request,
        ProjectRepository $projectRepository,
        AiUsageRepository $aiUsageRepository,
    ): Response {
        $search = trim($request->query->getString('q'));
        $statusValue = strtoupper(trim($request->query->getString('status')));
        $status = '' === $statusValue ? null : ProjectStatus::tryFrom($statusValue);

        return $this->render('admin/projects/index.html.twig', [
            'projects' => $projectRepository->findForAdmin($search, $status),
            'usageByProject' => $aiUsageRepository->getSummariesByProject(),
            'emptyUsage' => self::EMPTY_USAGE,
            'search' => $search,
            'selectedStatus' => null === $status ? '' : $status->value,
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    #[Route('/projects/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function newProject(
        Request $request,
        ProjectManager $projectManager,
    ): Response {
        $project = new Project();
        $form = $this->createForm(AdminProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $projectManager->createManaged($project, (string) $form->get('websiteUrl')->getData());
                $this->addFlash('success', 'Project created.');

                return $this->redirectToRoute('app_admin_projects');
            } catch (InvalidWebsiteUrlException|\LogicException $exception) {
                $form->get('websiteUrl')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->renderProjectForm($form, $project, 'Create project', 'Create project');
    }

    #[Route('/projects/{id}/edit', name: 'project_edit', methods: ['GET', 'POST'])]
    public function editProject(
        Project $project,
        Request $request,
        ProjectManager $projectManager,
    ): Response {
        $form = $this->createForm(AdminProjectType::class, $project, [
            'website_url' => $projectManager->getPrimaryWebsiteUrl($project),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $projectManager->update($project, (string) $form->get('websiteUrl')->getData());
                $this->addFlash('success', 'Project updated.');

                return $this->redirectToRoute('app_admin_projects');
            } catch (InvalidWebsiteUrlException $exception) {
                $form->get('websiteUrl')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->renderProjectForm($form, $project, 'Edit project', 'Save project');
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', methods: ['POST'])]
    public function deleteProject(
        Project $project,
        Request $request,
        ProjectManager $projectManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_delete_project_' . $project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid project delete CSRF token.');
        }

        $projectManager->delete($project);
        $this->addFlash('success', 'Project and its related crawl data were deleted.');

        return $this->redirectToRoute('app_admin_projects');
    }

    /**
     * @param list<User>                                                                                                  $users
     * @param array<string, array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int}> $usageByUser
     *
     * @return list<array{user: User, usage: array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int}}>
     */
    private function buildUserRows(array $users, array $usageByUser): array
    {
        return array_map(
            static fn(User $user): array => [
                'user' => $user,
                'usage' => $usageByUser[$user->getId()] ?? self::EMPTY_USAGE,
            ],
            $users,
        );
    }

    private function renderProjectForm(
        \Symfony\Component\Form\FormInterface $form,
        Project $project,
        string $pageTitle,
        string $buttonLabel,
    ): Response {
        return $this->render('admin/projects/form.html.twig', [
            'form' => $form,
            'project' => $project,
            'pageTitle' => $pageTitle,
            'buttonLabel' => $buttonLabel,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
