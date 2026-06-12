<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Service\Ai\ClaudeSeoAnalysisResponseParser;
use PHPUnit\Framework\TestCase;

final class ClaudeSeoAnalysisResponseParserTest extends TestCase
{
    public function testItParsesFencedClaudeJson(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $result = $parser->parse(<<<'JSON'
```json
{
  "global_score": 84.4,
  "technical_score": 91,
  "content_score": 76,
  "geo_score": 69,
  "confidence": 0.82,
  "summary": "The crawl is technically healthy but content needs clearer answers.",
  "strengths": ["Titles are present."],
  "weaknesses": ["FAQ coverage is missing."],
  "recommendations": [
    {
      "priority": "high",
      "category": "content",
      "title": "Add FAQ coverage",
      "problem": "The page lacks question-led sections.",
      "evidence": "Crawler found no FAQ-like headings.",
      "why_it_matters": "It improves AI answerability.",
      "action": "Add four concise FAQ answers.",
      "expected_impact": "Better GEO readiness.",
      "effort": "low"
    }
  ],
  "faq_suggestions": [{"question": "What is SEO?", "answer": "SEO improves search visibility."}],
  "geo_analysis": {
    "geo_score": 69,
    "methodology_notice": "Claude estimates readiness; the external models were not queried.",
    "ai_brand_visibility": {
      "chatgpt": {
        "status": "visible",
        "how_mentioned": "ChatGPT mentions it as a secondary brand.",
        "sentiment": "neutral"
      },
      "gemini": {
        "status": "low_visibility",
        "how_mentioned": "Gemini rarely references it.",
        "sentiment": "neutral"
      },
      "perplexity": {
        "status": "low_visibility",
        "how_mentioned": "Claude estimates weak citation readiness.",
        "sentiment": "unknown"
      }
    },
    "ai_seo_optimizations": [
      {
        "target_ai": "ChatGPT",
        "current_gap": "Lack of comparison tables.",
        "correction_action": "Add comparison table."
      }
    ]
  }
}
```
JSON);

        self::assertSame(84, $result['global_score']);
        self::assertSame(0.82, $result['confidence']);
        self::assertSame('The crawl is technically healthy but content needs clearer answers.', $result['summary']);
        self::assertCount(1, $result['recommendations']);
        self::assertSame('Add FAQ coverage', $result['recommendations'][0]['title']);
        self::assertSame('What is SEO?', $result['faq_suggestions'][0]['question']);
        self::assertSame('visible', $result['geo_analysis']['ai_brand_visibility']['chatgpt']['status']);
        self::assertSame('low_visibility', $result['geo_analysis']['ai_brand_visibility']['gemini']['status']);
        self::assertSame('low_visibility', $result['geo_analysis']['ai_brand_visibility']['perplexity']['status']);
        self::assertSame('ChatGPT', $result['geo_analysis']['ai_seo_optimizations'][0]['target_ai']);
    }

    public function testItRejectsResponsesWithoutJson(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $this->expectException(\UnexpectedValueException::class);

        $parser->parse('I cannot provide JSON here.');
    }

    public function testItParsesProfessionalNestedAnalysis(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $result = $parser->parse(<<<'JSON'
{
  "scores": {
    "global_score": 72,
    "technical_score": 68,
    "content_score": 61,
    "onpage_score": 64,
    "geo_score": 55,
    "ux_score": 70,
    "score_rationale": "Metadata and GEO gaps reduce the score."
  },
  "executive_summary": {
    "one_liner": "The site has a crawlable base but weak keyword targeting.",
    "top_3_blockers": ["Thin content", "Missing semantic coverage"]
  },
  "audience_and_intent": {
    "primary_audience": "B2B buyers",
    "search_intent": "commercial"
  },
  "keyword_analysis": {
    "primary_topic": "SEO software",
    "detected_target_keywords": [
      {
        "keyword": "seo audit",
        "occurrences": 4,
        "placement": ["title", "h1"],
        "intent": "commercial",
        "density_assessment": "reasonable",
        "evidence": "Observed in title and H1.",
        "recommendation": "Keep it in the title and expand supporting topics."
      }
    ]
  },
  "recommendations": [
    {
      "id": "REC-001",
      "priority": "high",
      "category": "keyword",
      "title": "Strengthen keyword targeting",
      "action": "Add comparison and FAQ sections.",
      "before_example": "SEO tool",
      "after_example": "SEO audit software for agencies",
      "time_estimate": "2 hours"
    }
  ]
}
JSON);

        self::assertSame('The site has a crawlable base but weak keyword targeting.', $result['summary']);
        self::assertSame(72, $result['global_score']);
        self::assertSame(64, $result['onpage_score']);
        self::assertSame('commercial', $result['search_intent']);
        self::assertSame('B2B buyers', $result['target_audience']);
        self::assertSame('SEO software', $result['keyword_analysis']['primary_topic']);
        self::assertSame('REC-001', $result['recommendations'][0]['id']);
        self::assertSame('SEO audit software for agencies', $result['recommendations'][0]['after_example']);
    }

    public function testItRequiresSummary(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $this->expectException(\UnexpectedValueException::class);

        $parser->parse('{"recommendations": []}');
    }

    public function testItExpandsCompactClaudeAnalysisForTheExistingDashboard(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $result = $parser->parse(json_encode([
            'scores' => [
                'global' => 77,
                'technical' => 81,
                'content' => 68,
                'onpage' => 74,
                'geo' => 63,
                'ux' => 70,
                'confidence' => 0.79,
            ],
            'summary' => 'The website needs stronger entities and answer blocks.',
            'score_rationale' => 'Content and GEO gaps reduce the score.',
            'audience' => [
                'primary' => 'Local buyers',
                'intent' => 'commercial',
                'journey_stage' => 'consideration',
            ],
            'blockers' => ['Weak entity clarity'],
            'quick_wins' => ['Add concise FAQs'],
            'keywords' => [
                'primary_topic' => 'Local marketplace',
                'assessment' => 'Headings partly cover the topic.',
                'targets' => [[
                    'keyword' => 'local marketplace',
                    'placement' => ['title', 'h1'],
                    'evidence' => 'Present in the title and H1.',
                    'recommendation' => 'Use it in direct answer blocks.',
                ]],
                'semantic_gaps' => ['buyer protection'],
                'long_tail' => ['how to buy safely locally'],
            ],
            'technical' => [
                'indexability' => 'indexable',
                'canonical' => 'Canonical tags are mostly consistent.',
                'crawlability' => 'Internal links support discovery.',
                'performance' => 'Some pages have elevated response times.',
                'mobile' => 'Viewport signals are present.',
                'issues' => [],
            ],
            'on_page' => [
                'title' => 'Titles are mostly descriptive.',
                'title_pattern' => 'Primary topic | Brand',
                'meta' => 'Descriptions need stronger value propositions.',
                'meta_pattern' => 'Benefit and location in 150 characters.',
                'headings' => 'Most pages use one H1.',
                'h1_pattern' => 'Primary service in target location',
                'content' => 'mixed',
                'min_words' => 700,
                'missing_sections' => ['Buyer safety guidance'],
            ],
            'content_strategy' => [
                'eeat' => 'moderate',
                'improvements' => ['Add author and source details.'],
                'faqs' => [['question' => 'How does it work?', 'answer' => 'Users browse and contact sellers.']],
            ],
            'geo' => [
                'methodology' => 'Claude-only readiness estimate.',
                'citation_potential' => 'medium',
                'citation_rationale' => 'The brand is clear but lacks sourced claims.',
                'readiness' => 'partial',
                'models' => [[
                    'model' => 'Perplexity',
                    'status' => 'low_visibility',
                    'assessment' => 'Claude estimates weak source-backed citation readiness.',
                    'sentiment' => 'neutral',
                    'confidence' => 0.7,
                    'evidence' => ['Few external citations were observed.'],
                ]],
                'optimizations' => [[
                    'target' => 'Perplexity',
                    'gap' => 'Few sourced claims.',
                    'action' => 'Add first-party data and citations.',
                ]],
                'answer_blocks' => [['question' => 'What is the service?', 'answer' => 'A local marketplace.']],
                'entities' => ['Marketplace'],
                'missing_entities' => ['Buyer protection'],
            ],
            'recommendations' => [[
                'id' => 'REC-001',
                'priority' => 'high',
                'category' => 'geo',
                'title' => 'Add sourced answer blocks',
                'problem' => 'Important claims lack evidence.',
                'evidence' => 'Few citations were found.',
                'why' => 'AI answer engines favor clear sourced claims.',
                'action' => 'Add concise answers with first-party evidence.',
                'before' => '',
                'after' => 'A sourced 50-word answer.',
                'impact' => 'Improves citation readiness.',
                'effort' => 'medium',
            ]],
            'suggested_title' => 'Local Marketplace | Brand',
            'suggested_meta_description' => 'Browse trusted local listings.',
            'serp_opportunities' => [],
            'day_30' => ['Fix entity clarity.'],
            'day_60' => ['Publish sourced guides.'],
            'day_90' => ['Measure repeat audits.'],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(77, $result['global_score']);
        self::assertSame('Local buyers', $result['target_audience']);
        self::assertSame('Local marketplace', $result['keyword_analysis']['primary_topic']);
        self::assertSame('indexable', $result['technical_seo']['indexability']['status']);
        self::assertSame('moderate', $result['content_strategy']['e_e_a_t_signals']['eeat_score']);
        self::assertSame('low_visibility', $result['geo_analysis']['ai_brand_visibility']['perplexity']['status']);
        self::assertSame('AI answer engines favor clear sourced claims.', $result['recommendations'][0]['why_it_matters']);
        self::assertSame(['Fix entity clarity.'], $result['action_plan_30_60_90']['day_30']);
    }

    public function testItExpandsKeyedModelReadinessReturnedByPromptJsonFallback(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $result = $parser->parse(json_encode([
            'scores' => ['global' => 37, 'content' => 42, 'geo' => 65],
            'summary' => 'The site needs stronger answer-ready content.',
            'geo' => [
                'models' => [
                    'chatgpt' => [
                        'readiness' => 'Low-Medium (45/100). Thin content limits recommendation readiness.',
                        'how_mentioned' => 'Claude estimates ChatGPT would describe the business as a local marketplace.',
                        'optimizations' => ['Add concise category FAQs.'],
                    ],
                    'gemini' => [
                        'readiness' => 'Medium (55/100). Structured product data is a strength.',
                        'how_mentioned' => 'Claude estimates Gemini would surface product listings.',
                        'optimizations' => ['Add LocalBusiness structured data.'],
                    ],
                    'perplexity' => [
                        'readiness' => 'High (72/100). Original evidence would improve citations.',
                        'how_mentioned' => 'Claude estimates Perplexity would cite category pages.',
                        'optimizations' => ['Publish first-party market reports.'],
                    ],
                ],
            ],
            'recommendations' => [],
        ], JSON_THROW_ON_ERROR));

        $visibility = $result['geo_analysis']['ai_brand_visibility'];
        self::assertSame('moderate_visibility', $visibility['chatgpt']['status']);
        self::assertSame('moderate_visibility', $visibility['gemini']['status']);
        self::assertSame('visible', $visibility['perplexity']['status']);
        self::assertSame(
            'Claude estimates ChatGPT would describe the business as a local marketplace.',
            $visibility['chatgpt']['how_mentioned'],
        );
        self::assertCount(3, $result['geo_analysis']['ai_seo_optimizations']);
        self::assertSame('ChatGPT', $result['geo_analysis']['ai_seo_optimizations'][0]['target_ai']);
    }
}
