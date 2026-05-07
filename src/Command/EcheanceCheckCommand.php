<?php

namespace App\Command;

use App\Service\EcheanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:echeance:check',
    description: 'Mise à jour automatique des statuts des échéances (overdue / notified).',
)]
class EcheanceCheckCommand extends Command
{
    public function __construct(private EcheanceService $echeanceService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vérification des échéances');

        $isDryRun = (bool) $input->getOption('dry-run');
        if ($isDryRun) {
            $io->note('Mode dry-run : aucune modification ne sera enregistrée.');
        }

        if (!$isDryRun) {
            $overdue   = $this->echeanceService->updateOverdueStatuses();
            $notified  = $this->echeanceService->markTodayReminders();

            $io->success(sprintf('%d échéance(s) passée(s) en "overdue".', $overdue));
            $io->success(sprintf('%d échéance(s) passée(s) en "notified".', $notified));
        } else {
            $io->info('Dry-run : aucune modification effectuée.');
        }

        $io->info('Commande terminée avec succès. Planifiez-la via cron (ex: * * * * *).');

        return Command::SUCCESS;
    }
}
