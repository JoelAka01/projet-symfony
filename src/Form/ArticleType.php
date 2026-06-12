<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Article;
use App\Entity\Keyword;
use App\Entity\Project;
use App\Repository\KeywordRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $options['project'];
        $queryBuilder = static fn(KeywordRepository $repository): QueryBuilder => $repository
            ->createQueryBuilder('keyword')
            ->andWhere('keyword.project = :project')
            ->setParameter('project', $project)
            ->orderBy('keyword.term', 'ASC');

        $builder
            ->add('title', TextType::class, [
                'label' => 'Visible article title',
            ])
            ->add('seoTitle', TextType::class, [
                'required' => false,
                'label' => 'SEO title',
                'help' => 'Keep this around 50-60 characters. CMS APIs without a separate SEO title use it as the article title.',
            ])
            ->add('seoDescription', TextareaType::class, [
                'required' => false,
                'label' => 'SEO description',
                'help' => 'Aim for a useful 140-160 character search snippet.',
                'attr' => ['rows' => 3],
            ])
            ->add('excerpt', TextareaType::class, [
                'required' => false,
                'label' => 'Article excerpt',
                'attr' => ['rows' => 3],
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'help' => 'Lowercase URL handle, for example complete-seo-audit-guide.',
            ])
            ->add('primaryKeyword', EntityType::class, [
                'class' => Keyword::class,
                'choice_label' => 'term',
                'query_builder' => $queryBuilder,
                'required' => false,
                'placeholder' => 'No primary keyword',
            ])
            ->add('targetKeywords', EntityType::class, [
                'class' => Keyword::class,
                'choice_label' => 'term',
                'query_builder' => $queryBuilder,
                'required' => false,
                'multiple' => true,
            ])
            ->add('contentHtml', TextareaType::class, [
                'required' => false,
                'label' => 'Article HTML',
                'help' => 'Use semantic HTML without an H1. Unsafe scripts and event attributes are removed before AI-generated content is stored.',
                'attr' => ['rows' => 24],
            ])
            ->add('featuredImageUrl', UrlType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Featured image URL',
                'data' => $options['featured_image_url'],
                'help' => 'A real public image URL. WordPress imports it into Media; Shopify uses it as the article image.',
            ])
            ->add('featuredImageAlt', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Featured image alt text',
                'data' => $options['featured_image_alt'],
            ])
            ->add('removeFeaturedImage', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Remove the attached featured image',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
            'featured_image_url' => null,
            'featured_image_alt' => null,
        ]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
        $resolver->setAllowedTypes('featured_image_url', ['null', 'string']);
        $resolver->setAllowedTypes('featured_image_alt', ['null', 'string']);
    }
}
