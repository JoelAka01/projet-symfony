<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TopicResearch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TopicResearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('primaryKeyword', TextType::class, [
                'label' => 'Primary keyword',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 3, max: 200),
                ],
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'Target country',
                'choices' => [
                    'France' => 'FR',
                    'United States' => 'US',
                    'United Kingdom' => 'GB',
                    'Canada' => 'CA',
                    'Germany' => 'DE',
                    'Spain' => 'ES',
                    'Italy' => 'IT',
                ],
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'Language',
                'choices' => [
                    'French' => 'fr',
                    'English' => 'en',
                    'German' => 'de',
                    'Spanish' => 'es',
                    'Italian' => 'it',
                ],
            ])
            ->add('sector', TextType::class, [
                'label' => 'Sector',
                'required' => false,
                'constraints' => [new Assert\Length(max: 180)],
            ])
            ->add('audience', TextareaType::class, [
                'label' => 'Audience',
                'required' => false,
                'attr' => ['rows' => 4],
                'constraints' => [new Assert\Length(max: 4000)],
            ])
            ->add('businessObjective', TextareaType::class, [
                'label' => 'Business objective',
                'required' => false,
                'attr' => ['rows' => 4],
                'constraints' => [new Assert\Length(max: 4000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TopicResearch::class,
            'csrf_token_id' => 'topic_research',
        ]);
    }
}
