<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\SupportedCountry;
use App\Enum\SupportedLanguage;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Project name'])
            ->add('websiteUrl', TextType::class, [
                'label' => 'Website URL',
                'mapped' => false,
                'data' => $options['website_url'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Enter the website URL for this project.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('owner', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn(User $user): string => sprintf('%s (%s)', $user->getDisplayName(), $user->getEmail()),
                'query_builder' => static fn(EntityRepository $repository) => $repository->createQueryBuilder('user')->orderBy('user.email', 'ASC'),
                'placeholder' => 'Select an owner',
                'required' => true,
            ])
            ->add('organization', EntityType::class, [
                'class' => Organization::class,
                'choice_label' => 'name',
                'query_builder' => static fn(EntityRepository $repository) => $repository->createQueryBuilder('organization')->orderBy('organization.name', 'ASC'),
                'placeholder' => 'Select a workspace',
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Active' => ProjectStatus::ACTIVE,
                    'Paused' => ProjectStatus::PAUSED,
                ],
                'choice_value' => static fn(?ProjectStatus $status): string => null === $status ? '' : $status->value,
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'Project language',
                'choices' => SupportedLanguage::choices(),
                'required' => false,
                'placeholder' => '— Not set —',
            ])
            ->add('targetCountry', ChoiceType::class, [
                'label' => 'Target country',
                'choices' => SupportedCountry::choices(),
                'required' => false,
                'placeholder' => '— Not set —',
            ])
            ->add('contentLanguage', ChoiceType::class, [
                'label' => 'Content language',
                'choices' => SupportedLanguage::choices(),
                'required' => false,
                'placeholder' => '— Same as project language —',
            ])
            ->add('autoDetectLanguage', CheckboxType::class, [
                'label' => 'Auto-detect language',
                'required' => false,
            ])
            ->add('languageConfidence', IntegerType::class, [
                'label' => 'Language confidence (%)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'website_url' => null,
        ]);
        $resolver->setAllowedTypes('website_url', ['null', 'string']);
    }
}
