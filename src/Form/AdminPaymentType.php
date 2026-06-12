<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Paid' => PaymentStatus::PAID,
                    'Refunded' => PaymentStatus::REFUNDED,
                    'Canceled' => PaymentStatus::CANCELED,
                ],
                'choice_value' => static fn(?PaymentStatus $status): string => null === $status ? '' : $status->value,
            ])
            ->add('adminNote', TextareaType::class, [
                'required' => false,
                'label' => 'Admin note',
                'constraints' => [new Assert\Length(max: 2000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Payment::class]);
    }
}
