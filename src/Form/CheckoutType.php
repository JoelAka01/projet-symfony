<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cardholder', TextType::class, [
                'label' => 'Name on card',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 120),
                ],
            ])
            ->add('cardNumber', TextType::class, [
                'label' => 'Card number',
                'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'cc-number'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(
                        pattern: '/^[\d ]{12,23}$/',
                        message: 'Enter a valid card number.',
                    ),
                ],
            ])
            ->add('expiry', TextType::class, [
                'label' => 'Expiry',
                'attr' => ['placeholder' => 'MM/YY', 'autocomplete' => 'cc-exp'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^\d{2}\/\d{2}$/', message: 'Use MM/YY.'),
                ],
            ])
            ->add('cvc', TextType::class, [
                'label' => 'CVC',
                'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'cc-csc'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^\d{3,4}$/', message: 'Enter 3 or 4 digits.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'simulated_checkout']);
    }
}
