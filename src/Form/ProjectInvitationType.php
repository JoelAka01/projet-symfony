<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProjectGuestAccess;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ProjectInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Guest Email Address',
            'constraints' => [
                new Assert\NotBlank(message: 'Please enter the email address of the guest.'),
                new Assert\Email(message: 'Please enter a valid email address.'),
            ],
            'attr' => [
                'placeholder' => 'guest@example.com',
                'autocomplete' => 'email',
            ],
        ]);
        $builder->add('access', EnumType::class, [
            'class' => ProjectGuestAccess::class,
            'label' => 'Access level',
            'choice_label' => static fn (ProjectGuestAccess $access): string => $access->label(),
            'data' => ProjectGuestAccess::CONTENT,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'project_invitation',
        ]);
    }
}
