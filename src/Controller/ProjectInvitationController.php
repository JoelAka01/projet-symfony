<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Audit;
use App\Entity\Project;
use App\Entity\ProjectInvitation;
use App\Entity\User;
use App\Form\ProjectInvitationType;
use App\Repository\AuditRepository;
use App\Repository\ProjectInvitationRepository;
use App\Repository\UserRepository;
use App\Security\AccountEmailService;
use App\Security\Voter\ProjectVoter;
use App\Service\Audit\AuditInsightsBuilder;
use App\Service\Project\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ProjectInvitationController extends AbstractController
{
    private const ISSUE_SEVERITIES = ['critical', 'high', 'medium', 'low', 'info'];

    #[Route('/projects/{id}/guests', name: 'app_project_guests_index', methods: ['GET'])]
    public function guests(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $form = $this->createForm(ProjectInvitationType::class);

        return $this->render('project/guests.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/projects/{id}/invitations/invite', name: 'app_project_invitation_invite', methods: ['POST'])]
    public function invite(
        Project $project,
        Request $request,
        AccountEmailService $emailService,
        EntityManagerInterface $entityManager,
        ProjectInvitationRepository $invitationRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $form = $this->createForm(ProjectInvitationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));

            // Check if there is already a pending invitation for this email on this project
            $existing = $invitationRepository->findOneBy([
                'project' => $project,
                'email' => $email,
                'status' => 'pending',
            ]);

            if (null !== $existing) {
                $this->addFlash('error', sprintf('An invitation is already pending for %s.', $email));
                return $this->redirectToRoute('app_project_guests_index', ['id' => $project->getId()]);
            }

            // Check if the user is already a guest of the project
            foreach ($project->getGuests() as $guest) {
                if ($guest->getEmail() === $email) {
                    $this->addFlash('error', sprintf('%s is already a guest of this project.', $email));
                    return $this->redirectToRoute('app_project_guests_index', ['id' => $project->getId()]);
                }
            }

            // Check if the user is the owner
            if ($project->getOwner()?->getEmail() === $email) {
                $this->addFlash('error', 'You cannot invite yourself or the project owner.');
                return $this->redirectToRoute('app_project_guests_index', ['id' => $project->getId()]);
            }



            $invitation = new ProjectInvitation();
            $invitation->setProject($project);
            $invitation->setEmail($email);

            $entityManager->persist($invitation);
            $entityManager->flush();

            try {
                $emailService->sendProjectInvitationEmail($invitation);
                $this->addFlash('success', sprintf('Invitation sent successfully to %s.', $email));
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Invitation created, but notification email could not be sent: ' . $exception->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid email address provided.');
        }

        return $this->redirectToRoute('app_project_guests_index', ['id' => $project->getId()]);
    }

    #[Route('/projects/invitations/view/{token}', name: 'app_project_invitation_view', methods: ['GET'])]
    public function view(
        string $token,
        ProjectInvitationRepository $invitationRepository,
        ProjectManager $projectManager,
    ): Response {
        $invitation = $invitationRepository->findOneBy(['token' => $token, 'status' => 'pending']);
        if (null === $invitation) {
            throw $this->createNotFoundException('This invitation does not exist or has already been accepted.');
        }

        $project = $invitation->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Project not found.');
        }

        $audits = $project->getAudits()->toArray();
        usort(
            $audits,
            static fn(Audit $left, Audit $right): int => $right->getCreatedAt() <=> $left->getCreatedAt(),
        );

        return $this->render('project/guest_view.html.twig', [
            'project' => $project,
            'primaryDomain' => $projectManager->getPrimaryDomain($project),
            'audits' => $audits,
            'invitation' => $invitation,
        ]);
    }

    #[Route('/projects/invitations/view/{token}/audits/{auditId}', name: 'app_project_invitation_audit_view', methods: ['GET'])]
    public function viewAudit(
        string $token,
        string $auditId,
        ProjectInvitationRepository $invitationRepository,
        AuditRepository $auditRepository,
        AuditInsightsBuilder $insightsBuilder,
    ): Response {
        $invitation = $invitationRepository->findOneBy(['token' => $token, 'status' => 'pending']);
        if (null === $invitation) {
            throw $this->createNotFoundException('This invitation does not exist or has already been accepted.');
        }

        $project = $invitation->getProject();
        $audit = $auditRepository->find($auditId);

        if (null === $audit || $audit->getProject() !== $project) {
            throw $this->createNotFoundException('Audit not found for this project.');
        }

        return $this->render('audit/guest_view.html.twig', [
            'project' => $project,
            'audit' => $audit,
            'invitation' => $invitation,
            'insights' => $insightsBuilder->build($audit),
            'issueGroupsBySeverity' => $this->groupIssuesBySeverityAndType($audit),
            'is_guest_view' => true,
        ]);
    }

    #[Route('/projects/invitations/accept/{token}', name: 'app_project_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        ProjectInvitationRepository $invitationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('You must be logged in to accept this invitation.');
        }

        $invitation = $invitationRepository->findOneBy(['token' => $token, 'status' => 'pending']);
        if (null === $invitation) {
            $this->addFlash('error', 'This invitation link is invalid or has already been used.');
            return $this->redirectToRoute('app_project_index');
        }

        $project = $invitation->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Project not found.');
        }

        // Check email matches
        if ($user->getEmail() !== $invitation->getEmail()) {
            $this->addFlash('error', sprintf(
                'This invitation is for %s, but you are logged in as %s. Please log in with the correct account.',
                $invitation->getEmail(),
                $user->getEmail()
            ));

            return $this->render('project/accept_invitation_error.html.twig', [
                'invitation' => $invitation,
                'user' => $user,
            ]);
        }

        if ($request = $this->container->get('request_stack')->getCurrentRequest()) {
            if ($request->isMethod('POST')) {
                $project->addGuest($user);
                $invitation->setStatus('accepted');
                $entityManager->flush();

                $this->addFlash('success', sprintf('You have successfully joined the project "%s" as a guest.', $project->getName()));

                return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
            }
        }

        return $this->render('project/accept_invitation.html.twig', [
            'project' => $project,
            'invitation' => $invitation,
        ]);
    }

    #[Route('/projects/{id}/invitations/{invitationId}/cancel', name: 'app_project_invitation_cancel', methods: ['POST'])]
    public function cancel(
        Project $project,
        string $invitationId,
        Request $request,
        ProjectInvitationRepository $invitationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if (!$this->isCsrfTokenValid('cancel_invitation_' . $invitationId, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $invitation = $invitationRepository->find($invitationId);
        if (null !== $invitation && $invitation->getProject() === $project) {
            $entityManager->remove($invitation);
            $entityManager->flush();
            $this->addFlash('success', 'Invitation has been cancelled.');
        }

        return $this->redirectToRoute('app_project_guests_index', ['id' => $project->getId()]);
    }

    #[Route('/projects/{id}/guests/{userId}/remove', name: 'app_project_guest_remove', methods: ['POST'])]
    public function removeGuest(
        Project $project,
        string $userId,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if (!$this->isCsrfTokenValid('remove_guest_' . $userId, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $guest = $userRepository->find($userId);
        if (null !== $guest) {
            $project->removeGuest($guest);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Guest %s has been removed from the project.', $guest->getDisplayName()));
        }

        return $this->redirectToRoute('app_project_guests_index', ['id' => $project->getId()]);
    }

    /**
     * @return array<string, list<array{type: string, label: string, issues: list<\App\Entity\AuditIssue>}>>
     */
    private function groupIssuesBySeverityAndType(Audit $audit): array
    {
        /** @var array<string, array<string, array{type: string, label: string, issues: list<\App\Entity\AuditIssue>}>> $indexedGroups */
        $indexedGroups = [];
        foreach (self::ISSUE_SEVERITIES as $severity) {
            $indexedGroups[$severity] = [];
        }

        foreach ($audit->getIssues() as $issue) {
            $severity = strtolower((string) $issue->getSeverity());
            if (!in_array($severity, self::ISSUE_SEVERITIES, true)) {
                $severity = 'medium';
            }

            $type = $issue->getIssueType();
            if (!isset($indexedGroups[$severity][$type])) {
                $indexedGroups[$severity][$type] = [
                    'type' => $type,
                    'label' => $this->humanizeIssueType($type),
                    'issues' => [],
                ];
            }

            $indexedGroups[$severity][$type]['issues'][] = $issue;
        }

        $groupsBySeverity = [];
        foreach ($indexedGroups as $severity => $groups) {
            $groupList = array_values($groups);
            usort($groupList, static function (array $left, array $right): int {
                $countComparison = count($right['issues']) <=> count($left['issues']);

                return 0 !== $countComparison ? $countComparison : strcmp($left['label'], $right['label']);
            });

            $groupsBySeverity[$severity] = $groupList;
        }

        return $groupsBySeverity;
    }

    private function humanizeIssueType(string $issueType): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $issueType));
    }
}
