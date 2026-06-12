<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\RegistrationFormType;
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

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        AccountTokenService $tokenService,
        AccountEmailService $accountEmailService,
        LoggerInterface $logger,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_project_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $user
                ->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword))
                ->setRole(UserRole::VIEWER)
                ->setIsVerified(false);

            $verificationToken = $tokenService->issueEmailVerificationToken($user);

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $accountEmailService->sendVerificationEmail($user, $verificationToken);
                $this->addFlash('success', 'Account created. Check your email to verify your account before logging in.');
            } catch (TransportExceptionInterface $exception) {
                $logger->error('Registration verification email could not be sent.', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'exception' => $exception,
                ]);
                $this->addFlash('error', 'Account created, but the verification email could not be sent. Check the SMTP configuration or request a new verification email.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
