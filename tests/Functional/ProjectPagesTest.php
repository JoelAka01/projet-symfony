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

    public function testCannotCreateDuplicateProjectName(): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(\App\Repository\UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(\App\Entity\User::class, $user);

        $client->loginUser($user);

        $crawler = $client->request('GET', '/projects/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create project and analyze')->form([
            'project[name]' => 'Example public website',
            'project[websiteUrl]' => 'https://duplicate.com',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'You already have a project with this name.');
    }

    public function testCannotEditProjectToDuplicateName(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $userRepository = $container->get(\App\Repository\UserRepository::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(\App\Entity\User::class, $user);

        $projectManager = $container->get(\App\Service\Project\ProjectManager::class);
        $project2 = new \App\Entity\Project();
        $project2->setName('Another unique name ' . uniqid());
        $projectManager->createForUser($project2, $user, 'https://another.com');

        $client->loginUser($user);

        $crawler = $client->request('GET', '/projects/' . $project2->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form([
            'project[name]' => 'Example public website',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'You already have a project with this name.');
    }
}
