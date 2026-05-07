<?php

namespace App\Command;

use App\Service\Transfer\ScheduledTransferEngineConfigManager;
use App\Service\Transfer\ScheduledTransferEngineResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:transfers:set-engine', description: 'Switch scheduled transfer engine (symfony|n8n)')]
final class SetScheduledTransferEngineCommand extends Command
{
    public function __construct(
        private ScheduledTransferEngineConfigManager $configManager,
        private ScheduledTransferEngineResolver $resolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('engine', InputArgument::REQUIRED, 'Engine to use: symfony or n8n');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $engine = strtolower(trim((string) $input->getArgument('engine')));

        if (!in_array($engine, [ScheduledTransferEngineResolver::ENGINE_SYMFONY, ScheduledTransferEngineResolver::ENGINE_N8N], true)) {
            $io->error('Invalid engine. Allowed values: symfony, n8n.');

            return Command::INVALID;
        }

        $this->configManager->setEngine($engine);

        $io->success(sprintf('Scheduled transfer engine switched to "%s". Run `php bin/console cache:clear` if needed.', $engine));

        return Command::SUCCESS;
    }
}
