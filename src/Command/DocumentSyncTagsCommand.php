<?php

namespace App\Command;

use App\Repository\DocumentRepository;
use App\Service\DocumentCategorizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:document:sync-tags',
    description: 'Synchronise les tags des documents basés sur leur contenu textuel.',
)]
class DocumentSyncTagsCommand extends Command
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentCategorizationService $categorizationService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation des tags des documents');

        $documents = $this->documentRepository->findAll();
        $updated = 0;

        foreach ($documents as $document) {
            $text = $document->getExtractedText();
            if (empty($text)) {
                continue;
            }

            $metadata = $this->categorizationService->extractMetadata($text);
            if (!empty($metadata['tags'])) {
                // Ici on suppose que Document a un champ tags ou une relation. 
                // Melek utilise souvent un champ string 'tags' pour la simplicité.
                if (method_exists($document, 'setTags')) {
                    $document->setTags($metadata['tags']);
                    $updated++;
                }
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d document(s) mis à jour avec de nouveaux tags.', $updated));

        return Command::SUCCESS;
    }
}
