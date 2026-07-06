<?php

declare(strict_types=1);

namespace App\Service\Language;

/**
 * Centralised service that injects mandatory language instructions into prompts
 * sent to external providers (Claude, etc.).
 *
 * Every prompt that generates user-facing SEO content must pass through this
 * service to guarantee the output language matches Project.contentLanguage.
 */
final class LanguagePromptInjector
{
    /** @var array<string, string> ISO 639-1 → English name */
    private const LANGUAGE_NAMES = [
        'fr' => 'French',
        'en' => 'English',
        'es' => 'Spanish',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'zh' => 'Chinese',
        'ru' => 'Russian',
        'pl' => 'Polish',
        'sv' => 'Swedish',
        'no' => 'Norwegian',
        'da' => 'Danish',
        'fi' => 'Finnish',
        'ar' => 'Arabic',
        'tr' => 'Turkish',
    ];

    /**
     * Returns the full language name for a given ISO 639-1 code.
     */
    public function languageName(string $code): string
    {
        $code = strtolower(trim($code));

        return self::LANGUAGE_NAMES[$code] ?? ucfirst($code);
    }

    /**
     * Builds the mandatory language instruction block.
     *
     * This block must be appended to every system prompt that generates
     * user-facing content (audits, keywords, articles, FAQs, briefs, etc.).
     */
    public function buildInstruction(string $languageCode): string
    {
        $name = $this->languageName($languageCode);

        return <<<INSTRUCTION

LANGUAGE INSTRUCTION (mandatory — overrides all other instructions):
The entire response MUST be written in: {$name} ({$languageCode}).
All text fields, labels, recommendations, questions, keywords, titles,
descriptions, anchors, cluster names, FAQ questions and answers, SEO titles,
meta descriptions, and any other generated text must be in {$name}.
Do not answer in any other language.
INSTRUCTION;
    }

    /**
     * Appends the language instruction to a system prompt.
     */
    public function appendToPrompt(string $systemPrompt, string $languageCode): string
    {
        return $systemPrompt . $this->buildInstruction($languageCode);
    }
}
