<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Entity\Article;
use App\Entity\ArticleImage;

final class CmsContentRenderer
{
    /**
     * @param array<int, string>|null $replacementUrls
     */
    public function render(Article $article, ?array $replacementUrls = null): string
    {
        $content = trim((string) $article->getContentHtml());
        $figures = [];

        foreach ($article->getImages() as $index => $image) {
            $url = $replacementUrls[$index] ?? $image->getStorageUrl();
            if ('' === trim($url) || str_contains($content, $url)) {
                continue;
            }

            $figures[] = $this->figure($image, $url);
        }

        return trim($content . "\n" . implode("\n", $figures));
    }

    private function figure(ArticleImage $image, string $url): string
    {
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedAlt = htmlspecialchars((string) $image->getAltText(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<figure><img src="%s" alt="%s" loading="lazy"></figure>', $escapedUrl, $escapedAlt);
    }
}
