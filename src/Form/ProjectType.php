<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project name',
                'attr' => [
                    'autocomplete' => 'organization',
                    'placeholder' => 'Acme website SEO',
                ],
            ])
            ->add('websiteUrl', TextType::class, [
                'label' => 'Website URL',
                'mapped' => false,
                'data' => $options['website_url'],
                'attr' => [
                    'autocomplete' => 'url',
                    'placeholder' => 'https://example.com/',
                ],
                'help' => 'Used as the single root URL for crawls launched from this project.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Enter the website URL for this project.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('defaultLanguage', TextType::class, [
                'label' => 'Default language',
                'required' => false,
                'attr' => [
                    'placeholder' => 'en',
                    'maxlength' => 10,
                ],
            ])
            ->add('targetCountry', TextType::class, [
                'label' => 'Target country',
                'required' => false,
                'attr' => [
                    'placeholder' => 'US',
                    'maxlength' => 10,
                ],
            ]);

        if (true === $options['include_status']) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => ProjectStatus::ACTIVE,
                    'Paused' => ProjectStatus::PAUSED,
                    'Archived' => ProjectStatus::ARCHIVED,
                ],
                'choice_value' => static fn (?ProjectStatus $status): string => null === $status ? '' : $status->value,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'include_status' => false,
            'website_url' => null,
        ]);
        $resolver->setAllowedTypes('include_status', 'bool');
        $resolver->setAllowedTypes('website_url', ['null', 'string']);
    }
}
