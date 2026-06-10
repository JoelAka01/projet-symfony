<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditStatus;
use App\Enum\ProjectStatus;
use App\Exception\InvalidWebsiteUrlException;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Crawler\WebsiteCrawlerService;
use App\Service\Project\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectController extends AbstractController
{
    #[Route('/projects', name: 'app_project_index', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        AuthorizationCheckerInterface $authorizationChecker,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $projects = array_values(array_filter(
            $projectRepository->findDashboardProjectsForUser($user),
            fn(Project $project): bool => $authorizationChecker->isGranted(ProjectVoter::VIEW, $project),
        ));

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/projects/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ProjectManager $projectManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $projectManager->createForUser($project, $user, (string) $form->get('websiteUrl')->getData());
                $this->addFlash('success', 'Project created. You can launch a real crawl from the project page.');

                return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
            } catch (InvalidWebsiteUrlException $exception) {
                $form->get('websiteUrl')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/projects/{id}', name: 'app_project_show', methods: ['GET'])]
    public function show(Project $project, ProjectManager $projectManager): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $audits = $project->getAudits()->toArray();
        usort(
            $audits,
            static fn(Audit $left, Audit $right): int => $right->getCreatedAt() <=> $left->getCreatedAt(),
        );

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'primaryDomain' => $projectManager->getPrimaryDomain($project),
            'audits' => $audits,
        ]);
    }

    #[Route('/projects/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(
        Project $project,
        Request $request,
        ProjectManager $projectManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $form = $this->createForm(ProjectType::class, $project, [
            'include_status' => true,
            'website_url' => $projectManager->getPrimaryWebsiteUrl($project),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $projectManager->update($project, (string) $form->get('websiteUrl')->getData());
                $this->addFlash('success', 'Project updated.');

                return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
            } catch (InvalidWebsiteUrlException $exception) {
                $form->get('websiteUrl')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/projects/{id}/archive', name: 'app_project_archive', methods: ['POST'])]
    public function archive(
        Project $project,
        Request $request,
        ProjectManager $projectManager,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);

        if (!$this->isCsrfTokenValid('archive_project_' . $project->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid project archive CSRF token.');
        }

        if (ProjectStatus::ARCHIVED !== $project->getStatus()) {
            $projectManager->archive($project);
            $this->addFlash('success', 'Project archived.');
        }

        return $this->redirectToRoute('app_project_index');
    }

    #[Route('/projects/{id}/audits/launch', name: 'app_project_audit_launch', methods: ['POST'])]
    public function launchAudit(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        WebsiteCrawlerService $crawler,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('LAUNCH_AUDIT', $project);

        if (!$this->isCsrfTokenValid('launch_audit_' . $project->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid audit launch CSRF token.');
        }

        $domain = $project->getDomains()->first();
        if (!$domain instanceof Domain) {
            $this->addFlash('error', 'Add a domain to this project before launching a crawl.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setStatus(AuditStatus::RUNNING)
            ->setCrawlStartedAt(new \DateTimeImmutable())
            ->setMaxPages($crawler->getConfiguredMaxPages())
            ->setMaxDepth($crawler->getConfiguredMaxDepth());

        $entityManager->persist($audit);
        $entityManager->flush();

        try {
            // async crawl message here instead of calling the service directly.
            $crawler->crawl($audit);
            $this->addFlash('success', 'Website crawl completed.');
        } catch (\Throwable $exception) {
            $audit
                ->setStatus(AuditStatus::FAILED)
                ->setErrorMessage($exception->getMessage())
                ->setCrawlFinishedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('error', 'The crawl failed: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
    }

    #[Route('/audits/{id}', name: 'app_audit_show', methods: ['GET'])]
    public function showAudit(Audit $audit): Response
    {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit project not found.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $issuesBySeverity = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
            'info' => [],
        ];

        foreach ($audit->getIssues() as $issue) {
            $severity = strtolower((string) $issue->getSeverity());
            $issuesBySeverity[$severity] ??= [];
            $issuesBySeverity[$severity][] = $issue;
        }

        return $this->render('audit/show.html.twig', [
            'audit' => $audit,
            'project' => $project,
            'issuesBySeverity' => $issuesBySeverity,
        ]);
    }
}
