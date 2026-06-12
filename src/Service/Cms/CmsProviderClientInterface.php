<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Dto\Cms\CmsConnectionTestResult;
use App\Dto\Cms\CmsPublishResult;
use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Entity\CmsPublication;
use App\Enum\CmsProvider;

interface CmsProviderClientInterface
{
    public function provider(): CmsProvider;

    public function testConnection(CmsConnection $connection): CmsConnectionTestResult;

    public function publishArticle(
        CmsConnection $connection,
        Article $article,
        ?CmsPublication $existingPublication,
        bool $publish,
    ): CmsPublishResult;
}
