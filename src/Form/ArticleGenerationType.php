<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ArticleGenerationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('brief', TextareaType::class, [
                'label' => 'Writing brief',
                'attr' => ['rows' => 5],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 20, max: 4000),
                ],
            ])
            ->add('tone', ChoiceType::class, [
                'choices' => [
                    'Expert and clear' => 'expert_clear',
                    'Friendly and practical' => 'friendly_practical',
                    'Professional and concise' => 'professional_concise',
                    'Educational for beginners' => 'educational_beginner',
                ],
            ])
            ->add('targetWordCount', IntegerType::class, [
                'label' => 'Target word count',
                'data' => 1400,
                'constraints' => [new Assert\Range(min: 500, max: 4000)],
            ])
            ->add('includeFaq', CheckboxType::class, [
                'label' => 'Include an FAQ section',
                'required' => false,
                'data' => true,
            ]);
    }
}
