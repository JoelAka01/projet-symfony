<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionPlan;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class BillingEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(param: 'app.mailer_from_email')]
        private readonly string $fromEmail,
        #[Autowire(param: 'app.mailer_from_name')]
        private readonly string $fromName,
        #[Autowire(param: 'app.admin_email')]
        private readonly string $adminEmail,
    ) {}

    public function sendSubscriptionActivatedEmail(User $user, Subscription $subscription): void
    {
        // 1. send to user
        $userEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Your SEO GEO AI subscription is active!')
            ->htmlTemplate('emails/subscription_activated.html.twig')
            ->context([
                'user' => $user,
                'subscription' => $subscription,
            ]);
        $this->mailer->send($userEmail);

        // 2. send to admin
        $adminEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($this->adminEmail, 'SEO GEO AI Admin'))
            ->subject(sprintf('[Admin] New subscription: %s by %s', $subscription->getPlan()->value, $user->getEmail()))
            ->htmlTemplate('emails/admin_subscription_activated.html.twig')
            ->context([
                'user' => $user,
                'subscription' => $subscription,
            ]);
        $this->mailer->send($adminEmail);
    }

    public function sendSubscriptionChangedEmail(User $user, Subscription $subscription, SubscriptionPlan $oldPlan): void
    {
        // 1. send to user
        $userEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Your SEO GEO AI subscription has changed')
            ->htmlTemplate('emails/subscription_changed.html.twig')
            ->context([
                'user' => $user,
                'subscription' => $subscription,
                'oldPlan' => $oldPlan,
            ]);
        $this->mailer->send($userEmail);

        // 2. send to admin
        $adminEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($this->adminEmail, 'SEO GEO AI Admin'))
            ->subject(sprintf('[Admin] Subscription changed: %s to %s by %s', $oldPlan->value, $subscription->getPlan()->value, $user->getEmail()))
            ->htmlTemplate('emails/admin_subscription_changed.html.twig')
            ->context([
                'user' => $user,
                'subscription' => $subscription,
                'oldPlan' => $oldPlan,
            ]);
        $this->mailer->send($adminEmail);
    }

    public function sendSubscriptionCanceledEmail(User $user, Subscription $subscription): void
    {
        // 1. send to user
        $userEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Your SEO GEO AI subscription has been canceled')
            ->htmlTemplate('emails/subscription_canceled.html.twig')
            ->context([
                'user' => $user,
                'subscription' => $subscription,
            ]);
        $this->mailer->send($userEmail);

        // 2. send to admin
        $adminEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($this->adminEmail, 'SEO GEO AI Admin'))
            ->subject(sprintf('[Admin] Subscription canceled: %s by %s', $subscription->getPlan()->value, $user->getEmail()))
            ->htmlTemplate('emails/admin_subscription_canceled.html.twig')
            ->context([
                'user' => $user,
                'subscription' => $subscription,
            ]);
        $this->mailer->send($adminEmail);
    }

    public function sendPaymentReceiptEmail(User $user, Payment $payment): void
    {
        // 1. send to user
        $userEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Payment Receipt: SEO GEO AI subscription')
            ->htmlTemplate('emails/payment_receipt.html.twig')
            ->context([
                'user' => $user,
                'payment' => $payment,
            ]);
        $this->mailer->send($userEmail);

        // 2. send to admin
        $adminEmail = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($this->adminEmail, 'SEO GEO AI Admin'))
            ->subject(sprintf('[Admin] Payment received: €%s from %s', number_format($payment->getAmountCents() / 100, 2), $user->getEmail()))
            ->htmlTemplate('emails/admin_payment_receipt.html.twig')
            ->context([
                'user' => $user,
                'payment' => $payment,
            ]);
        $this->mailer->send($adminEmail);
    }
}
