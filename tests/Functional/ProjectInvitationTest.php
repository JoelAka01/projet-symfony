<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\ProjectInvitation;
use App\Entity\User;
use App\Repository\ProjectInvitationRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectInvitationTest extends WebTestCase
{
    public function testInviteGuestFlow(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $projectRepository = $container->get(ProjectRepository::class);
        $invitationRepository = $container->get(ProjectInvitationRepository::class);

        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Cleanup existing test guest user and invitations to ensure test isolation
        $existingGuest = $userRepository->findOneBy(['email' => 'invited-guest@example.com']);
        if ($existingGuest) {
            $project = $projectRepository->findOneBy(['name' => 'Portfolio Personnel']);
            if ($project) {
                $project->removeGuest($existingGuest);
            }
            $entityManager->remove($existingGuest);
        }

        $existingInvites = $invitationRepository->findBy(['email' => 'invited-guest@example.com']);
        foreach ($existingInvites as $invite) {
            $entityManager->remove($invite);
        }
        $entityManager->flush();

        // 1. Log in as owner of 'Portfolio Personnel' (user@example.com)
        $owner = $userRepository->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(User::class, $owner);
        $client->loginUser($owner);

        $project = $projectRepository->findOneBy(['name' => 'Portfolio Personnel']);
        self::assertInstanceOf(Project::class, $project);

        // 2. View project page
        $crawler = $client->request('GET', '/projects/' . $project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Project Guests & Invitations');

        // Click "Add members" link/button
        $crawler = $client->clickLink('Add members');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.panel.full-panel h2', 'Project Guests & Invitations');

        // 3. Invite a guest
        $form = $crawler->selectButton('Send Invitation')->form([
            'project_invitation[email]' => 'invited-guest@example.com',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/projects/' . $project->getId() . '/guests');
        $client->followRedirect();

        // 4. Verify invitation is created
        $invitation = $invitationRepository->findOneBy([
            'project' => $project,
            'email' => 'invited-guest@example.com',
            'status' => 'pending',
        ]);
        self::assertInstanceOf(ProjectInvitation::class, $invitation);

        // 5. Access read-only guest page as anonymous user
        $client->getCookieJar()->clear(); // Log out
        $token = $invitation->getToken();
        $crawler = $client->request('GET', '/projects/invitations/view/' . $token);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Read-only access:');
        self::assertSelectorTextContains('h1', 'Portfolio Personnel');

        // 5.1 Create a test audit for the project and access its guest detail view
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $project = $entityManager->getRepository(Project::class)->findOneBy(['name' => 'Portfolio Personnel']);
        self::assertInstanceOf(Project::class, $project);
        $domain = $project->getDomains()->first();
        self::assertInstanceOf(\App\Entity\Domain::class, $domain);

        $audit = (new \App\Entity\Audit())
            ->setProject($project)
            ->setDomain($domain)
            ->setStatus(\App\Enum\AuditStatus::COMPLETED)
            ->setSeoScore(85)
            ->setPagesCrawled(5)
            ->setPagesFailed(0)
            ->setMetadata([
                'ai_analysis' => [
                    'status' => 'completed',
                    'summary' => 'This is a test audit summary.',
                    'global_score' => 80,
                    'content_score' => 75,
                    'geo_score' => 70,
                    'confidence' => 0.85,
                    'search_intent' => 'informational',
                    'citation_potential' => 'high',
                    'score_rationale' => 'Good structure',
                    'onpage_score' => 80,
                    'ux_score' => 85,
                    'target_audience' => 'developers',
                    'recommendations' => [],
                    'geo_analysis' => [
                        'methodology_notice' => 'Test notice',
                        'ai_brand_visibility' => [],
                        'ai_seo_optimizations' => [],
                    ],
                ],
            ]);

        $entityManager->persist($audit);
        $entityManager->flush();

        // 5.2 Access guest audit details page as anonymous user
        $crawler = $client->request('GET', '/projects/invitations/view/' . $token . '/audits/' . $audit->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Read-only access:');
        self::assertSelectorTextContains('h1', 'Portfolio Personnel');
        self::assertSelectorTextContains('body', 'This is a test audit summary.');

        // 6. Access accepting page without authentication -> redirects to login
        $client->request('GET', '/projects/invitations/accept/' . $token);
        self::assertResponseRedirects('/login');

        // 7. Accept invitation with mismatching email -> error page
        $anotherUser = $userRepository->findOneBy(['email' => 'manager@example.com']);
        self::assertInstanceOf(User::class, $anotherUser);
        $client->loginUser($anotherUser);
        $crawler = $client->request('GET', '/projects/invitations/accept/' . $token);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Email Mismatch Error');

        // 8. Accept invitation with matching email
        $client->getCookieJar()->clear(); // Log out

        // We need the invited guest user to exist to log in. Let's create one dynamically or use an existing one.
        // Actually, we can create the invited guest user in DB first.
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $guestUser = new User();
        $guestUser
            ->setEmail('invited-guest@example.com')
            ->setFirstName('Invited')
            ->setLastName('Guest')
            ->setIsVerified(true)
            ->setPasswordHash('hash');
        $entityManager->persist($guestUser);
        $entityManager->flush();

        $client->loginUser($guestUser);
        $crawler = $client->request('GET', '/projects/invitations/accept/' . $token);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Accept Invitation');

        // Submit accept form
        $form = $crawler->selectButton('Accept Invitation')->form();
        $client->submit($form);

        self::assertResponseRedirects('/projects/' . $project->getId());
        $client->followRedirect();

        // 9. Verify guest relationship is established
        $projectRepository = self::getContainer()->get(ProjectRepository::class);
        $project = $projectRepository->find($project->getId());
        self::assertInstanceOf(Project::class, $project);

        $guestUserLoaded = self::getContainer()->get(UserRepository::class)->find($guestUser->getId());
        self::assertInstanceOf(User::class, $guestUserLoaded);
        self::assertTrue($project->getGuests()->contains($guestUserLoaded));

        // 10. Verify invitation status updated to accepted
        $invitationRepository = self::getContainer()->get(ProjectInvitationRepository::class);
        $invitation = $invitationRepository->find($invitation->getId());
        self::assertInstanceOf(ProjectInvitation::class, $invitation);
        self::assertSame('accepted', $invitation->getStatus());
    }
}
