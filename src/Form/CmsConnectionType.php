<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CmsConnection;
use App\Enum\CmsProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CmsConnectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', ChoiceType::class, [
                'choices' => [
                    'WordPress' => CmsProvider::WORDPRESS,
                    'Shopify' => CmsProvider::SHOPIFY,
                ],
                'choice_value' => static fn(?CmsProvider $provider): string => null === $provider ? '' : $provider->value,
                'attr' => ['data-cms-provider-select' => ''],
            ])
            ->add('baseUrl', UrlType::class, [
                'label' => 'CMS base URL',
                'help' => 'WordPress: public site URL. Shopify: the https://store.myshopify.com domain used by the Admin API.',
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $connection = $event->getData();
            $settings = $connection instanceof CmsConnection ? ($connection->getSettings() ?? []) : [];
            $provider = $connection instanceof CmsConnection ? $connection->getProvider() : CmsProvider::WORDPRESS;
            $hasStoredCredential = $connection instanceof CmsConnection && null !== $connection->getEncryptedAccessToken();
            $this->addProviderFields($event->getForm(), $provider, false, $settings, $hasStoredCredential);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = is_array($event->getData()) ? $event->getData() : [];
            $connection = $event->getForm()->getData();
            $settings = $connection instanceof CmsConnection ? ($connection->getSettings() ?? []) : [];
            $provider = CmsProvider::tryFrom(is_scalar($data['provider'] ?? null) ? (string) $data['provider'] : '')
                ?? CmsProvider::WORDPRESS;
            $hasStoredCredential = $connection instanceof CmsConnection
                && $connection->getProvider() === $provider
                && null !== $connection->getEncryptedAccessToken();
            $this->addProviderFields($event->getForm(), $provider, true, $settings, $hasStoredCredential);
        });
    }

    /** @param array<string, mixed> $settings */
    private function addProviderFields(
        FormInterface $form,
        CmsProvider $provider,
        bool $submitted,
        array $settings = [],
        bool $hasStoredCredential = false,
    ): void {
        $wordPressRequired = CmsProvider::WORDPRESS === $provider && $submitted;
        $wordPressSecretRequired = $wordPressRequired && !$hasStoredCredential;
        $shopifySecretRequired = CmsProvider::SHOPIFY === $provider && $submitted && !$hasStoredCredential;

        $form
            ->add('username', TextType::class, [
                'mapped' => false,
                'required' => $wordPressRequired,
                'label' => 'WordPress username',
                'data' => $submitted ? null : ($settings['username'] ?? null),
                'row_attr' => ['data-cms-provider-field' => CmsProvider::WORDPRESS->value],
                'constraints' => $wordPressRequired ? [new Assert\NotBlank()] : [],
            ])
            ->add('applicationPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $wordPressSecretRequired,
                'label' => 'WordPress application password',
                'help' => $hasStoredCredential
                    ? 'A password is already stored securely. Leave this empty to keep it, or enter a new Application Password.'
                    : 'Create an Application Password in the WordPress user profile. Do not use the account password.',
                'always_empty' => true,
                'row_attr' => ['data-cms-provider-field' => CmsProvider::WORDPRESS->value],
                'constraints' => $wordPressSecretRequired ? [new Assert\NotBlank()] : [],
            ])
            ->add('accessToken', PasswordType::class, [
                'mapped' => false,
                'required' => $shopifySecretRequired,
                'label' => 'Shopify Admin API access token',
                'help' => $hasStoredCredential
                    ? 'A token is already stored securely. Leave this empty to keep it, or enter a replacement token.'
                    : 'Paste the Admin API token from a Shopify custom app with read_content and write_content access.',
                'always_empty' => true,
                'row_attr' => ['data-cms-provider-field' => CmsProvider::SHOPIFY->value],
                'constraints' => $shopifySecretRequired ? [new Assert\NotBlank()] : [],
            ])
            ->add('shopifyBlogId', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Destination blog',
                'data' => $submitted ? null : ($settings['blog_id'] ?? null),
                'choices' => $this->shopifyBlogChoices($settings),
                'placeholder' => 'Automatically select the first available blog',
                'help' => 'Blogs are discovered automatically after the first successful connection test.',
                'row_attr' => ['data-cms-provider-field' => CmsProvider::SHOPIFY->value],
            ])
            ->add('authorName', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Shopify article author',
                'data' => $submitted ? null : ($settings['author_name'] ?? 'SEO GEO AI'),
                'row_attr' => ['data-cms-provider-field' => CmsProvider::SHOPIFY->value],
            ]);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, string>
     */
    private function shopifyBlogChoices(array $settings): array
    {
        $availableBlogs = is_array($settings['available_blogs'] ?? null) ? $settings['available_blogs'] : [];
        $choices = [];

        foreach ($availableBlogs as $blog) {
            if (!is_array($blog) || !is_scalar($blog['id'] ?? null)) {
                continue;
            }

            $id = (string) $blog['id'];
            $title = is_scalar($blog['title'] ?? null) ? trim((string) $blog['title']) : '';
            $choices['' === $title ? $id : $title] = $id;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CmsConnection::class,
        ]);
    }
}
