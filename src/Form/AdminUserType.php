<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [
            new Assert\Length(
                min: 10,
                max: 4096,
                minMessage: 'The password should be at least {{ limit }} characters.',
            ),
            new Assert\Regex(
                pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/',
                message: 'Use at least one letter and one number.',
            ),
        ];

        if (true === $options['password_required']) {
            array_unshift($passwordConstraints, new Assert\NotBlank(message: 'Choose a password.'));
        }

        $builder
            ->add('firstName', TextType::class, ['label' => 'First name'])
            ->add('lastName', TextType::class, ['label' => 'Last name'])
            ->add('email', EmailType::class, ['label' => 'Email address'])
            ->add('role', ChoiceType::class, [
                'label' => 'Platform role',
                'choices' => [
                    'Administrator (ROLE_ADMIN)' => UserRole::ADMIN,
                    'Manager / owner (ROLE_MANAGER)' => UserRole::OWNER,
                    'Manager / editor (ROLE_MANAGER)' => UserRole::EDITOR,
                    'User / viewer (ROLE_USER)' => UserRole::VIEWER,
                ],
                'choice_value' => static fn(?UserRole $role): string => null === $role ? '' : $role->value,
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'Interface language',
                'choices' => [
                    'Français' => 'fr',
                    'English' => 'en',
                ],
                'required' => true,
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Email verified',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $options['password_required'],
                'invalid_message' => 'Both passwords must match.',
                'first_options' => [
                    'label' => $options['password_required'] ? 'Password' : 'New password (leave blank to keep current)',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => $passwordConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => false,
        ]);
        $resolver->setAllowedTypes('password_required', 'bool');
    }
}
