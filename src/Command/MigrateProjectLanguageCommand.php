<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Project;
use App\Service\Language\LanguageDetectionService;
use App\Service\Project\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-project-language',
    description: 'Detects and assigns language/country for existing projects that have no language set.',
)]
final class MigrateProjectLanguageCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LanguageDetectionService $languageDetector,
        private readonly ProjectManager $projectManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing to database.');
        $this->addOption('default-language', null, InputOption::VALUE_REQUIRED, 'Fallback language when detection fails.', 'fr');
        $this->addOption('default-country', null, InputOption::VALUE_REQUIRED, 'Fallback country when detection fails.', 'FR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $defaultLanguage = (string) $input->getOption('default-language');
        $defaultCountry = (string) $input->getOption('default-country');

        if ($dryRun) {
            $io->note('Running in dry-run mode — no changes will be saved.');
        }

        $projects = $this->entityManager
            ->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->where('p.language IS NULL')
            ->getQuery()
            ->getResult();

        if ([] === $projects) {
            $io->success('All projects already have a language assigned.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d project(s) without a language.', count($projects)));

        $detected = 0;
        $fallback = 0;
        $errors = 0;

        foreach ($projects as $project) {
            \assert($project instanceof Project);

            $websiteUrl = $this->projectManager->getPrimaryWebsiteUrl($project);
            $projectName = $project->getName();

            if (null === $websiteUrl) {
                $io->warning(sprintf('[%s] No website URL — applying default: %s / %s', $projectName, $defaultLanguage, $defaultCountry));
                if (!$dryRun) {
                    $project->setLanguage($defaultLanguage);
                    $project->setTargetCountry($project->getTargetCountry() ?? $defaultCountry);
                    $project->setLanguageConfidence(0);
                }
                ++$fallback;

                continue;
            }

            try {
                $result = $this->languageDetector->detect($websiteUrl);

                if ($result->isConfident() && null !== $result->language) {
                    $io->text(sprintf(
                        '[%s] Detected: %s / %s (confidence: %d%%, method: %s)',
                        $projectName,
                        $result->language,
                        $result->country ?? '—',
                        $result->confidence,
                        $result->detectionMethod,
                    ));

                    if (!$dryRun) {
                        $project->setLanguage($result->language);
                        $project->setLanguageConfidence($result->confidence);
                        if (null !== $result->country && null === $project->getTargetCountry()) {
                            $project->setTargetCountry($result->country);
                        }
                    }

                    ++$detected;
                } else {
                    $io->text(sprintf(
                        '[%s] Low confidence (%d%%) — applying default: %s / %s',
                        $projectName,
                        $result->confidence,
                        $defaultLanguage,
                        $defaultCountry,
                    ));

                    if (!$dryRun) {
                        $project->setLanguage($defaultLanguage);
                        $project->setTargetCountry($project->getTargetCountry() ?? $defaultCountry);
                        $project->setLanguageConfidence($result->confidence);
                    }

                    ++$fallback;
                }
            } catch (\Throwable $exception) {
                $io->error(sprintf('[%s] Detection failed: %s — applying default.', $projectName, $exception->getMessage()));

                if (!$dryRun) {
                    $project->setLanguage($defaultLanguage);
                    $project->setTargetCountry($project->getTargetCountry() ?? $defaultCountry);
                    $project->setLanguageConfidence(0);
                }

                ++$errors;
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine();
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total projects processed', (string) count($projects)],
                ['Language detected', (string) $detected],
                ['Default fallback applied', (string) $fallback],
                ['Errors (fallback applied)', (string) $errors],
            ],
        );

        if ($dryRun) {
            $io->note('Dry-run completed. Run without --dry-run to apply changes.');
        } else {
            $io->success('Project language migration completed.');
        }

        return Command::SUCCESS;
    }
}
