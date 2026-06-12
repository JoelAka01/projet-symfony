<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Audit;
use App\Entity\AuditIssue;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditStatus;
use App\Exception\InvalidWebsiteUrlException;
use App\Form\ProjectType;
use App\Message\RunClaudeAnalysisMessage;
use App\Message\RunWebsiteAuditMessage;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Audit\AuditInsightsBuilder;
use App\Service\Audit\AuditProgressStatusBuilder;
use App\Service\Audit\WebsiteAuditRunner;
use App\Service\Project\ProjectManager;
use App\Service\Report\AuditPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectController extends AbstractController
{
    private const ISSUE_SEVERITIES = ['critical', 'high', 'medium', 'low', 'info'];

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
        WebsiteAuditRunner $auditRunner,
        MessageBusInterface $messageBus,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $project = new Project();
        $project->setOwner($user);
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $projectManager->createForUser($project, $user, (string) $form->get('websiteUrl')->getData());

                $domain = $projectManager->getPrimaryDomain($project);
                if (!$domain instanceof Domain) {
                    $this->addFlash('error', 'Project created, but no crawlable domain was attached.');

                    return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
                }

                $audit = $auditRunner->createQueued($project, $domain);
                $messageBus->dispatch(new RunWebsiteAuditMessage($audit->getId()));
                $this->addFlash('info', 'Project created. The crawler and Claude SEO analysis are running in the background.');

                return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
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

    #[Route('/projects/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    public function delete(
        Project $project,
        Request $request,
        ProjectManager $projectManager,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);

        if (!$this->isCsrfTokenValid('delete_project_' . $project->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid project delete CSRF token.');
        }

        $projectManager->delete($project);
        $this->addFlash('success', 'Project deleted.');

        return $this->redirectToRoute('app_project_index');
    }

    #[Route('/projects/{id}/audits/launch', name: 'app_project_audit_launch', methods: ['POST'])]
    public function launchAudit(
        Project $project,
        Request $request,
        WebsiteAuditRunner $auditRunner,
        MessageBusInterface $messageBus,
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

        $audit = $auditRunner->createQueued($project, $domain);
        $messageBus->dispatch(new RunWebsiteAuditMessage($audit->getId()));
        $this->addFlash('info', 'Website crawl and Claude SEO analysis queued. Progress updates automatically, and you can leave this page while it runs.');

        return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
    }

    #[Route('/audits/{id}', name: 'app_audit_show', methods: ['GET'])]
    public function showAudit(Audit $audit, AuditInsightsBuilder $insightsBuilder): Response
    {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit project not found.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('audit/show.html.twig', [
            'audit' => $audit,
            'project' => $project,
            'issueGroupsBySeverity' => $this->groupIssuesBySeverityAndType($audit),
            'insights' => $insightsBuilder->build($audit),
        ]);
    }

    #[Route('/audits/{id}/status', name: 'app_audit_status', methods: ['GET'])]
    public function auditStatus(Audit $audit, AuditProgressStatusBuilder $statusBuilder): JsonResponse
    {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit project not found.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $response = $this->json($statusBuilder->build($audit));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/audits/{id}/pdf', name: 'app_audit_pdf', methods: ['GET'])]
    public function downloadAuditPdf(Audit $audit, AuditPdfGenerator $pdfGenerator): Response
    {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit project not found.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $domain = $audit->getDomain()?->getRootDomain() ?? 'website';
        $safeDomain = trim((string) preg_replace('/[^a-z0-9.-]+/i', '-', $domain), '-');
        $filename = sprintf('seo-audit-%s-%s.pdf', '' === $safeDomain ? 'website' : $safeDomain, $audit->getCreatedAt()->format('Ymd'));

        $response = new Response($pdfGenerator->generate($audit));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }

    #[Route('/audits/{id}/ai/retry', name: 'app_audit_ai_retry', methods: ['POST'])]
    public function retryAuditAi(
        Audit $audit,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): RedirectResponse {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit project not found.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::LAUNCH_AUDIT, $project);

        if (!$this->isCsrfTokenValid('retry_ai_' . $audit->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid Claude retry CSRF token.');
        }

        if (AuditStatus::COMPLETED !== $audit->getStatus()) {
            $this->addFlash('error', 'Claude analysis can only be retried after the crawl has completed.');

            return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
        }

        if ($audit->getPages()->isEmpty()) {
            $this->addFlash('error', 'Claude analysis cannot run because this audit has no crawled pages.');

            return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
        }

        $metadata = $audit->getMetadata() ?? [];
        $existingAi = is_array($metadata['ai_analysis'] ?? null) ? $metadata['ai_analysis'] : [];
        $metadata['ai_analysis'] = [
            'status' => 'queued',
            'provider' => is_scalar($existingAi['provider'] ?? null) ? (string) $existingAi['provider'] : 'anthropic',
            'model' => is_scalar($existingAi['model'] ?? null) ? (string) $existingAi['model'] : null,
            'recommendations' => [],
            'queued_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        $audit->setMetadata($metadata);
        $entityManager->flush();

        $messageBus->dispatch(new RunClaudeAnalysisMessage($audit->getId()));
        $this->addFlash('info', 'Claude analysis retry queued. Progress updates automatically, and you can leave this page while it runs.');

        return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
    }

    /**
     * @return array<string, list<array{type: string, label: string, issues: list<AuditIssue>}>>
     */
    private function groupIssuesBySeverityAndType(Audit $audit): array
    {
        /** @var array<string, array<string, array{type: string, label: string, issues: list<AuditIssue>}>> $indexedGroups */
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
