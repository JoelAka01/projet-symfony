<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class ClaudeSeoAnalysisSchema
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $modelVisibility = $this->object([
            'status' => $this->string(),
            'how_mentioned' => $this->string(),
            'sentiment' => $this->string(),
            'confidence' => $this->number(),
            'evidence' => $this->stringList(),
        ]);

        return $this->object([
            'global_score' => $this->integer(),
            'technical_score' => $this->integer(),
            'content_score' => $this->integer(),
            'onpage_score' => $this->integer(),
            'geo_score' => $this->integer(),
            'ux_score' => $this->integer(),
            'confidence' => $this->number(),
            'summary' => $this->string(),
            'score_rationale' => $this->string(),
            'executive_summary' => $this->object([
                'one_liner' => $this->string(),
                'situation' => $this->string(),
                'top_3_blockers' => $this->stringList(),
                'top_3_quick_wins' => $this->stringList(),
                'estimated_traffic_potential' => $this->string(),
                'competitive_difficulty' => $this->string(),
                'verdict' => $this->string(),
            ]),
            'audience_and_intent' => $this->object([
                'primary_audience' => $this->string(),
                'secondary_audience' => $this->string(),
                'search_intent' => $this->string(),
                'intent_detail' => $this->string(),
                'buyer_journey_stage' => $this->string(),
                'personas' => $this->stringList(),
            ]),
            'search_intent' => $this->string(),
            'target_audience' => $this->string(),
            'strengths' => $this->stringList(),
            'weaknesses' => $this->stringList(),
            'keyword_analysis' => $this->object([
                'primary_topic' => $this->string(),
                'detected_target_keywords' => $this->objectList([
                    'keyword' => $this->string(),
                    'occurrences' => $this->integer(),
                    'placement' => $this->stringList(),
                    'intent' => $this->string(),
                    'density_assessment' => $this->string(),
                    'evidence' => $this->string(),
                    'recommendation' => $this->string(),
                ]),
                'missing_semantic_keywords' => $this->stringList(),
                'url_keyword_assessment' => $this->string(),
                'long_tail_opportunities' => $this->stringList(),
                'topic_clusters' => $this->objectList([
                    'pillar_topic' => $this->string(),
                    'supporting_topics' => $this->stringList(),
                    'gap' => $this->string(),
                ]),
            ]),
            'technical_seo' => $this->object([
                'indexability' => $this->object([
                    'status' => $this->string(),
                    'canonical_analysis' => $this->string(),
                    'issues' => $this->stringList(),
                ]),
                'crawlability' => $this->object([
                    'crawl_depth_assessment' => $this->string(),
                    'issues' => $this->stringList(),
                ]),
                'page_speed_signals' => $this->object([
                    'estimated_lcp_risk' => $this->string(),
                    'estimated_cls_risk' => $this->string(),
                    'core_web_vitals_risks' => $this->stringList(),
                ]),
                'mobile_seo' => $this->object([
                    'responsive_signals' => $this->string(),
                    'mobile_issues' => $this->stringList(),
                ]),
            ]),
            'on_page_seo' => $this->object([
                'title_tag' => $this->object([
                    'sitewide_assessment' => $this->string(),
                    'suggested_pattern' => $this->string(),
                ]),
                'meta_description' => $this->object([
                    'sitewide_assessment' => $this->string(),
                    'suggested_pattern' => $this->string(),
                ]),
                'heading_structure' => $this->object([
                    'sitewide_assessment' => $this->string(),
                    'issues' => $this->stringList(),
                    'recommended_h1_pattern' => $this->string(),
                ]),
                'content_analysis' => $this->object([
                    'word_count_assessment' => $this->string(),
                    'min_recommended_words' => $this->integer(),
                    'content_structure' => $this->string(),
                    'missing_content_sections' => $this->stringList(),
                ]),
            ]),
            'content_strategy' => $this->object([
                'strengths' => $this->stringList(),
                'weaknesses' => $this->stringList(),
                'e_e_a_t_signals' => $this->object([
                    'eeat_score' => $this->string(),
                    'improvements' => $this->stringList(),
                ]),
                'featured_snippet_opportunities' => $this->objectList([
                    'query' => $this->string(),
                    'snippet_type' => $this->string(),
                    'current_gap' => $this->string(),
                    'action' => $this->string(),
                ]),
                'faq_suggestions' => $this->objectList([
                    'question' => $this->string(),
                    'answer' => $this->string(),
                ]),
            ]),
            'geo_analysis' => $this->object([
                'geo_score' => $this->integer(),
                'ai_citation_potential' => $this->string(),
                'citation_rationale' => $this->string(),
                'conversational_query_alignment' => $this->string(),
                'methodology_notice' => $this->string(),
                'ai_brand_visibility' => $this->object([
                    'chatgpt' => $modelVisibility,
                    'gemini' => $modelVisibility,
                    'perplexity' => $modelVisibility,
                ]),
                'ai_seo_optimizations' => $this->objectList([
                    'target_ai' => $this->string(),
                    'current_gap' => $this->string(),
                    'correction_action' => $this->string(),
                ]),
                'structured_answer_blocks' => $this->objectList([
                    'question' => $this->string(),
                    'answer' => $this->string(),
                ]),
                'geo_improvements' => $this->objectList([
                    'issue' => $this->string(),
                    'action' => $this->string(),
                    'target_ai' => $this->string(),
                ]),
                'entity_coverage' => $this->object([
                    'entities_detected' => $this->stringList(),
                    'missing_entities' => $this->stringList(),
                    'entity_linking_opportunities' => $this->stringList(),
                ]),
                'answer_engine_readiness' => $this->string(),
            ]),
            'serp_features' => $this->object([
                'currently_eligible' => $this->stringList(),
                'optimization_needed' => $this->objectList([
                    'feature' => $this->string(),
                    'current_gap' => $this->string(),
                    'action' => $this->string(),
                ]),
            ]),
            'recommendations' => $this->objectList([
                'id' => $this->string(),
                'priority' => $this->string(),
                'category' => $this->string(),
                'title' => $this->string(),
                'problem' => $this->string(),
                'evidence' => $this->string(),
                'why_it_matters' => $this->string(),
                'action' => $this->string(),
                'before_example' => $this->string(),
                'after_example' => $this->string(),
                'expected_impact' => $this->string(),
                'effort' => $this->string(),
                'time_estimate' => $this->string(),
            ]),
            'suggested_title' => $this->string(),
            'suggested_meta_description' => $this->string(),
            'faq_suggestions' => $this->objectList([
                'question' => $this->string(),
                'answer' => $this->string(),
            ]),
            'entities' => $this->stringList(),
            'citation_potential' => $this->string(),
            'content_opportunities' => $this->stringList(),
            'technical_risks' => $this->stringList(),
            'short_answer_blocks' => $this->objectList([
                'question' => $this->string(),
                'answer' => $this->string(),
            ]),
            'priority_matrix' => $this->object([
                'critical_do_now' => $this->stringList(),
                'high_this_week' => $this->stringList(),
                'medium_this_month' => $this->stringList(),
                'low_backlog' => $this->stringList(),
            ]),
            'action_plan_30_60_90' => $this->object([
                'day_30' => $this->stringList(),
                'day_60' => $this->stringList(),
                'day_90' => $this->stringList(),
            ]),
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
