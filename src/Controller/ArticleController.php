<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Audit;
use App\Entity\CmsConnection;
use App\Entity\Project;
use App\Enum\ArticleStatus;
use App\Exception\CmsIntegrationException;
use App\Form\ArticleGenerationType;
use App\Form\ArticleType;
use App\Form\CmsPublishType;
use App\Repository\ArticleRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Cms\CmsPublishingService;
use App\Service\Content\ArticleHtmlSanitizer;
use App\Service\Content\AuditArticleDraftFactory;
use App\Service\Content\ClaudeArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    #[Route('/projects/{project}/articles', name: 'app_article_index', methods: ['GET'])]
    public function index(Project $project, ArticleRepository $articleRepository): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('article/index.html.twig', [
            'project' => $project,
            'articles' => $articleRepository->findBy(['project' => $project], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/projects/{project}/articles/new', name: 'app_article_new', methods: ['GET', 'POST'])]
    public function new(
        Project $project,
        Request $request,
        ArticleHtmlSanitizer $htmlSanitizer,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        $article = new Article();
        $article->setProject($project);
        $form = $this->createForm(ArticleType::class, $article, ['project' => $project]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prepareArticle($article, $form, $htmlSanitizer);
            $entityManager->persist($article);
            $entityManager->flush();
            $this->addFlash('success', 'Article draft created.');

            return $this->redirectToRoute('app_article_show', [
                'project' => $project->getId(),
                'article' => $article->getId(),
            ]);
        }

        return $this->render('article/form.html.twig', [
            'project' => $project,
            'article' => $article,
            'form' => $form,
            'pageTitle' => 'Create article draft',
            'isEdit' => false,
        ]);
    }

    #[Route('/projects/{project}/articles/{article}', name: 'app_article_show', methods: ['GET'])]
    public function show(Project $project, Article $article): Response
    {
        $this->assertArticleProject($project, $article);
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $generateForm = $this->createForm(ArticleGenerationType::class, null, [
            'action' => $this->generateUrl('app_article_generate', [
                'project' => $project->getId(),
                'article' => $article->getId(),
            ]),
            'method' => 'POST',
        ]);
        $publishForm = $this->createForm(CmsPublishType::class, null, [
            'project' => $project,
            'action' => $this->generateUrl('app_article_publish', [
                'project' => $project->getId(),
                'article' => $article->getId(),
            ]),
            'method' => 'POST',
        ]);

        return $this->render('article/show.html.twig', [
            'project' => $project,
            'article' => $article,
            'generateForm' => $generateForm,
            'publishForm' => $publishForm,
        ]);
    }

    #[Route('/projects/{project}/articles/{article}/edit', name: 'app_article_edit', methods: ['GET', 'POST'])]
    public function edit(
        Project $project,
        Article $article,
        Request $request,
        ArticleHtmlSanitizer $htmlSanitizer,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertArticleProject($project, $article);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        $image = $article->getImages()->first();
        $form = $this->createForm(ArticleType::class, $article, [
            'project' => $project,
            'featured_image_url' => false === $image ? null : $image->getStorageUrl(),
            'featured_image_alt' => false === $image ? null : $image->getAltText(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prepareArticle($article, $form, $htmlSanitizer);
            $entityManager->flush();
            $this->addFlash('success', 'Article updated. Publish again to apply the changes to the CMS.');

            return $this->redirectToRoute('app_article_show', [
                'project' => $project->getId(),
                'article' => $article->getId(),
            ]);
        }

        return $this->render('article/form.html.twig', [
            'project' => $project,
            'article' => $article,
            'form' => $form,
            'pageTitle' => 'Edit article',
            'isEdit' => true,
        ]);
    }

    #[Route('/audits/{audit}/articles/create', name: 'app_article_create_from_audit', methods: ['POST'])]
    public function createFromAudit(
        Audit $audit,
        Request $request,
        AuditArticleDraftFactory $draftFactory,
    ): RedirectResponse {
        $project = $audit->getProject();
        if (null === $project) {
            throw $this->createNotFoundException('Audit project not found.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);
        if (!$this->isCsrfTokenValid('create_article_from_audit_' . $audit->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid article draft CSRF token.');
        }

        try {
            $article = $draftFactory->create($audit);
            $this->addFlash('success', 'Created a CMS-ready draft from the real audit analysis.');

            return $this->redirectToRoute('app_article_show', [
                'project' => $project->getId(),
                'article' => $article->getId(),
            ]);
        } catch (CmsIntegrationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_audit_show', ['id' => $audit->getId()]);
        }
    }

    #[Route('/projects/{project}/articles/{article}/generate', name: 'app_article_generate', methods: ['POST'])]
    public function generate(
        Project $project,
        Article $article,
        Request $request,
        ClaudeArticleWriterService $articleWriter,
    ): RedirectResponse {
        $this->assertArticleProject($project, $article);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        $form = $this->createForm(ArticleGenerationType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'The article writing brief is invalid.');

            return $this->articleRedirect($project, $article);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();

        try {
            $articleWriter->generate(
                $article,
                (string) ($data['brief'] ?? ''),
                (string) ($data['tone'] ?? 'expert_clear'),
                (int) ($data['targetWordCount'] ?? 1400),
                true === ($data['includeFaq'] ?? false),
            );
            $this->addFlash('success', 'Claude generated real CMS-ready article content. Review it before publishing.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Real AI article writing failed: ' . $exception->getMessage());
        }

        return $this->articleRedirect($project, $article);
    }

    #[Route('/projects/{project}/articles/{article}/publish', name: 'app_article_publish', methods: ['POST'])]
    public function publish(
        Project $project,
        Article $article,
        Request $request,
        CmsPublishingService $publishingService,
    ): RedirectResponse {
        $this->assertArticleProject($project, $article);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_CONTENT, $project);

        $form = $this->createForm(CmsPublishType::class, null, ['project' => $project]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Select a tested CMS connection and publication mode.');

            return $this->articleRedirect($project, $article);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        $connection = $data['connection'] ?? null;
        if (!$connection instanceof CmsConnection) {
            $this->addFlash('error', 'The selected CMS connection is invalid.');

            return $this->articleRedirect($project, $article);
        }

        try {
            $result = $publishingService->publish($article, $connection, 'publish' === ($data['mode'] ?? null));
            $this->addFlash('success', null === $result->externalUrl
                ? 'The article was sent to the real CMS.'
                : 'The article was sent to the real CMS: ' . $result->externalUrl);
            foreach ($result->warnings as $warning) {
                $this->addFlash('info', $warning);
            }
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Real CMS publication failed: ' . $exception->getMessage());
        }

        return $this->articleRedirect($project, $article);
    }

    private function prepareArticle(
        Article $article,
        \Symfony\Component\Form\FormInterface $form,
        ArticleHtmlSanitizer $htmlSanitizer,
    ): void {
        $article->setContentHtml($htmlSanitizer->sanitize((string) $article->getContentHtml()));
        $article->setWordCount(str_word_count(strip_tags((string) $article->getContentHtml())));
        if (ArticleStatus::DRAFT === $article->getStatus() && '' !== trim((string) $article->getContentHtml())) {
            $article->setStatus(ArticleStatus::GENERATED);
        }

        $image = $article->getImages()->first();
        if (true === $form->get('removeFeaturedImage')->getData() && false !== $image) {
            $article->removeImage($image);
            $image = false;
        }

        $imageUrl = trim((string) $form->get('featuredImageUrl')->getData());
        if ('' === $imageUrl) {
            return;
        }

        if (false === $image) {
            $image = new ArticleImage();
            $article->addImage($image);
        }

        $image
            ->setStorageUrl($imageUrl)
            ->setAltText(trim((string) $form->get('featuredImageAlt')->getData()))
            ->setProvider('external_url');
    }

    private function assertArticleProject(Project $project, Article $article): void
    {
        if ($article->getProject() !== $project) {
            throw $this->createNotFoundException('Article not found for this project.');
        }
    }

    private function articleRedirect(Project $project, Article $article): RedirectResponse
    {
        return $this->redirectToRoute('app_article_show', [
            'project' => $project->getId(),
            'article' => $article->getId(),
        ]);
    }
}
