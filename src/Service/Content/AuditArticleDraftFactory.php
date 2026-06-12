<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\Article;
use App\Entity\Audit;
use App\Entity\Keyword;
use App\Entity\Project;
use App\Enum\ArticleStatus;
use App\Exception\CmsIntegrationException;
use App\Repository\KeywordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class AuditArticleDraftFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KeywordRepository $keywordRepository,
        private readonly ArticleHtmlSanitizer $htmlSanitizer,
    ) {}

    public function create(Audit $audit): Article
    {
        $project = $audit->getProject();
        if (null === $project) {
            throw new CmsIntegrationException('The audit is not attached to a project.');
        }

        $metadata = $audit->getMetadata() ?? [];
        $analysis = is_array($metadata['ai_analysis'] ?? null) ? $metadata['ai_analysis'] : [];
        if ('completed' !== ($analysis['status'] ?? null)) {
            throw new CmsIntegrationException('A completed real AI analysis is required before creating an article draft.');
        }

        $title = $this->firstString([
            $analysis['suggested_title'] ?? null,
            $this->firstRecommendationAfterExample($analysis),
            $this->firstContentOpportunity($analysis),
        ]);
        if (null === $title) {
            throw new CmsIntegrationException('Claude did not return a usable article title or content opportunity.');
        }

        $article = new Article();
        $article
            ->setProject($project)
            ->setTitle(substr($title, 0, 500))
            ->setSeoTitle(substr($title, 0, 70))
            ->setSeoDescription($this->limitNullable($analysis['suggested_meta_description'] ?? null, 320))
            ->setSlug((new AsciiSlugger())->slug($title)->lower()->toString())
            ->setContentHtml($this->htmlSanitizer->sanitize($this->starterHtml($analysis)))
            ->setStatus(ArticleStatus::GENERATED)
            ->setGeneratedByProvider('anthropic')
            ->setGeneratedAt(new \DateTimeImmutable())
            ->setGenerationMetadata([
                'source' => 'audit_ai_analysis',
                'audit_id' => $audit->getId(),
                'image_suggestions' => [],
            ]);

        foreach ($this->detectedKeywords($analysis) as $index => $term) {
            $keyword = $this->findOrCreateKeyword($project, $term);
            $article->addTargetKeyword($keyword);
            if (0 === $index) {
                $article->setPrimaryKeyword($keyword);
            }
        }

        $article->setWordCount(str_word_count(strip_tags((string) $article->getContentHtml())));
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }

    /** @param array<string, mixed> $analysis */
    private function starterHtml(array $analysis): string
    {
        $html = '';
        if (is_scalar($analysis['summary'] ?? null)) {
            $html .= '<p>' . htmlspecialchars((string) $analysis['summary'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $faqs = is_array($analysis['faq_suggestions'] ?? null) ? $analysis['faq_suggestions'] : [];
        if ([] !== $faqs) {
            $html .= '<h2>Frequently asked questions</h2>';
            foreach (array_slice($faqs, 0, 6) as $faq) {
                if (!is_array($faq) || !is_scalar($faq['question'] ?? null) || !is_scalar($faq['answer'] ?? null)) {
                    continue;
                }

                $html .= '<h3>' . htmlspecialchars((string) $faq['question'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>';
                $html .= '<p>' . htmlspecialchars((string) $faq['answer'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $analysis
     *
     * @return list<string>
     */
    private function detectedKeywords(array $analysis): array
    {
        $keywordAnalysis = is_array($analysis['keyword_analysis'] ?? null) ? $analysis['keyword_analysis'] : [];
        $detected = is_array($keywordAnalysis['detected_target_keywords'] ?? null)
            ? $keywordAnalysis['detected_target_keywords']
            : [];
        $terms = [];

        foreach ($detected as $item) {
            if (!is_array($item) || !is_scalar($item['keyword'] ?? null)) {
                continue;
            }

            $term = trim((string) $item['keyword']);
            if ('' !== $term) {
                $terms[] = substr($term, 0, 500);
            }
        }

        return array_values(array_unique(array_slice($terms, 0, 12)));
    }

    private function findOrCreateKeyword(Project $project, string $term): Keyword
    {
        foreach ($this->keywordRepository->searchForProject($project, $term) as $keyword) {
            if (0 === strcasecmp($keyword->getTerm(), $term)) {
                return $keyword;
            }
        }

        $keyword = new Keyword();
        $keyword
            ->setProject($project)
            ->setTerm($term)
            ->setSource('audit_ai');
        $this->entityManager->persist($keyword);

        return $keyword;
    }

    /** @param array<string, mixed> $analysis */
    private function firstRecommendationAfterExample(array $analysis): mixed
    {
        $recommendations = is_array($analysis['recommendations'] ?? null) ? $analysis['recommendations'] : [];
        foreach ($recommendations as $recommendation) {
            if (is_array($recommendation) && is_scalar($recommendation['after_example'] ?? null)) {
                return $recommendation['after_example'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $analysis */
    private function firstContentOpportunity(array $analysis): mixed
    {
        $strategy = is_array($analysis['content_strategy'] ?? null) ? $analysis['content_strategy'] : [];
        $opportunities = is_array($strategy['content_opportunities'] ?? null) ? $strategy['content_opportunities'] : [];
        foreach ($opportunities as $opportunity) {
            if (is_array($opportunity) && is_scalar($opportunity['opportunity'] ?? null)) {
                return $opportunity['opportunity'];
            }

            if (is_scalar($opportunity)) {
                return $opportunity;
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function limitNullable(mixed $value, int $limit): ?string
    {
        return is_scalar($value) && '' !== trim((string) $value)
            ? substr(trim((string) $value), 0, $limit)
            : null;
    }
}
