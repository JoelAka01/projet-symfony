<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\KeywordSuggestion;
use App\Entity\Project;
use App\Entity\TopicResearch;
use App\Entity\User;
use App\Form\TopicResearchType;
use App\Repository\KeywordSuggestionRepository;
use App\Repository\TopicResearchRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Cost\CostAwarePipelineService;
use App\Service\KeywordDiscovery\AuditKeywordDiscoveryService;
use App\Service\Pipeline\ArticleGenerationPipelineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{project}/topic-research')]
final class TopicResearchController extends AbstractController
{
    #[Route('', name: 'app_topic_research_index', methods: ['GET'])]
    public function index(Project $project, TopicResearchRepository $topicResearchRepository): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('topic_research/index.html.twig', [
            'project' => $project,
            'topicResearches' => $topicResearchRepository->findForProject($project),
        ]);
    }

    #[Route('/new', name: 'app_topic_research_new', methods: ['GET', 'POST'])]
    public function new(
        Project $project,
        Request $request,
        CostAwarePipelineService $pipelineService,
        AuditKeywordDiscoveryService $auditKeywordDiscoveryService,
        KeywordSuggestionRepository $keywordSuggestionRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        $topicResearch = new TopicResearch();
        $topicResearch
            ->setProject($project)
            ->setCountry($project->getTargetCountry() ?? 'FR')
            ->setLanguage($project->getDefaultLanguage() ?? 'fr');

        $form = $this->createForm(TopicResearchType::class, $topicResearch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $started = $pipelineService->start($project, $user, $topicResearch->getPrimaryKeyword(), [
                'country' => $topicResearch->getCountry(),
                'language' => $topicResearch->getLanguage(),
                'sector' => $topicResearch->getSector(),
                'audience' => $topicResearch->getAudience(),
                'businessObjective' => $topicResearch->getBusinessObjective(),
                'qualityMode' => $topicResearch->getQualityMode(),
            ]);

            $this->addFlash('success', 'Pipeline V2 started.');

            return $this->redirectToRoute('app_topic_research_show', [
                'project' => $project->getId(),
                'topicResearch' => $started->getId(),
            ]);
        }

        $keywordDiscoverySummary = $auditKeywordDiscoveryService->discover($project, false);

        return $this->render('topic_research/new.html.twig', [
            'project' => $project,
            'form' => $form,
            'keywordSuggestions' => $keywordSuggestionRepository->findForProject($project, 25),
            'keywordDiscoverySummary' => $keywordDiscoverySummary,
        ]);
    }

    #[Route('/keyword-discovery', name: 'app_topic_research_discover_keywords', methods: ['POST'])]
    public function discoverKeywords(
        Project $project,
        Request $request,
        AuditKeywordDiscoveryService $auditKeywordDiscoveryService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        if (!$this->isCsrfTokenValid('discover_keywords_' . $project->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid keyword discovery CSRF token.');
        }

        $summary = $auditKeywordDiscoveryService->discover($project, true);
        if (!$summary['audit_used']) {
            $this->addFlash('warning', 'No completed audit available for keyword discovery.');
        } else {
            $message = sprintf(
                'Keyword opportunities refreshed: %d added, %d updated, %d skipped.',
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
            );
            if ($summary['fallback_used']) {
                $message .= ' Economic fallback was used.';
            }

            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('app_topic_research_new', [
            'project' => $project->getId(),
        ]);
    }

    #[Route('/suggestions/{keywordSuggestion}/generate', name: 'app_topic_research_generate_from_suggestion', methods: ['POST'])]
    public function generateFromSuggestion(
        Project $project,
        KeywordSuggestion $keywordSuggestion,
        Request $request,
        CostAwarePipelineService $pipelineService,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        if ($keywordSuggestion->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Keyword suggestion not found for this project.');
        }

        if (!$this->isCsrfTokenValid('generate_keyword_suggestion_' . $keywordSuggestion->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid keyword suggestion CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $keywordSuggestion->setIsSelected(true);
        $entityManager->flush();

        $started = $pipelineService->start($project, $user, $keywordSuggestion->getTerm(), [
            'country' => $project->getTargetCountry() ?? 'FR',
            'language' => $project->getDefaultLanguage() ?? 'fr',
        ]);

        $this->addFlash('success', 'Pipeline V2 started from audit opportunity.');

        return $this->redirectToRoute('app_topic_research_show', [
            'project' => $project->getId(),
            'topicResearch' => $started->getId(),
        ]);
    }

    #[Route('/{topicResearch}', name: 'app_topic_research_show', methods: ['GET'])]
    public function show(Project $project, TopicResearch $topicResearch): Response
    {
        $this->assertTopicResearchProject($project, $topicResearch);
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('topic_research/show.html.twig', [
            'project' => $project,
            'topicResearch' => $topicResearch,
            'steps' => $this->steps(),
            'isRunning' => $topicResearch->getStatus()->isRunning(),
        ]);
    }

    #[Route('/{topicResearch}/retry', name: 'app_topic_research_retry', methods: ['POST'])]
    public function retry(
        Project $project,
        TopicResearch $topicResearch,
        Request $request,
        ArticleGenerationPipelineService $pipelineService,
    ): RedirectResponse {
        $this->assertTopicResearchProject($project, $topicResearch);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        if (!$this->isCsrfTokenValid('retry_topic_research_' . $topicResearch->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid retry CSRF token.');
        }

        $step = $request->request->getString('step') ?: null;

        try {
            $pipelineService->retryStep($topicResearch, $step);
            $this->addFlash('success', 'Pipeline step relaunched.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_topic_research_show', [
            'project' => $project->getId(),
            'topicResearch' => $topicResearch->getId(),
        ]);
    }

    private function assertTopicResearchProject(Project $project, TopicResearch $topicResearch): void
    {
        if ($topicResearch->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Topic research not found for this project.');
        }
    }

    /** @return list<array{key: string, label: string}> */
    private function steps(): array
    {
        return [
            ['key' => TopicResearch::STEP_SERP_ANALYSIS, 'label' => 'SERP + Questions'],
            ['key' => TopicResearch::STEP_INTELLIGENCE, 'label' => 'Intent + Semantic'],
            ['key' => TopicResearch::STEP_BRIEF_OUTLINE, 'label' => 'Brief + Outline'],
            ['key' => TopicResearch::STEP_ARTICLE, 'label' => 'Article'],
            ['key' => TopicResearch::STEP_INTERNAL_LINKING, 'label' => 'Internal Links'],
            ['key' => TopicResearch::STEP_SEO_SCORE, 'label' => 'SEO Score'],
        ];
    }
}
