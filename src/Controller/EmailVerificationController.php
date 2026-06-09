<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\VerificationEmailRequestFormType;
use App\Repository\UserRepository;
use App\Security\AccountEmailService;
use App\Security\AccountTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email/{id}/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(
        string $id,
        string $token,
        UserRepository $userRepository,
        AccountTokenService $tokenService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->find($id);

        if (null === $user) {
            $this->addFlash('error', 'The verification link is invalid.');

            return $this->redirectToRoute('app_verify_email_resend');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'This account is already verified. You can log in.');

            return $this->redirectToRoute('app_login');
        }

        if (!$tokenService->isEmailVerificationTokenValid($user, $token)) {
            $this->addFlash('error', 'The verification link is invalid or has expired. Request a new one.');

            return $this->redirectToRoute('app_verify_email_resend');
        }

        $user
            ->setIsVerified(true)
            ->clearEmailVerificationToken()
            ->touch();

        $entityManager->flush();

        $this->addFlash('success', 'Your email address has been verified. You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify-email/resend', name: 'app_verify_email_resend', methods: ['GET', 'POST'])]
    public function resend(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        AccountTokenService $tokenService,
        AccountEmailService $accountEmailService,
        LoggerInterface $logger,
    ): Response {
        $form = $this->createForm(VerificationEmailRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email?: string} $data */
            $data = $form->getData();
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $user = $userRepository->findOneBy(['email' => $email]);

            if (null === $user) {
                $this->addFlash('success', 'If an unverified account exists for that email, a new verification link has been sent.');

                return $this->redirectToRoute('app_login');
            }

            if ($user->isVerified()) {
                $this->addFlash('info', 'This account is already verified. You can log in.');

                return $this->redirectToRoute('app_login');
            }

            $verificationToken = $tokenService->issueEmailVerificationToken($user);
            $entityManager->flush();

            try {
                $accountEmailService->sendVerificationEmail($user, $verificationToken);
                $this->addFlash('success', 'If an unverified account exists for that email, a new verification link has been sent.');

                return $this->redirectToRoute('app_login');
            } catch (TransportExceptionInterface $exception) {
                $logger->error('Verification email could not be resent.', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'exception' => $exception,
                ]);
                $this->addFlash('error', 'The verification email could not be sent. Check the SMTP configuration and try again.');
            }
        }

        return $this->render('registration/resend_verification.html.twig', [
            'resendForm' => $form,
        ]);
    }
}
