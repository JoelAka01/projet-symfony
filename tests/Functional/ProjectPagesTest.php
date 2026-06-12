<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectPagesTest extends WebTestCase
{
    public function testProjectListRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/projects');

        self::assertResponseRedirects('/login');
    }

    public function testCmsConnectionsRequireAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/projects/00000000-0000-0000-0000-000000000000/cms');

        self::assertResponseRedirects('/login');
    }

    public function testContentStudioRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/projects/00000000-0000-0000-0000-000000000000/articles');

        self::assertResponseRedirects('/login');
    }
}
