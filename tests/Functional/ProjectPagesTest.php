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
}
