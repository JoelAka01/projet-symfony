<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\SitePageType;
use App\Repository\SitePageRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\InternalLinking\SitePageDiscoveryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{project}/site-pages')]
final class SitePageController extends AbstractController
{
    #[Route('', name: 'app_site_page_create', methods: ['POST'])]
    public function create(
        Project $project,
        Request $request,
        SitePageRepository $sitePageRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        if (!$this->isCsrfTokenValid('create_site_page_' . $project->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid site page CSRF token.');
        }

        $url = trim($request->request->getString('url'));
        $title = trim($request->request->getString('title'));
        $pageType = SitePageType::tryFrom($request->request->getString('pageType')) ?? SitePageType::OTHER;
        if ('' === $url || '' === $title) {
            $this->addFlash('error', 'URL and title are required.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $sitePage = $sitePageRepository->findOneForProjectUrl($project, $url) ?? new SitePage();
        $sitePage
            ->setProject($project)
            ->setUrl($url)
            ->setTitle($title)
            ->setPageType($pageType)
            ->setTargetKeyword($request->request->getString('targetKeyword') ?: null)
            ->setBusinessPriority($request->request->getInt('businessPriority', 50))
            ->setAnchorSuggestions($this->anchorsFromRequest($request))
            ->setIsActive(true);

        $entityManager->persist($sitePage);
        $entityManager->flush();
        $this->addFlash('success', 'Internal page saved.');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/discover', name: 'app_site_page_discover', methods: ['POST'])]
    public function discover(
        Project $project,
        Request $request,
        SitePageDiscoveryService $discoveryService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        if (!$this->isCsrfTokenValid('discover_site_pages_' . $project->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid discovery CSRF token.');
        }

        $result = $discoveryService->discover($project);
        $this->addFlash('success', sprintf('Internal pages discovered: %d created, %d updated.', $result['created'], $result['updated']));

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{sitePage}/toggle', name: 'app_site_page_toggle', methods: ['POST'])]
    public function toggle(
        Project $project,
        SitePage $sitePage,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->assertSitePageProject($project, $sitePage);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        if (!$this->isCsrfTokenValid('toggle_site_page_' . $sitePage->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid site page toggle CSRF token.');
        }

        $sitePage->setIsActive(!$sitePage->isActive());
        $entityManager->flush();
        $this->addFlash('success', $sitePage->isActive() ? 'Internal page activated.' : 'Internal page disabled.');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    /** @return list<string> */
    private function anchorsFromRequest(Request $request): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $request->request->getString('anchorSuggestions'));
        $parts = preg_split('/[\n,]+/', $raw) ?: [];
        $anchors = [];
        foreach ($parts as $part) {
            $anchor = trim($part);
            if ('' !== $anchor) {
                $anchors[] = $anchor;
            }
        }

        return array_values(array_unique($anchors));
    }

    private function assertSitePageProject(Project $project, SitePage $sitePage): void
    {
        if ($sitePage->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Internal page not found for this project.');
        }
    }
}
