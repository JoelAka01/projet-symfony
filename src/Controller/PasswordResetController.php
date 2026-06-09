<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\PasswordResetRequestFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use App\Security\AccountEmailService;
use App\Security\AccountTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PasswordResetController extends AbstractController
{
    #[Route('/password-reset', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        AccountTokenService $tokenService,
        AccountEmailService $accountEmailService,
        LoggerInterface $logger,
    ): Response {
        $form = $this->createForm(PasswordResetRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email?: string} $data */
            $data = $form->getData();
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $user = $userRepository->findOneBy(['email' => $email]);

            if (null !== $user) {
                $resetToken = $tokenService->issuePasswordResetToken($user);
                $entityManager->flush();

                try {
                    $accountEmailService->sendPasswordResetEmail($user, $resetToken);
                } catch (TransportExceptionInterface $exception) {
                    $logger->error('Password reset email could not be sent.', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'exception' => $exception,
                    ]);
                    $this->addFlash('error', 'The password reset email could not be sent. Check the SMTP configuration and try again.');

                    return $this->redirectToRoute('app_forgot_password_request');
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/password-reset/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/password-reset/{id}/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $id,
        string $token,
        Request $request,
        UserRepository $userRepository,
        AccountTokenService $tokenService,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->find($id);

        if (null === $user || !$tokenService->isPasswordResetTokenValid($user, $token)) {
            $this->addFlash('error', 'The password reset link is invalid or has expired. Request a new one.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $user
                ->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword))
                ->clearPasswordResetToken()
                ->touch();

            $entityManager->flush();

            $this->addFlash('success', 'Your password has been updated. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }
}
