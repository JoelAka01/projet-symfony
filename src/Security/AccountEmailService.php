<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.mailer_from_email')]
        private readonly string $fromEmail,
        #[Autowire(param: 'app.mailer_from_name')]
        private readonly string $fromName,
    ) {}

    public function sendVerificationEmail(User $user, string $token): void
    {
        $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
            'id' => $user->getId(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Verify your SEO GEO AI account')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiresIn' => '24 hours',
            ]);

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate('app_reset_password', [
            'id' => $user->getId(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Reset your SEO GEO AI password')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresIn' => '1 hour',
            ]);

        $this->mailer->send($email);
    }

    public function sendProjectInvitationEmail(\App\Entity\ProjectInvitation $invitation): void
    {
        $project = $invitation->getProject();
        if (null === $project) {
            return;
        }

        $invitationUrl = $this->urlGenerator->generate('app_project_invitation_view', [
            'token' => $invitation->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($invitation->getEmail()))
            ->subject(sprintf('Invitation to access project "%s" on SEO GEO AI', $project->getName()))
            ->htmlTemplate('emails/project_invitation.html.twig')
            ->context([
                'project' => $project,
                'invitationUrl' => $invitationUrl,
                'invitation' => $invitation,
            ]);

        $this->mailer->send($email);
    }
}
