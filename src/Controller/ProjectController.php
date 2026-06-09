<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Project;
use App\Enum\AuditStatus;
use App\Security\Voter\ProjectVoter;
use App\Service\Crawler\WebsiteCrawlerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController extends AbstractController
{
    #[Route('/projects/{id}', name: 'app_project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $audits = $project->getAudits()->toArray();
        usort(
            $audits,
            static fn (Audit $left, Audit $right): int => $right->getCreatedAt() <=> $left->getCreatedAt(),
        );

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'audits' => $audits,
        ]);
    }

    #[Route('/projects/{id}/audits/launch', name: 'app_project_audit_launch', methods: ['POST'])]
    public function launchAudit(
        Project $project,
        Request $request,
        EntityManagerInterface $entityManager,
        WebsiteCrawlerService $crawler,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('LAUNCH_AUDIT', $project);

        if (!$this->isCsrfTokenValid('launch_audit_'.$project->getId(), (string) $request->request->get('_token', ''))) {
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

            $this->addFlash('error', 'The crawl failed: '.$exception->getMessage());
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
