<?php

namespace App\Command;

use App\Service\GeminiAIService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gemini:test',
    description: 'Teste l\'intégration de l\'IA Gemini pour l\'analyse documentaire.',
)]
class GeminiTestCommand extends Command
{
    public function __construct(private GeminiAIService $geminiService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de l\'intégration Gemini AI');

        $testText = "FACTURE\nSociété : Tech Solutions SARL\nDate : 15/04/2026\nMontant Total : 1250.50 €\nObjet : Maintenance serveurs Q2";
        
        $io->section('Test d\'analyse de document');
        $io->info('Texte de test :');
        $io->text($testText);

        $analysis = $this->geminiService->analyzeDocumentText($testText);

        if (isset($analysis['error'])) {
            $io->error('Erreur lors de l\'analyse : ' . $analysis['error']);
            return Command::FAILURE;
        }

        $io->success('Analyse réussie !');
        $io->table(
            ['Champ', 'Valeur'],
            [
                ['Titre', $analysis['title'] ?? 'N/A'],
                ['Catégorie', $analysis['category'] ?? 'N/A'],
                ['Date Document', $analysis['date_document'] ?? 'N/A'],
                ['Date Échéance', $analysis['date_echeance'] ?? 'N/A'],
                ['Montant', $analysis['amount'] ?? 'N/A'],
                ['Tags', $analysis['tags'] ?? 'N/A'],
            ]
        );

        $io->section('Test du Chat documentaire');
        $question = "Quel est le montant total de cette facture ?";
        $io->info("Question : $question");
        
        $answer = $this->geminiService->chatWithDocument($testText, $question);
        $io->note('Réponse : ' . $answer);

        return Command::SUCCESS;
    }
}
