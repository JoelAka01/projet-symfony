<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Service\Content\ArticleHtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class ArticleHtmlSanitizerTest extends TestCase
{
    public function testItKeepsSemanticContentAndRemovesExecutableMarkup(): void
    {
        $sanitizer = new ArticleHtmlSanitizer();

        $html = $sanitizer->sanitize(
            '<script>alert(1)</script><h2 onclick="alert(2)">SEO title</h2><p>Useful text.</p><a href="javascript:alert(3)">Bad link</a>',
        );

        self::assertStringNotContainsString('script', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<h2>SEO title</h2>', $html);
        self::assertStringContainsString('<p>Useful text.</p>', $html);
    }
}
