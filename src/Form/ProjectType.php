<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Enum\SupportedCountry;
use App\Enum\SupportedLanguage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
            ->add('language', ChoiceType::class, [
                'label' => 'Project language',
                'choices' => SupportedLanguage::choices(),
                'required' => true,
                'placeholder' => '— Select language —',
                'help' => 'The language of the analysed website. All SEO analysis will use this language.',
            ])
            ->add('targetCountry', ChoiceType::class, [
                'label' => 'Target country',
                'choices' => SupportedCountry::choices(),
                'required' => true,
                'placeholder' => '— Select country —',
                'help' => 'The primary market for this project. Used for local SERP and search volumes.',
            ])
            ->add('contentLanguage', ChoiceType::class, [
                'label' => 'Content language',
                'choices' => SupportedLanguage::choices(),
                'required' => false,
                'placeholder' => '— Same as project language —',
                'help' => 'Override the language used for content generation. Leave empty to use the project language.',
            ])
            ->add('autoDetectLanguage', CheckboxType::class, [
                'label' => 'Auto-detect language from website',
                'required' => false,
                'help' => 'When enabled, the language and country will be automatically detected from the website URL.',
            ]);

        if (true === $options['include_status']) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => ProjectStatus::ACTIVE,
                    'Paused' => ProjectStatus::PAUSED,
                ],
                'choice_value' => static fn(?ProjectStatus $status): string => null === $status ? '' : $status->value,
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
