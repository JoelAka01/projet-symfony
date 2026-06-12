<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class ClaudeSeoAnalysisSchema
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        return $this->object([
            'scores' => $this->object([
                'global' => $this->integer(),
                'technical' => $this->integer(),
                'content' => $this->integer(),
                'onpage' => $this->integer(),
                'geo' => $this->integer(),
                'ux' => $this->integer(),
                'confidence' => $this->number(),
            ]),
            'summary' => $this->string(),
            'score_rationale' => $this->string(),
            'audience' => $this->object([
                'primary' => $this->string(),
                'intent' => $this->string(),
                'journey_stage' => $this->string(),
            ]),
            'strengths' => $this->stringList(),
            'weaknesses' => $this->stringList(),
            'blockers' => $this->stringList(),
            'quick_wins' => $this->stringList(),
            'keywords' => $this->object([
                'primary_topic' => $this->string(),
                'assessment' => $this->string(),
                'targets' => $this->objectList([
                    'keyword' => $this->string(),
                    'placement' => $this->stringList(),
                    'evidence' => $this->string(),
                    'recommendation' => $this->string(),
                ]),
                'semantic_gaps' => $this->stringList(),
                'long_tail' => $this->stringList(),
            ]),
            'technical' => $this->object([
                'indexability' => $this->string(),
                'canonical' => $this->string(),
                'crawlability' => $this->string(),
                'performance' => $this->string(),
                'mobile' => $this->string(),
                'issues' => $this->stringList(),
            ]),
            'on_page' => $this->object([
                'title' => $this->string(),
                'title_pattern' => $this->string(),
                'meta' => $this->string(),
                'meta_pattern' => $this->string(),
                'headings' => $this->string(),
                'h1_pattern' => $this->string(),
                'content' => $this->string(),
                'min_words' => $this->integer(),
                'missing_sections' => $this->stringList(),
            ]),
            'content_strategy' => $this->object([
                'eeat' => $this->string(),
                'improvements' => $this->stringList(),
                'faqs' => $this->objectList([
                    'question' => $this->string(),
                    'answer' => $this->string(),
                ]),
            ]),
            'geo' => $this->object([
                'methodology' => $this->string(),
                'citation_potential' => $this->string(),
                'citation_rationale' => $this->string(),
                'readiness' => $this->string(),
                'models' => $this->objectList([
                    'model' => $this->string(),
                    'status' => $this->string(),
                    'assessment' => $this->string(),
                    'sentiment' => $this->string(),
                    'confidence' => $this->number(),
                    'evidence' => $this->stringList(),
                ]),
                'optimizations' => $this->objectList([
                    'target' => $this->string(),
                    'gap' => $this->string(),
                    'action' => $this->string(),
                ]),
                'answer_blocks' => $this->objectList([
                    'question' => $this->string(),
                    'answer' => $this->string(),
                ]),
                'entities' => $this->stringList(),
                'missing_entities' => $this->stringList(),
            ]),
            'recommendations' => $this->objectList([
                'id' => $this->string(),
                'priority' => $this->string(),
                'category' => $this->string(),
                'title' => $this->string(),
                'problem' => $this->string(),
                'evidence' => $this->string(),
                'why' => $this->string(),
                'action' => $this->string(),
                'before' => $this->string(),
                'after' => $this->string(),
                'impact' => $this->string(),
                'effort' => $this->string(),
            ]),
            'suggested_title' => $this->string(),
            'suggested_meta_description' => $this->string(),
            'serp_opportunities' => $this->objectList([
                'feature' => $this->string(),
                'gap' => $this->string(),
                'action' => $this->string(),
            ]),
            'day_30' => $this->stringList(),
            'day_60' => $this->stringList(),
            'day_90' => $this->stringList(),
        ]);
    }

    /** @return array<string, mixed> */
    private function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, string> */
    private function string(): array
    {
        return ['type' => 'string'];
    }

    /** @return array<string, string> */
    private function integer(): array
    {
        return ['type' => 'integer'];
    }

    /** @return array<string, string> */
    private function number(): array
    {
        return ['type' => 'number'];
    }

    /** @return array<string, mixed> */
    private function stringList(): array
    {
        return [
            'type' => 'array',
            'items' => $this->string(),
        ];
    }

    /** @return array<string, mixed> */
    private function objectList(array $properties): array
    {
        return [
            'type' => 'array',
            'items' => $this->object($properties),
        ];
    }
}
