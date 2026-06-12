<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Entity\AiUsage;
use App\Entity\Project;
use App\Entity\User;
use App\Service\Ai\AiUsageRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AiUsageRecorderTest extends TestCase
{
    public function testItCalculatesCreditsFromProviderReportedTokenCategories(): void
    {
        $persistedUsage = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (object $entity) use (&$persistedUsage): bool {
                $persistedUsage = $entity;

                return $entity instanceof AiUsage;
            }));

        $user = (new User())->setEmail('usage@example.com');
        $project = (new Project())->setName('Usage project');
        $recorder = new AiUsageRecorder($entityManager);

        $result = $recorder->record(
            $user,
            $project,
            'anthropic',
            'claude-test',
            AiUsageRecorder::OPERATION_AUDIT_ANALYSIS,
            [
                'input_tokens' => 100,
                'output_tokens' => 40,
                'cache_creation_input_tokens' => 20,
                'cache_read_input_tokens' => 10,
            ],
            '00000000-0000-0000-0000-000000000001',
        );

        self::assertSame($result, $persistedUsage);
        self::assertSame(100, $result->getInputTokens());
        self::assertSame(40, $result->getOutputTokens());
        self::assertSame(30, $result->getCachedInputTokens());
        self::assertSame(170, $result->getCredits());
        self::assertSame($user, $result->getUser());
        self::assertSame($project, $result->getProject());
    }
}
