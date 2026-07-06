<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Form\ProjectType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class ProjectTypeTest extends TypeTestCase
{
    public function testItSubmitsProjectFieldsAndUnmappedWebsiteUrl(): void
    {
        $project = new Project();
        $form = $this->factory->create(ProjectType::class, $project, [
            'include_status' => true,
            'website_url' => 'https://old.example/',
        ]);

        self::assertSame('https://old.example/', $form->get('websiteUrl')->getData());

        $form->submit([
            'name' => 'Updated project',
            'websiteUrl' => 'example.com',
            'language' => 'en',
            'targetCountry' => 'US',
            'status' => ProjectStatus::PAUSED->value,
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('Updated project', $project->getName());
        self::assertSame('example.com', $form->get('websiteUrl')->getData());
        self::assertSame('en', $project->getLanguage());
        self::assertSame('US', $project->getTargetCountry());
        self::assertSame(ProjectStatus::PAUSED, $project->getStatus());
    }

    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }
}
