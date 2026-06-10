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
  "faq_suggestions": [{"question": "What is SEO?", "answer": "SEO improves search visibility."}]
}
```
JSON);

        self::assertSame(84, $result['global_score']);
        self::assertSame(0.82, $result['confidence']);
        self::assertSame('The crawl is technically healthy but content needs clearer answers.', $result['summary']);
        self::assertCount(1, $result['recommendations']);
        self::assertSame('Add FAQ coverage', $result['recommendations'][0]['title']);
        self::assertSame('What is SEO?', $result['faq_suggestions'][0]['question']);
    }

    public function testItRejectsResponsesWithoutJson(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $this->expectException(\UnexpectedValueException::class);

        $parser->parse('I cannot provide JSON here.');
    }

    public function testItRequiresSummary(): void
    {
        $parser = new ClaudeSeoAnalysisResponseParser();

        $this->expectException(\UnexpectedValueException::class);

        $parser->parse('{"recommendations": []}');
    }
}
