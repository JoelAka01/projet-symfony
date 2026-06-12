<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CmsConnection;
use App\Entity\Project;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CmsPublishType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $options['project'];

        $builder
            ->add('connection', EntityType::class, [
                'class' => CmsConnection::class,
                'choice_label' => static fn(CmsConnection $connection): string => sprintf(
                    '%s - %s',
                    ucfirst(strtolower($connection->getProvider()->value)),
                    $connection->getBaseUrl(),
                ),
                'query_builder' => static fn(EntityRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('connection')
                    ->andWhere('connection.project = :project')
                    ->andWhere('connection.isActive = true')
                    ->setParameter('project', $project)
                    ->orderBy('connection.provider', 'ASC'),
                'placeholder' => 'Select a tested CMS connection',
            ])
            ->add('mode', ChoiceType::class, [
                'choices' => [
                    'Create or update remote draft' => 'draft',
                    'Publish live now' => 'publish',
                ],
                'help' => 'Publishing live immediately changes the connected website.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
        $resolver->setDefaults([
            'csrf_token_id' => 'publish_article',
        ]);
    }
}
