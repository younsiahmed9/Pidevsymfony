<?php

namespace App\Controller\API;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\DocumentCategorizationService;
use App\Service\DocumentOCRService;
use App\Service\EcheanceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/document-intelligence')]
class DocumentIntelligenceController extends AbstractController
{
    public function __construct(
        private \App\Service\DocumentOCRService $ocrService,
        private \App\Service\DocumentCategorizationService $categorizationService,
        private \App\Service\DocumentChatService $chatService,
        private \App\Service\EcheanceService $echeanceService,
        private DocumentRepository $documentRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Analyse un document et suggère une catégorie
     */
    #[Route('/{id}/analyze', name: 'api_document_analyze', methods: ['POST'])]
    public function analyze(Document $document): JsonResponse
    {
        try {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getCheminFichier();

            if (!file_exists($filePath)) {
                return $this->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
            }

            // 1. Extraire le texte
            $fileExtension = strtolower(pathinfo($document->getCheminFichier(), PATHINFO_EXTENSION));
            $extractedText = null;

            if ($fileExtension === 'pdf') {
                $extractedText = $this->ocrService->extractTextFromPDF($filePath);
            } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extractedText = $this->ocrService->extractTextFromImage($filePath);
            }

            $extractedText = $extractedText ? $this->ocrService->cleanText($extractedText) : '';

            // 2. Suggérer une catégorie
            $suggestedCategory = $this->categorizationService->suggestCategory($extractedText, $document->getTitre() ?? '');

            if (!$suggestedCategory) {
                return $this->json(['success' => false, 'message' => 'Aucune catégorie correspondante trouvée'], 200);
            }

            return $this->json([
                'success' => true,
                'suggestion' => [
                    'id' => $suggestedCategory->getId(),
                    'name' => $suggestedCategory->getNomCategorie(),
                    'icon' => $suggestedCategory->getIcon(),
                    'color' => $suggestedCategory->getCouleur(),
                ],
                'summary' => substr($extractedText, 0, 200) . '...'
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Applique automatiquement la catégorie suggérée
     */
    #[Route('/{id}/apply-suggestion', name: 'api_document_apply_suggestion', methods: ['POST'])]
    public function applySuggestion(Document $document, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $categoryId = $data['categoryId'] ?? null;

        if (!$categoryId) {
            return $this->json(['success' => false, 'message' => 'ID Catégorie manquant'], 400);
        }

        try {
            $repo = $this->entityManager->getRepository(\App\Entity\Categorie::class);
            $category = $repo->find($categoryId);

            if (!$category) {
                return $this->json(['success' => false, 'message' => 'Catégorie introuvable'], 404);
            }

            $document->setCategorie($category);
            $this->entityManager->flush();

            // Sync echeance as well if the document has dates from previous analysis
            $this->echeanceService->syncFromDocument($document);
            $this->entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Catégorie mise à jour avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Analyse un fichier téléchargé (non sauvegardé) pour suggérer catégorie et dossier
     */
    #[Route('/analyze-upload', name: 'api_document_analyze_upload', methods: ['POST'])]
    public function analyzeUpload(Request $request): JsonResponse
    {
        $this->logger->info('AI Analyze Upload - Request received');
        try {
            $file = $request->files->get('fichier');
            if (!$file) {
                return $this->json(['success' => false, 'message' => 'Aucun fichier reçu'], 400);
            }

            $titleIn = $request->request->get('titre', '');
            
            // Sauvegarde temporaire pour OCR
            $tempName = uniqid('analyze_') . '.' . $file->guessExtension();
            $file->move(sys_get_temp_dir(), $tempName);
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempName;

            // 1. Extraire le texte
            $fileExtension = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
            $extractedText = null;

            if ($fileExtension === 'pdf') {
                $extractedText = $this->ocrService->extractTextFromPDF($tempPath);
            } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extractedText = $this->ocrService->extractTextFromImage($tempPath);
            }

            $extractedText = $extractedText ? $this->ocrService->cleanText($extractedText) : '';

            $this->logger->info(sprintf('AI Analyze Upload - Title In: "%s", Extracted Text Length: %d, Preview: %s', 
                $titleIn, strlen($extractedText), substr($extractedText, 0, 100)));

            // 2. Extraire les méta-données
            $metadata = $this->categorizationService->extractMetadata($extractedText);
            
            // Si le titre de l'utilisateur est vide, on prend celui de l'OCR
            $title = !empty($titleIn) ? $titleIn : ($metadata['title'] ?: 'Document Sans Titre');

            // 3. Suggérer catégorie et dossier
            $suggestedCategory = $this->categorizationService->suggestCategory($extractedText, $title);
            $suggestedFolder = $this->categorizationService->suggestFolder($extractedText, $title, $this->getUser());

            // Nettoyage
            @unlink($tempPath);

            return $this->json([
                'success' => true,
                'extracted_text' => $extractedText,
                'suggestion' => [
                    'title'        => $title,
                    'description'  => $metadata['description'],
                    'tags'         => $metadata['tags'],
                    'categoryId'   => $suggestedCategory?->getId(),
                    'categoryName' => $suggestedCategory?->getNomCategorie(),
                    'folderId'     => $suggestedFolder?->getId(),
                    'folderName'   => $suggestedFolder?->getNomDossier(),
                    'date_document'  => $metadata['date_document'],
                    'date_echeance'  => $metadata['date_echeance'],
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * Chat avec le document (Q&A)
     */
    #[Route('/{id}/chat', name: 'api_document_chat', methods: ['POST'])]
    public function chat(Document $document, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';

        if (empty($question)) {
            return $this->json(['success' => false, 'message' => 'Veuillez poser une question.'], 400);
        }

        try {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $document->getCheminFichier();

            if (!file_exists($filePath)) {
                return $this->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
            }

            // 1. Extraire le texte si nécessaire (on peut utiliser un cache ou le texte déjà en base si dispo)
            // Pour l'instant on ré-extrait ou on suppose que c'est rapide
            $fileExtension = strtolower(pathinfo($document->getCheminFichier(), PATHINFO_EXTENSION));
            $extractedText = null;

            if ($fileExtension === 'pdf') {
                $extractedText = $this->ocrService->extractTextFromPDF($filePath);
            } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extractedText = $this->ocrService->extractTextFromImage($filePath);
            }

            $extractedText = $extractedText ? $this->ocrService->cleanText($extractedText) : '';

            // 2. Obtenir la réponse de l'IA (Mocked/Smart search for now)
            $response = $this->chatService->askQuestion($extractedText, $question);

            return $this->json([
                'success' => true,
                'answer' => $response
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
