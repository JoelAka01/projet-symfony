<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'Both passwords must match.',
            'first_options' => [
                'label' => 'New password',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ],
            'second_options' => [
                'label' => 'Confirm new password',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ],
            'constraints' => [
                new Assert\NotBlank(message: 'Choose a new password.'),
                new Assert\Length(
                    min: 10,
                    max: 4096,
                    minMessage: 'Your password should be at least {{ limit }} characters.',
                ),
                new Assert\Regex(
                    pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/',
                    message: 'Use at least one letter and one number.',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'reset_password',
        ]);
    }
}
