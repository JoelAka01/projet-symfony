<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserAccountFormType;
use App\Form\UserProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Separate forms to avoid validation overlap and submission conflicts
        $profileForm = $this->createForm(UserProfileFormType::class, $user);
        $accountForm = $this->createForm(UserAccountFormType::class, $user);

        // Determine which form was submitted based on POST parameters
        if ($request->isMethod('POST')) {
            if ($request->request->has('user_profile_form')) {
                $profileForm->handleRequest($request);
                if ($profileForm->isSubmitted() && $profileForm->isValid()) {
                    $user->touch();
                    $entityManager->flush();

                    $this->addFlash('success', 'Profile details updated successfully.');

                    return $this->redirectToRoute('app_settings');
                }
            } elseif ($request->request->has('user_account_form')) {
                $accountForm->handleRequest($request);
                if ($accountForm->isSubmitted() && $accountForm->isValid()) {
                    $plainPassword = $accountForm->get('plainPassword')->getData();
                    $hasPasswordError = false;

                    if (!empty($plainPassword)) {
                        $errors = $validator->validate($plainPassword, [
                            new Assert\Length(
                                min: 10,
                                max: 4096,
                                minMessage: 'Your password should be at least {{ limit }} characters.',
                            ),
                            new Assert\Regex(
                                pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/',
                                message: 'Use at least one letter and one number.',
                            ),
                        ]);

                        if (count($errors) > 0) {
                            $hasPasswordError = true;
                            foreach ($errors as $error) {
                                $accountForm->get('plainPassword')->get('first')->addError(new FormError($error->getMessage()));
                            }
                        } else {
                            $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));
                        }
                    }

                    if (!$hasPasswordError) {
                        $user->touch();
                        $entityManager->flush();

                        $this->addFlash('success', 'Account security settings updated successfully.');

                        return $this->redirectToRoute('app_settings');
                    }
                }
            }
        }

        return $this->render('settings/index.html.twig', [
            'profileForm' => $profileForm,
            'accountForm' => $accountForm,
        ]);
    }

    #[Route('/settings/delete', name: 'app_settings_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Invalid CSRF token for account deletion.');
        }

        $entityManager->remove($user);
        $entityManager->flush();

        // Log the user out and destroy the session
        $security->logout(false);

        $this->addFlash('success', 'Your account has been deleted permanently.');

        return $this->redirectToRoute('app_home');
    }
}
