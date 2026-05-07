<?php

namespace App\Command;

use App\Service\AIService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-gemini',
    description: 'Teste la connexion à l\'API Google Gemini',
)]
class TestGeminiCommand extends Command
{
    private AIService $aiService;

    public function __construct(AIService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de l\'API Google Gemini');

        $io->section('1. Vérification de la clé API');
        $result = $this->aiService->testApiKey();

        if ($result['success']) {
            $io->success('La clé API est valide !');
            $io->note('Statut HTTP : ' . $result['status']);
            
            if (isset($result['data']['models'])) {
                $io->writeln('Modèles disponibles :');
                foreach ($result['data']['models'] as $model) {
                    $io->writeln('- ' . $model['name'] . ' (' . $model['displayName'] . ')');
                }
            }
        } else {
            $io->error('Échec de la validation de la clé API.');
            $io->writeln('Erreur : ' . ($result['error'] ?? 'Inconnue'));
            if (isset($result['data'])) {
                $io->writeln('Détails : ' . json_encode($result['data'], JSON_PRETTY_PRINT));
            }
            return Command::FAILURE;
        }

        $io->section('2. Test de génération de contenu (Prompt simple)');
        $io->info('Envoi d\'une requête de test...');
        
        $testExpenses = [['date_depense' => '2026-04-20', 'categorie' => 'Alimentation', 'montant' => 50, 'description' => 'Courses']];
        $testBudgets = [['nom_budget' => 'Loisirs', 'montant_total' => 200]];
        $testBalance = 1500.0;

        $advice = $this->aiService->getFinancialAdvice($testExpenses, $testBudgets, $testBalance);

        if (str_contains($advice, '❌ Erreur Gemini AI')) {
            $io->error('Échec de la génération de contenu.');
            $io->writeln($advice);
            return Command::FAILURE;
        }

        $io->success('Génération réussie !');
        $io->writeln('--- RÉPONSE IA ---');
        $io->writeln($advice);
        $io->writeln('------------------');

        return Command::SUCCESS;
    }
}
