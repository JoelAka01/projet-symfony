<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\AuditStatus;
use App\Repository\AuditRepository;
use App\Service\Ai\ClaudeSeoAnalysisService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:retry-ai',
    description: 'Retry the real Claude SEO/GEO analysis for a completed audit.',
)]
final class RetryAuditAiCommand extends Command
{
    public function __construct(
        private readonly AuditRepository $auditRepository,
        private readonly ClaudeSeoAnalysisService $claudeSeoAnalysis,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('audit-id', InputArgument::REQUIRED, 'Audit UUID')
            ->addOption(
                'stored-response',
                null,
                InputOption::VALUE_NONE,
                'Reparse the saved Claude response without making another API request.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $auditId = (string) $input->getArgument('audit-id');
        $audit = $this->auditRepository->find($auditId);

        if (null === $audit) {
            $io->error(sprintf('Audit "%s" was not found.', $auditId));

            return Command::FAILURE;
        }

        if (AuditStatus::COMPLETED !== $audit->getStatus()) {
            $io->error(sprintf(
                'Audit "%s" must be completed before Claude analysis can run. Current status: %s.',
                $auditId,
                $audit->getStatus()->value,
            ));

            return Command::FAILURE;
        }

        if ($audit->getPages()->isEmpty()) {
            $io->error(sprintf('Audit "%s" has no crawled pages to analyze.', $auditId));

            return Command::FAILURE;
        }

        $io->note(sprintf('Running Claude analysis for audit %s...', $auditId));
        try {
            if ((bool) $input->getOption('stored-response')) {
                $this->claudeSeoAnalysis->reparseStoredResponse($audit);
            } else {
                $this->claudeSeoAnalysis->analyze($audit);
            }
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $aiAnalysis = $audit->getMetadata()['ai_analysis'] ?? null;
        if (!is_array($aiAnalysis)) {
            $io->error('Claude analysis did not persist a result.');

            return Command::FAILURE;
        }

        $status = is_scalar($aiAnalysis['status'] ?? null) ? (string) $aiAnalysis['status'] : 'unknown';
        if ('completed' !== $status) {
            $error = is_scalar($aiAnalysis['error'] ?? null)
                ? (string) $aiAnalysis['error']
                : 'No error details were returned.';
            $io->error(sprintf('Claude analysis status: %s. %s', $status, $error));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Claude analysis completed. Global: %s, content: %s, GEO: %s.',
            $this->displayScore($aiAnalysis['global_score'] ?? null),
            $this->displayScore($aiAnalysis['content_score'] ?? null),
            $this->displayScore($aiAnalysis['geo_score'] ?? null),
        ));

        return Command::SUCCESS;
    }

    private function displayScore(mixed $score): string
    {
        return is_int($score) || is_float($score) || is_string($score)
            ? (string) $score
            : 'not returned';
    }
}
