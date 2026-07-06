<?php

declare(strict_types=1);

namespace App\Tests\Unit\KeywordDiscovery;

use App\Entity\Audit;
use App\Entity\Keyword;
use App\Entity\KeywordSuggestion;
use App\Entity\Project;
use App\Enum\AuditStatus;
use App\Enum\KeywordSuggestionSource;
use App\Repository\ApiUsageLogRepository;
use App\Repository\ArticleRepository;
use App\Repository\AuditRepository;
use App\Repository\KeywordRepository;
use App\Repository\KeywordSuggestionRepository;
use App\Repository\ProjectApiBudgetRepository;
use App\Service\Cost\ApiCostGuard;
use App\Service\Cost\ApiUsageLogger;
use App\Service\KeywordDiscovery\AuditKeywordDiscoveryService;
use App\Service\Language\LanguagePromptInjector;
use App\Service\Serp\SerpProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AuditKeywordDiscoveryServiceTest extends TestCase
{
    public function testItCreatesAuditSuggestionsWithoutCallingFallbackWhenAuditHasEnoughData(): void
    {
        $project = (new Project())->setName('Skymotion')->setTargetCountry('FR')->setDefaultLanguage('fr');
        $audit = $this->audit([
            'detected_target_keywords' => [
                'location camera professionnelle',
                'materiel son professionnel',
                'studio fond vert',
                'regie video mobile',
                'captation conference',
                'streaming evenementiel',
                'eclairage plateau',
                'micro hf spectacle',
                'camera cinema location',
                'objectif longue focale',
                'location prompteur',
                'enregistrement podcast video',
                'plateau interview entreprise',
                'diffusion live hybride',
                'sonorisation congres',
                'production webinar',
                'location steadycam',
                'captation multicamera',
                'prestation technicien video',
                'location drone tournage',
            ],
            'competitor_terms' => array_combine([
                'catalogue audiovisuel entreprise',
                'location projecteur evenement',
                'pack streaming corporate',
                'console mixage numerique',
                'enceinte active conference',
                'camera broadcast plateau',
                'fond cyclorama tournage',
                'kit interview mobile',
                'retour video scene',
                'moniteur realisation',
                'captation assemblee generale',
                'solution webcast',
                'plateau virtuel',
                'micro cravate professionnel',
                'location pupitre lumiere',
                'ecran led evenement',
                'distribution signal video',
                'enregistreur externe',
                'liaison video sans fil',
                'location talkie walkie',
                'mixage audio live',
                'camera tourelle conference',
                'location optique cinema',
                'regie podcast',
                'serveur replay evenement',
                'location teleprompteur',
                'support camera epaule',
                'captation spectacle vivant',
                'location eclairage led',
                'solution traduction simultanee',
            ], array_fill(0, 30, 3)),
            'questions' => ['Comment louer une camera professionnelle ?'],
        ]);
        $persisted = [];

        $service = $this->service(
            $project,
            $audit,
            persisted: $persisted,
            serpProvider: $this->serpProviderExpectingNoSuggest(),
        );

        $summary = $service->discover($project, true);

        self::assertTrue($summary['audit_used']);
        self::assertFalse($summary['fallback_used']);
        self::assertGreaterThanOrEqual(50, $summary['created']);
        self::assertNotEmpty($persisted);
        self::assertSame(KeywordSuggestionSource::AUDIT_DETECTED_KEYWORD, $persisted[0]->getSource());
    }

    public function testItDeduplicatesAgainstExistingKeywordsArticlesAndSuggestions(): void
    {
        $project = (new Project())->setName('Skymotion');
        $audit = $this->audit([
            'detected_target_keywords' => ['Location camera professionnelle', 'Materiel son professionnel'],
            'content_gaps' => ['Captation video', 'solution live streaming evenementiel'],
        ]);
        $existingKeyword = (new Keyword())->setProject($project)->setTerm('Location camera professionnelle');
        $existingSuggestion = (new KeywordSuggestion())
            ->setProject($project)
            ->setTerm('Captation vidéo')
            ->setNormalizedTerm('captation video')
            ->setSource(KeywordSuggestionSource::AUDIT_DETECTED_KEYWORD);
        $persisted = [];

        $service = $this->service(
            $project,
            $audit,
            keywords: [$existingKeyword],
            articles: [['title' => 'Old article', 'slug' => 'materiel-son-professionnel']],
            suggestions: [$existingSuggestion],
            persisted: $persisted,
        );

        $summary = $service->discover($project, false);

        self::assertSame(1, $summary['created']);
        self::assertSame(1, $summary['updated']);
        self::assertSame('solution live streaming evenementiel', $persisted[0]->getTerm());
        self::assertSame(KeywordSuggestionSource::CONTENT_GAP, $existingSuggestion->getSource());
    }

    public function testItUsesClaudeAndSerpFallbackOnlyWhenAuditIsInsufficient(): void
    {
        $project = (new Project())->setName('Skymotion')->setTargetCountry('FR')->setDefaultLanguage('fr');
        $audit = $this->audit(['detected_target_keywords' => ['location camera']]);
        $persisted = [];
        $previousApiKey = $_ENV['CLAUDE_API_KEY'] ?? null;
        $_ENV['CLAUDE_API_KEY'] = 'test-key';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->with(false)->willReturn(json_encode([
            'content' => [[
                'type' => 'text',
                'text' => '{"seeds":["location plateau tv"]}',
            ]],
        ], JSON_THROW_ON_ERROR));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')->willReturn($response);

        $serpProvider = $this->createMock(SerpProviderInterface::class);
        $serpProvider
            ->expects(self::once())
            ->method('suggest')
            ->with('location plateau tv', 'FR', 'fr')
            ->willReturn(['location plateau tv paris']);

        try {
            $service = $this->service(
                $project,
                $audit,
                persisted: $persisted,
                serpProvider: $serpProvider,
                httpClient: $httpClient,
            );

            $summary = $service->discover($project, true);
        } finally {
            if (null === $previousApiKey) {
                unset($_ENV['CLAUDE_API_KEY']);
            } else {
                $_ENV['CLAUDE_API_KEY'] = $previousApiKey;
            }
        }

        self::assertTrue($summary['fallback_used']);
        self::assertSame(3, $summary['created']);
        self::assertSame(KeywordSuggestionSource::AI_GENERATED, $persisted[1]->getSource());
        self::assertSame(KeywordSuggestionSource::SERP_SUGGEST, $persisted[2]->getSource());
    }

    /** @param array<string, mixed> $analysis */
    private function audit(array $analysis): Audit
    {
        return (new Audit())
            ->setStatus(AuditStatus::COMPLETED)
            ->setMetadata(['ai_analysis' => $analysis]);
    }

    /**
     * @param list<Keyword>                             $keywords
     * @param list<array{title: string, slug: ?string}> $articles
     * @param list<KeywordSuggestion>                   $suggestions
     * @param list<KeywordSuggestion>                   $persisted
     */
    private function service(
        Project $project,
        Audit $audit,
        array $keywords = [],
        array $articles = [],
        array $suggestions = [],
        array &$persisted = [],
        ?SerpProviderInterface $serpProvider = null,
        ?HttpClientInterface $httpClient = null,
    ): AuditKeywordDiscoveryService {
        $auditRepository = $this->createMock(AuditRepository::class);
        $auditRepository->method('findLatestCompletedForProject')->with($project)->willReturn($audit);

        $keywordRepository = $this->createMock(KeywordRepository::class);
        $keywordRepository->method('findForProject')->with($project)->willReturn($keywords);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('findTitlesAndSlugsForProject')->with($project)->willReturn($articles);

        $suggestionRepository = $this->createMock(KeywordSuggestionRepository::class);
        $suggestionRepository
            ->method('findForProject')
            ->willReturnCallback(static function () use (&$persisted, $suggestions): array {
                return array_merge($suggestions, $persisted);
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                if ($entity instanceof KeywordSuggestion) {
                    $persisted[] = $entity;
                }
            });
        $entityManager->expects(self::once())->method('flush');
        $budgetRepository = $this->createMock(ProjectApiBudgetRepository::class);
        $budgetRepository->method('findForProject')->willReturn(null);
        $usageLogRepository = $this->createMock(ApiUsageLogRepository::class);
        $apiCostGuard = new ApiCostGuard($budgetRepository, $usageLogRepository, new NullLogger());
        $apiUsageLogger = new ApiUsageLogger($entityManager);

        return new AuditKeywordDiscoveryService(
            $auditRepository,
            $keywordRepository,
            $suggestionRepository,
            $articleRepository,
            $serpProvider ?? $this->serpProviderExpectingNoSuggest(),
            $entityManager,
            $httpClient ?? $this->createMock(HttpClientInterface::class),
            new NullLogger(),
            $apiCostGuard,
            $apiUsageLogger,
            new LanguagePromptInjector(),
        );
    }

    private function serpProviderExpectingNoSuggest(): SerpProviderInterface
    {
        $serpProvider = $this->createMock(SerpProviderInterface::class);
        $serpProvider->expects(self::never())->method('suggest');

        return $serpProvider;
    }
}
