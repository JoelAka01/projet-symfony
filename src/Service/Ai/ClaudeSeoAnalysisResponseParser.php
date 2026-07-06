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
            throw new \UnexpectedValueException('Claude response JSON could not be parsed: ' . $exception->getMessage(), previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Claude response JSON must be an object.');
        }

        $decoded = $this->expandCompactAnalysis($decoded);
        $executiveSummary = $this->arrayValue($decoded['executive_summary'] ?? []);
        $executiveSummary['top_3_blockers'] = $this->summaryList($executiveSummary['top_3_blockers'] ?? []);
        $executiveSummary['top_3_quick_wins'] = $this->summaryList($executiveSummary['top_3_quick_wins'] ?? []);
        $scores = $this->arrayValue($decoded['scores'] ?? []);
        $summary = $this->stringValue($decoded['summary'] ?? null)
            ?? $this->stringValue($executiveSummary['one_liner'] ?? null)
            ?? $this->stringValue($executiveSummary['situation'] ?? null);
        if (null === $summary) {
            throw new \UnexpectedValueException('Claude response must include a summary string.');
        }

        $decoded['summary'] = $summary;
        $decoded['global_score'] = $this->scoreValue($decoded['global_score'] ?? $scores['global_score'] ?? null);
        $decoded['technical_score'] = $this->scoreValue($decoded['technical_score'] ?? $scores['technical_score'] ?? null);
        $decoded['content_score'] = $this->scoreValue($decoded['content_score'] ?? $scores['content_score'] ?? null);
        $decoded['onpage_score'] = $this->scoreValue($decoded['onpage_score'] ?? $scores['onpage_score'] ?? null);
        $decoded['geo_score'] = $this->scoreValue($decoded['geo_score'] ?? $scores['geo_score'] ?? null);
        $decoded['ux_score'] = $this->scoreValue($decoded['ux_score'] ?? $scores['ux_score'] ?? null);
        $decoded['confidence'] = $this->confidenceValue($decoded['confidence'] ?? null);
        $decoded['score_rationale'] = $this->stringValue($decoded['score_rationale'] ?? $scores['score_rationale'] ?? null);
        $decoded['executive_summary'] = $executiveSummary;
        $decoded['audience_and_intent'] = $this->arrayValue($decoded['audience_and_intent'] ?? []);
        $contentStrategy = $this->arrayValue($decoded['content_strategy'] ?? []);
        $decoded['strengths'] = $this->stringList($decoded['strengths'] ?? $contentStrategy['strengths'] ?? []);
        $decoded['weaknesses'] = $this->stringList($decoded['weaknesses'] ?? $contentStrategy['weaknesses'] ?? []);
        $decoded['content_opportunities'] = $this->stringList($decoded['content_opportunities'] ?? []);
        $decoded['technical_risks'] = $this->stringList($decoded['technical_risks'] ?? []);
        $decoded['entities'] = $this->stringList($decoded['entities'] ?? []);
        $decoded['recommendations'] = $this->recommendations($decoded['recommendations'] ?? []);
        $decoded['faq_suggestions'] = $this->faqSuggestions($decoded['faq_suggestions'] ?? []);
        $decoded['short_answer_blocks'] = $this->faqSuggestions($decoded['short_answer_blocks'] ?? []);
        $decoded['suggested_title'] = $this->stringValue($decoded['suggested_title'] ?? null);
        $decoded['suggested_meta_description'] = $this->stringValue($decoded['suggested_meta_description'] ?? null);
        $decoded['citation_potential'] = $this->stringValue($decoded['citation_potential'] ?? null);
        $decoded['search_intent'] = $this->stringValue($decoded['search_intent'] ?? $decoded['audience_and_intent']['search_intent'] ?? null);
        $decoded['target_audience'] = $this->stringValue($decoded['target_audience'] ?? $decoded['audience_and_intent']['primary_audience'] ?? null);
        $decoded['keyword_analysis'] = $this->arrayValue($decoded['keyword_analysis'] ?? []);
        $decoded['technical_seo'] = $this->arrayValue($decoded['technical_seo'] ?? []);
        $decoded['on_page_seo'] = $this->arrayValue($decoded['on_page_seo'] ?? []);
        $decoded['content_strategy'] = $contentStrategy;
        $decoded['geo_analysis'] = $this->arrayValue($decoded['geo_analysis'] ?? []);
        $decoded['serp_features'] = $this->arrayValue($decoded['serp_features'] ?? []);
        $decoded['priority_matrix'] = $this->arrayValue($decoded['priority_matrix'] ?? []);
        $actionPlan = $this->arrayValue($decoded['action_plan_30_60_90'] ?? $decoded['30_60_90_plan'] ?? []);
        $decoded['action_plan_30_60_90'] = [
            'day_30' => $this->stringList($actionPlan['day_30'] ?? []),
            'day_60' => $this->stringList($actionPlan['day_60'] ?? []),
            'day_90' => $this->stringList($actionPlan['day_90'] ?? []),
        ];

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return array<string, mixed>
     */
    private function expandCompactAnalysis(array $decoded): array
    {
        $scores = $this->arrayValue($decoded['scores'] ?? []);
        if (array_key_exists('global', $scores)) {
            $decoded['global_score'] = $scores['global'] ?? null;
            $decoded['technical_score'] = $scores['technical'] ?? null;
            $decoded['content_score'] = $scores['content'] ?? null;
            $decoded['onpage_score'] = $scores['onpage'] ?? null;
            $decoded['geo_score'] = $scores['geo'] ?? null;
            $decoded['ux_score'] = $scores['ux'] ?? null;
            $decoded['confidence'] = $scores['confidence'] ?? null;
        }

        $audience = $this->arrayValue($decoded['audience'] ?? []);
        if ([] !== $audience) {
            $decoded['audience_and_intent'] = [
                'primary_audience' => $audience['primary'] ?? null,
                'search_intent' => $audience['intent'] ?? null,
                'buyer_journey_stage' => $audience['journey_stage'] ?? null,
            ];
        }

        if (isset($decoded['blockers']) || isset($decoded['quick_wins'])) {
            $decoded['executive_summary'] = [
                'one_liner' => $decoded['summary'] ?? null,
                'situation' => $decoded['score_rationale'] ?? null,
                'top_3_blockers' => $decoded['blockers'] ?? [],
                'top_3_quick_wins' => $decoded['quick_wins'] ?? [],
            ];
        }

        $keywords = $this->arrayValue($decoded['keywords'] ?? []);
        if ([] !== $keywords) {
            $targets = [];
            foreach ($this->arrayValue($keywords['targets'] ?? []) as $target) {
                if (!is_array($target)) {
                    continue;
                }

                $targets[] = [
                    'keyword' => $target['keyword'] ?? null,
                    'occurrences' => 0,
                    'placement' => $target['placement'] ?? [],
                    'intent' => $audience['intent'] ?? 'uncertain',
                    'density_assessment' => 'insufficient_data',
                    'evidence' => $target['evidence'] ?? null,
                    'recommendation' => $target['recommendation'] ?? null,
                ];
            }

            $decoded['keyword_analysis'] = [
                'primary_topic' => $keywords['primary_topic'] ?? null,
                'heading_keyword_coverage' => $keywords['assessment'] ?? null,
                'detected_target_keywords' => $targets,
                'missing_semantic_keywords' => $keywords['semantic_gaps'] ?? [],
                'long_tail_opportunities' => $keywords['long_tail'] ?? [],
            ];
        }

        $technical = $this->arrayValue($decoded['technical'] ?? []);
        if ([] !== $technical) {
            $decoded['technical_seo'] = [
                'indexability' => [
                    'status' => $technical['indexability'] ?? 'uncertain',
                    'canonical_analysis' => $technical['canonical'] ?? null,
                    'issues' => $technical['issues'] ?? [],
                ],
                'crawlability' => [
                    'crawl_depth_assessment' => $technical['crawlability'] ?? null,
                    'issues' => $technical['issues'] ?? [],
                ],
                'page_speed_signals' => [
                    'estimated_lcp_risk' => 'uncertain',
                    'estimated_cls_risk' => 'uncertain',
                    'core_web_vitals_risks' => isset($technical['performance']) ? [$technical['performance']] : [],
                ],
                'mobile_seo' => [
                    'responsive_signals' => $technical['mobile'] ?? null,
                    'mobile_issues' => [],
                ],
                'summary' => $technical['indexability'] ?? null,
            ];
        }

        $onPage = $this->arrayValue($decoded['on_page'] ?? []);
        if ([] !== $onPage) {
            $decoded['on_page_seo'] = [
                'title_tag' => [
                    'sitewide_assessment' => $onPage['title'] ?? null,
                    'suggested_pattern' => $onPage['title_pattern'] ?? null,
                ],
                'meta_description' => [
                    'sitewide_assessment' => $onPage['meta'] ?? null,
                    'suggested_pattern' => $onPage['meta_pattern'] ?? null,
                ],
                'heading_structure' => [
                    'sitewide_assessment' => $onPage['headings'] ?? null,
                    'issues' => [],
                    'recommended_h1_pattern' => $onPage['h1_pattern'] ?? null,
                ],
                'content_analysis' => [
                    'word_count_assessment' => $onPage['content'] ?? null,
                    'min_recommended_words' => $onPage['min_words'] ?? 0,
                    'content_structure' => $onPage['content'] ?? null,
                    'missing_content_sections' => $onPage['missing_sections'] ?? [],
                ],
            ];
        }

        $contentStrategy = $this->arrayValue($decoded['content_strategy'] ?? []);
        if (isset($contentStrategy['eeat']) || isset($contentStrategy['faqs'])) {
            $decoded['content_strategy'] = [
                'strengths' => $decoded['strengths'] ?? [],
                'weaknesses' => $decoded['weaknesses'] ?? [],
                'e_e_a_t_signals' => [
                    'eeat_score' => $contentStrategy['eeat'] ?? null,
                    'improvements' => $contentStrategy['improvements'] ?? [],
                ],
                'faq_suggestions' => $contentStrategy['faqs'] ?? [],
            ];
            $decoded['faq_suggestions'] = $contentStrategy['faqs'] ?? [];
        }

        $geo = $this->arrayValue($decoded['geo'] ?? []);
        if ([] !== $geo) {
            $modelVisibility = [];
            $optimizations = [];
            foreach ($this->arrayValue($geo['models'] ?? []) as $modelKey => $model) {
                if (!is_array($model)) {
                    continue;
                }

                $modelName = $model['model'] ?? $modelKey;
                if (!is_scalar($modelName)) {
                    continue;
                }

                $key = strtolower((string) $modelName);
                if (!in_array($key, ['chatgpt', 'gemini', 'perplexity'], true)) {
                    continue;
                }

                $readiness = $this->stringValue($model['readiness'] ?? null);
                $assessment = [
                    'status' => $model['status'] ?? $this->visibilityStatus($readiness),
                    'how_mentioned' => $model['assessment'] ?? $model['how_mentioned'] ?? $readiness,
                    'sentiment' => $model['sentiment'] ?? null,
                    'evidence' => $this->stringList($model['evidence'] ?? []),
                ];
                $confidence = $this->confidenceValue($model['confidence'] ?? null);
                if (null !== $confidence) {
                    $assessment['confidence'] = $confidence;
                }
                $modelVisibility[$key] = $assessment;

                foreach ($this->stringList($model['optimizations'] ?? []) as $action) {
                    $optimizations[] = [
                        'target_ai' => match ($key) {
                            'chatgpt' => 'ChatGPT',
                            'gemini' => 'Gemini',
                            'perplexity' => 'Perplexity',
                        },
                        'current_gap' => $readiness,
                        'correction_action' => $action,
                    ];
                }
            }

            foreach ($this->arrayValue($geo['optimizations'] ?? []) as $optimization) {
                if (!is_array($optimization)) {
                    continue;
                }

                $optimizations[] = [
                    'target_ai' => $optimization['target'] ?? null,
                    'current_gap' => $optimization['gap'] ?? null,
                    'correction_action' => $optimization['action'] ?? null,
                ];
            }

            $decoded['geo_analysis'] = [
                'geo_score' => $decoded['geo_score'] ?? null,
                'ai_citation_potential' => $geo['citation_potential'] ?? null,
                'citation_rationale' => $geo['citation_rationale'] ?? null,
                'methodology_notice' => $geo['methodology'] ?? null,
                'ai_brand_visibility' => $modelVisibility,
                'ai_seo_optimizations' => $optimizations,
                'structured_answer_blocks' => $geo['answer_blocks'] ?? [],
                'entity_coverage' => [
                    'entities_detected' => $geo['entities'] ?? [],
                    'missing_entities' => $geo['missing_entities'] ?? [],
                    'entity_linking_opportunities' => [],
                ],
                'answer_engine_readiness' => $geo['readiness'] ?? null,
            ];
            $decoded['citation_potential'] = $geo['citation_potential'] ?? null;
            $decoded['entities'] = $geo['entities'] ?? [];
            $decoded['short_answer_blocks'] = $geo['answer_blocks'] ?? [];
        }

        $serpOpportunities = $this->arrayValue($decoded['serp_opportunities'] ?? []);
        if ([] !== $serpOpportunities) {
            $optimizationNeeded = [];
            foreach ($serpOpportunities as $opportunity) {
                if (!is_array($opportunity)) {
                    continue;
                }

                $optimizationNeeded[] = [
                    'feature' => $opportunity['feature'] ?? null,
                    'current_gap' => $opportunity['gap'] ?? null,
                    'action' => $opportunity['action'] ?? null,
                ];
            }
            $decoded['serp_features'] = ['optimization_needed' => $optimizationNeeded];
        }

        if (isset($decoded['day_30']) || isset($decoded['day_60']) || isset($decoded['day_90'])) {
            $decoded['action_plan_30_60_90'] = [
                'day_30' => $this->stringList($decoded['day_30'] ?? []),
                'day_60' => $this->stringList($decoded['day_60'] ?? []),
                'day_90' => $this->stringList($decoded['day_90'] ?? []),
            ];
        }

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

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
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

    /** @return list<string> */
    private function summaryList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $summaries = [];
        foreach ($value as $item) {
            $text = $this->stringValue($item);
            if (null !== $text) {
                $summaries[] = $text;

                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $title = $this->stringValue($item['title'] ?? $item['name'] ?? $item['label'] ?? null);
            $action = $this->stringValue(
                $item['action']
                    ?? $item['recommendation']
                    ?? $item['description']
                    ?? $item['problem']
                    ?? null,
            );
            if (null === $title) {
                if (null !== $action) {
                    $summaries[] = $action;
                }

                continue;
            }

            $summaries[] = null !== $action && $title !== $action
                ? sprintf('%s: %s', $title, $action)
                : $title;
        }

        return $summaries;
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
                'id' => $this->stringValue($item['id'] ?? null),
                'priority' => $this->stringValue($item['priority'] ?? null) ?? 'medium',
                'category' => $this->stringValue($item['category'] ?? null) ?? 'seo',
                'title' => $title ?? 'SEO recommendation',
                'problem' => $this->stringValue($item['problem'] ?? null),
                'evidence' => $this->stringValue($item['evidence'] ?? null),
                'why_it_matters' => $this->stringValue($item['why_it_matters'] ?? $item['why'] ?? null),
                'action' => $action,
                'before_example' => $this->stringValue($item['before_example'] ?? $item['before'] ?? null),
                'after_example' => $this->stringValue($item['after_example'] ?? $item['after'] ?? null),
                'expected_impact' => $this->stringValue($item['expected_impact'] ?? $item['impact'] ?? null),
                'effort' => $this->stringValue($item['effort'] ?? null),
                'time_estimate' => $this->stringValue($item['time_estimate'] ?? null),
            ];
        }

        return $recommendations;
    }

    private function visibilityStatus(?string $readiness): string
    {
        if (null === $readiness) {
            return 'unknown';
        }

        if (1 === preg_match('/(\d{1,3})\s*\/\s*100/', $readiness, $matches)) {
            $score = max(0, min(100, (int) $matches[1]));

            return match (true) {
                $score >= 80 => 'highly_visible',
                $score >= 60 => 'visible',
                $score >= 40 => 'moderate_visibility',
                default => 'low_visibility',
            };
        }

        $normalized = strtolower($readiness);

        return match (true) {
            str_contains($normalized, 'high') => 'visible',
            str_contains($normalized, 'medium') => 'moderate_visibility',
            str_contains($normalized, 'low') => 'low_visibility',
            default => 'unknown',
        };
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
