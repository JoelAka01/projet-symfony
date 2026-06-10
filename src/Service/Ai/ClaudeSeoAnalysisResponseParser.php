<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class ClaudeSeoAnalysisResponseParser
{
    /** @return array<string, mixed> */
    public function parse(string $responseText): array
    {
        $json = $this->extractJson($responseText);
        if (null === $json) {
            throw new \UnexpectedValueException('Claude response did not contain a JSON object.');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException('Claude response JSON could not be parsed: '.$exception->getMessage(), previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Claude response JSON must be an object.');
        }

        $summary = $this->stringValue($decoded['summary'] ?? null);
        if (null === $summary) {
            throw new \UnexpectedValueException('Claude response must include a summary string.');
        }

        $decoded['summary'] = $summary;
        $decoded['global_score'] = $this->scoreValue($decoded['global_score'] ?? null);
        $decoded['technical_score'] = $this->scoreValue($decoded['technical_score'] ?? null);
        $decoded['content_score'] = $this->scoreValue($decoded['content_score'] ?? null);
        $decoded['geo_score'] = $this->scoreValue($decoded['geo_score'] ?? null);
        $decoded['confidence'] = $this->confidenceValue($decoded['confidence'] ?? null);
        $decoded['strengths'] = $this->stringList($decoded['strengths'] ?? []);
        $decoded['weaknesses'] = $this->stringList($decoded['weaknesses'] ?? []);
        $decoded['content_opportunities'] = $this->stringList($decoded['content_opportunities'] ?? []);
        $decoded['technical_risks'] = $this->stringList($decoded['technical_risks'] ?? []);
        $decoded['entities'] = $this->stringList($decoded['entities'] ?? []);
        $decoded['recommendations'] = $this->recommendations($decoded['recommendations'] ?? []);
        $decoded['faq_suggestions'] = $this->faqSuggestions($decoded['faq_suggestions'] ?? []);
        $decoded['short_answer_blocks'] = $this->faqSuggestions($decoded['short_answer_blocks'] ?? []);
        $decoded['suggested_title'] = $this->stringValue($decoded['suggested_title'] ?? null);
        $decoded['suggested_meta_description'] = $this->stringValue($decoded['suggested_meta_description'] ?? null);
        $decoded['citation_potential'] = $this->stringValue($decoded['citation_potential'] ?? null);
        $decoded['search_intent'] = $this->stringValue($decoded['search_intent'] ?? null);
        $decoded['target_audience'] = $this->stringValue($decoded['target_audience'] ?? null);

        return $decoded;
    }

    private function extractJson(string $responseText): ?string
    {
        $candidate = trim($responseText);
        $candidate = (string) preg_replace('/^```(?:json)?\s*/i', '', $candidate);
        $candidate = (string) preg_replace('/\s*```$/', '', $candidate);

        $firstBrace = strpos($candidate, '{');
        $lastBrace = strrpos($candidate, '}');
        if (false === $firstBrace || false === $lastBrace || $lastBrace <= $firstBrace) {
            return null;
        }

        return substr($candidate, $firstBrace, $lastBrace - $firstBrace + 1);
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return '' === $text ? null : $text;
    }

    private function scoreValue(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function confidenceValue(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return max(0.0, min(1.0, round((float) $value, 2)));
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            $text = $this->stringValue($item);
            if (null !== $text) {
                $strings[] = $text;
            }
        }

        return $strings;
    }

    /** @return list<array<string, string|null>> */
    private function recommendations(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $recommendations = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = $this->stringValue($item['title'] ?? null);
            $action = $this->stringValue($item['action'] ?? $item['recommendation'] ?? null);
            if (null === $title && null === $action) {
                continue;
            }

            $recommendations[] = [
                'priority' => $this->stringValue($item['priority'] ?? null) ?? 'medium',
                'category' => $this->stringValue($item['category'] ?? null) ?? 'seo',
                'title' => $title ?? 'SEO recommendation',
                'problem' => $this->stringValue($item['problem'] ?? null),
                'evidence' => $this->stringValue($item['evidence'] ?? null),
                'why_it_matters' => $this->stringValue($item['why_it_matters'] ?? null),
                'action' => $action,
                'expected_impact' => $this->stringValue($item['expected_impact'] ?? $item['impact'] ?? null),
                'effort' => $this->stringValue($item['effort'] ?? null),
            ];
        }

        return $recommendations;
    }

    /** @return list<array{question: string, answer: string}> */
    private function faqSuggestions(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $questions = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = $this->stringValue($item['question'] ?? null);
            $answer = $this->stringValue($item['answer'] ?? null);
            if (null !== $question && null !== $answer) {
                $questions[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        return $questions;
    }
}
