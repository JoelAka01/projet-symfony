<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Audit;
use App\Service\Audit\AuditInsightsBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaudeSeoAnalysisService
{
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const DEFAULT_MAX_TOKENS = 16000;
    private const DEFAULT_TIMEOUT_SECONDS = 180;
    private const DEFAULT_MAX_DURATION_SECONDS = 240;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditInsightsBuilder $insightsBuilder,
        private readonly ClaudeSeoAnalysisResponseParser $responseParser,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function analyze(Audit $audit): void
    {
        $apiKey = $this->envString('CLAUDE_API_KEY');
        $model = $this->envString('CLAUDE_MODEL') ?? self::DEFAULT_MODEL;
        $baseUrl = rtrim($this->envString('CLAUDE_API_BASE_URL') ?? self::DEFAULT_BASE_URL, '/');
        $maxTokens = $this->envInt('CLAUDE_MAX_TOKENS', self::DEFAULT_MAX_TOKENS, 1500, 64000);
        $timeoutSeconds = $this->envInt('CLAUDE_TIMEOUT_SECONDS', self::DEFAULT_TIMEOUT_SECONDS, 30, 600);
        $maxDurationSeconds = $this->envInt(
            'CLAUDE_MAX_DURATION_SECONDS',
            max(self::DEFAULT_MAX_DURATION_SECONDS, $timeoutSeconds + 60),
            $timeoutSeconds,
            900,
        );

        if (null === $apiKey) {
            $this->storeAiMetadata($audit, [
                'status' => 'not_configured',
                'provider' => 'anthropic',
                'model' => $model,
                'max_tokens' => $maxTokens,
                'timeout_seconds' => $timeoutSeconds,
                'max_duration_seconds' => $maxDurationSeconds,
                'error' => 'CLAUDE_API_KEY is not configured. Real AI analysis cannot run.',
                'recommendations' => [],
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);

            return;
        }

        $this->storeAiMetadata($audit, [
            'status' => 'running',
            'provider' => 'anthropic',
            'model' => $model,
            'max_tokens' => $maxTokens,
            'timeout_seconds' => $timeoutSeconds,
            'max_duration_seconds' => $maxDurationSeconds,
            'recommendations' => [],
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $crawlSummary = $this->insightsBuilder->buildClaudePayload($audit);

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/v1/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.2,
                    'system' => $this->systemPrompt(),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Analyze this real website crawl and return only valid JSON matching the requested schema:\n\n".json_encode($crawlSummary, JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
                'timeout' => $timeoutSeconds,
                'max_duration' => $maxDurationSeconds,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
            if ($statusCode >= 400) {
                throw new \RuntimeException(sprintf('Claude API returned HTTP %d: %s', $statusCode, $this->limit($body, 1000)));
            }

            $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($responseData)) {
                throw new \UnexpectedValueException('Claude API response was not a JSON object.');
            }

            if (($responseData['stop_reason'] ?? null) === 'max_tokens') {
                throw new \UnexpectedValueException(sprintf('Claude response reached the configured max_tokens limit (%d). Increase CLAUDE_MAX_TOKENS or use a stronger model for complete professional analysis.', $maxTokens));
            }

            $responseText = $this->extractTextContent($responseData);
            $parsed = $this->responseParser->parse($responseText);

            $this->storeAiMetadata($audit, $parsed + [
                'status' => 'completed',
                'provider' => 'anthropic',
                'model' => $model,
                'max_tokens' => $maxTokens,
                'timeout_seconds' => $timeoutSeconds,
                'max_duration_seconds' => $maxDurationSeconds,
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'raw_response' => $this->limit($responseText, 15000),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Claude SEO analysis failed.', [
                'audit_id' => $audit->getId(),
                'exception' => $exception,
            ]);

            $this->storeAiMetadata($audit, [
                'status' => 'failed',
                'provider' => 'anthropic',
                'model' => $model,
                'max_tokens' => $maxTokens,
                'timeout_seconds' => $timeoutSeconds,
                'max_duration_seconds' => $maxDurationSeconds,
                'error' => $this->limit($exception->getMessage(), 2000),
                'recommendations' => [],
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function storeAiMetadata(Audit $audit, array $metadata): void
    {
        $auditMetadata = $audit->getMetadata() ?? [];
        $auditMetadata['ai_analysis'] = $metadata;
        $audit->setMetadata($auditMetadata);

        $this->entityManager->flush();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior SEO strategist, technical auditor, content strategist, and GEO (Generative Engine Optimization) specialist with 10+ years of agency experience.
You receive compact crawler facts from a real Symfony crawler. Treat those facts as the only source for objective crawl data.

Strict rules:
- Do not invent pages, HTTP statuses, metadata, headings, link counts, keyword metrics, performance scores, issue counts, crawl errors, competitors, backlinks, rankings, or analytics.
- Separate crawler evidence from SEO interpretation. If evidence is insufficient, say so in the relevant field.
- Keyword analysis must use crawled URL/title/meta/heading/body-excerpt/top-term evidence only. Do not pretend to know search volume or difficulty.
- Be specific and expert-level: include exact modifications, character-count targets, HTML attributes/tags, replacement examples, and implementation priority.
- Cover the crawled website as a whole, not only the first page. Use all_pages_compact, sample_pages, top_issues, duplicate_metadata, keyword_evidence, and aggregate_page_metrics.
- Prioritize recommendations by estimated SEO/GEO/ranking impact, then effort.
- Return only valid JSON. No markdown, no comments, no preamble.

Analysis depth expected:
- If data is enough, provide at least 8 recommendations and up to 15.
- Every recommendation must reference exact crawler evidence.
- For title/meta/H1 fixes, provide before_example and after_example when the current value exists.
- For missing sections, state the exact section to add and the user intent it supports.
- For GEO, suggest AI-answerable blocks, FAQs, entity improvements, and citation improvements grounded in the observed content.

Return only valid JSON with this schema:
{
  "global_score": 0-100,
  "technical_score": 0-100,
  "content_score": 0-100,
  "onpage_score": 0-100,
  "geo_score": 0-100,
  "ux_score": 0-100,
  "confidence": 0.0-1.0,
  "summary": "2-4 sentence expert verdict covering technical SEO, content, keyword targeting, and GEO readiness",
  "score_rationale": "string explaining the main reasons behind each score",
  "executive_summary": {
    "one_liner": "single sentence verdict",
    "situation": "2-3 sentence site-level SEO posture",
    "top_3_blockers": ["string"],
    "top_3_quick_wins": ["string"],
    "estimated_traffic_potential": "low|medium|high|very_high",
    "competitive_difficulty": "low|medium|high|unknown",
    "verdict": "critical|needs_work|decent|strong"
  },
  "audience_and_intent": {
    "primary_audience": "string",
    "secondary_audience": "string|null",
    "search_intent": "informational|commercial|transactional|navigational|mixed|uncertain",
    "intent_detail": "string",
    "buyer_journey_stage": "awareness|consideration|decision|retention|uncertain",
    "personas": ["string"]
  },
  "search_intent": "string",
  "target_audience": "string",
  "strengths": ["string"],
  "weaknesses": ["string"],
  "keyword_analysis": {
    "primary_topic": "string",
    "detected_target_keywords": [
      {
        "keyword": "string",
        "occurrences": 0,
        "placement": ["title|h1|h2|meta_description|body_excerpt|url|alt|unknown"],
        "intent": "informational|commercial|transactional|navigational|mixed|uncertain",
        "density_assessment": "absent|too_low|reasonable|too_high|insufficient_data",
        "evidence": "string",
        "recommendation": "string"
      }
    ],
    "missing_semantic_keywords": ["string"],
    "keyword_cannibalization_risks": ["string"],
    "over_optimized_patterns": ["string"],
    "heading_keyword_coverage": "string",
    "url_keyword_assessment": "string",
    "long_tail_opportunities": ["specific query or page idea"],
    "topic_clusters": [
      {
        "pillar_topic": "string",
        "supporting_topics": ["string"],
        "gap": "string"
      }
    ]
  },
  "technical_seo": {
    "indexability": {
      "status": "indexable|blocked|mixed|uncertain",
      "signals": ["string"],
      "canonical_analysis": "string",
      "issues": ["string"]
    },
    "crawlability": {
      "internal_links_count": 0,
      "broken_links_count": 0,
      "redirect_chains": ["string"],
      "orphan_page_risk": "low|medium|high|uncertain",
      "crawl_depth_assessment": "string",
      "issues": ["string"]
    },
    "page_speed_signals": {
      "estimated_lcp_risk": "low|medium|high|uncertain",
      "estimated_cls_risk": "low|medium|high|uncertain",
      "core_web_vitals_risks": ["string"]
    },
    "mobile_seo": {
      "viewport_meta_present": true,
      "responsive_signals": "string",
      "mobile_issues": ["string"]
    },
    "security_and_protocol": {
      "https_status": "string",
      "mixed_content_risk": "low|medium|high|uncertain",
      "issues": ["string"]
    },
    "internationalization": {
      "hreflang_present": false,
      "language_declared": "string|null",
      "issues": ["string"]
    }
  },
  "on_page_seo": {
    "title_tag": {
      "sitewide_assessment": "string",
      "best_current_example": "string|null",
      "worst_current_example": "string|null",
      "suggested_pattern": "string"
    },
    "meta_description": {
      "sitewide_assessment": "string",
      "suggested_pattern": "string"
    },
    "heading_structure": {
      "sitewide_assessment": "string",
      "issues": ["string"],
      "recommended_h1_pattern": "string|null"
    },
    "content_analysis": {
      "word_count_assessment": "very_thin|thin|acceptable|good|comprehensive|mixed|insufficient_data",
      "min_recommended_words": 0,
      "content_structure": "poor|fragmented|logical|excellent|mixed|insufficient_data",
      "missing_content_sections": ["string"],
      "content_freshness_signals": "string"
    },
    "images": {
      "total_count": 0,
      "missing_alt_count": 0,
      "issues": ["string"]
    },
    "links": {
      "internal_links_count": 0,
      "external_links_count": 0,
      "broken_links_count": 0,
      "anchor_text_diversity": "poor|acceptable|good|insufficient_data",
      "internal_linking_recommendations": ["string"]
    },
    "structured_data": {
      "detected_schemas": ["string"],
      "missing_recommended_schemas": [
        {
          "schema_type": "Organization|LocalBusiness|Article|FAQPage|BreadcrumbList|Product|Service|WebSite|Other",
          "reason": "string",
          "example_snippet": "minimal valid JSON-LD string"
        }
      ]
    },
    "open_graph_twitter": {
      "og_title_present": false,
      "og_description_present": false,
      "og_image_present": false,
      "twitter_card_present": false,
      "issues": ["string"]
    }
  },
  "content_strategy": {
    "strengths": ["string"],
    "weaknesses": ["string"],
    "content_gaps": [
      {
        "missing_topic": "string",
        "why_it_matters": "string",
        "suggested_format": "blog_post|landing_page|faq|comparison|guide|video|tool|case_study"
      }
    ],
    "content_opportunities": [
      {
        "opportunity": "string",
        "type": "expansion|rewrite|new_page|internal_link|schema|faq|definition_block",
        "estimated_impact": "low|medium|high",
        "effort": "low|medium|high"
      }
    ],
    "e_e_a_t_signals": {
      "expertise_signals": ["string"],
      "authoritativeness_signals": ["string"],
      "trustworthiness_signals": ["string"],
      "experience_signals": ["string"],
      "eeat_score": "weak|moderate|strong|insufficient_data",
      "improvements": ["string"]
    },
    "featured_snippet_opportunities": [
      {
        "query": "string",
        "snippet_type": "paragraph|list|table|definition",
        "current_gap": "string",
        "action": "string"
      }
    ],
    "faq_suggestions": [
      {
        "question": "string",
        "answer": "40-60 word concise answer",
        "schema_ready": true
      }
    ]
  },
  "geo_analysis": {
    "geo_score": 0-100,
    "ai_citation_potential": "low|medium|high",
    "citation_rationale": "string",
    "conversational_query_alignment": "string",
    "structured_answer_blocks": [
      {
        "question": "string",
        "answer": "direct answer an AI engine could cite"
      }
    ],
    "geo_improvements": [
      {
        "issue": "string",
        "action": "specific change to make content more citable",
        "target_ai": "ChatGPT|Perplexity|Gemini|all"
      }
    ],
    "entity_coverage": {
      "entities_detected": ["string"],
      "missing_entities": ["string"],
      "entity_linking_opportunities": ["string"]
    },
    "answer_engine_readiness": "poor|partial|good|excellent"
  },
  "serp_features": {
    "currently_eligible": ["string"],
    "optimization_needed": [
      {
        "feature": "featured_snippet|people_also_ask|knowledge_panel|local_pack|image_pack|video|sitelinks|review_stars",
        "current_gap": "string",
        "action": "string"
      }
    ]
  },
  "recommendations": [
    {
      "id": "REC-001",
      "priority": "critical|high|medium|low",
      "category": "metadata|content|technical|links|images|indexability|performance|geo|structured_data|keyword|eeat|ux",
      "title": "string",
      "problem": "what is wrong and why it hurts SEO",
      "evidence": "string from crawler facts",
      "why_it_matters": "string",
      "action": "step-by-step exact instructions",
      "before_example": "string|null",
      "after_example": "string|null",
      "expected_impact": "realistic outcome if fixed",
      "effort": "low|medium|high",
      "time_estimate": "string"
    }
  ],
  "suggested_title": "string|null",
  "suggested_meta_description": "string|null",
  "faq_suggestions": [{"question": "string", "answer": "string"}],
  "entities": ["string"],
  "citation_potential": "low|medium|high",
  "content_opportunities": ["string"],
  "technical_risks": ["string"],
  "short_answer_blocks": [{"question": "string", "answer": "string"}],
  "priority_matrix": {
    "critical_do_now": ["REC id or action"],
    "high_this_week": ["REC id or action"],
    "medium_this_month": ["REC id or action"],
    "low_backlog": ["REC id or action"]
  },
  "action_plan_30_60_90": {
    "day_30": ["concrete action with expected outcome"],
    "day_60": ["concrete action with expected outcome"],
    "day_90": ["concrete action with expected outcome"]
  }
}
PROMPT;
    }

    /** @param array<string, mixed> $responseData */
    private function extractTextContent(array $responseData): string
    {
        $content = $responseData['content'] ?? null;
        if (!is_array($content)) {
            throw new \UnexpectedValueException('Claude response did not include content blocks.');
        }

        $text = '';
        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === 'text' && is_scalar($block['text'] ?? null)) {
                $text .= (string) $block['text'];
            }
        }

        $text = trim($text);
        if ('' === $text) {
            throw new \UnexpectedValueException('Claude response did not include text content.');
        }

        return $text;
    }

    private function envString(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
        if (false === $value) {
            $value = getenv($name);
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function envInt(string $name, int $default, int $min, int $max): int
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
        if (false === $value) {
            $value = getenv($name);
        }

        if (!is_scalar($value) || '' === (string) $value) {
            return $default;
        }

        $integerValue = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $integerValue) {
            return $default;
        }

        return max($min, min($max, $integerValue));
    }

    private function limit(string $value, int $maxLength): string
    {
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}
