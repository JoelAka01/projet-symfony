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
}
