<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\DocumentCategorizationService;
use App\Service\DocumentStorageService;
use App\Service\DocumentOCRService;
use App\Service\DocumentChatService;
use App\Service\EcheanceService;
use App\Service\GeminiAIService;
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
        private DocumentOCRService $ocrService,
        private DocumentCategorizationService $categorizationService,
        private DocumentChatService $chatService,
        private DocumentStorageService $documentStorage,
        private EcheanceService $echeanceService,
        private GeminiAIService $geminiService,
        private DocumentRepository $documentRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Analyse un document et suggère une catégorie (Version locale/regex)
     */
    #[Route('/{id}/analyze', name: 'api_document_analyze', methods: ['POST'])]
    public function analyze(Document $document): JsonResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        try {
            $filePath = $this->documentStorage->resolvePath($document);

            if (!$filePath || !file_exists($filePath)) {
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
                'summary' => mb_substr($extractedText, 0, 200) . '...'
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Analyse avancée via Gemini AI
     */
    #[Route('/{id}/analyze-gemini', name: 'api_document_analyze_gemini', methods: ['POST'])]
    public function analyzeGemini(Document $document): JsonResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        try {
            $filePath = $this->documentStorage->resolvePath($document);

            if (!$filePath || !file_exists($filePath)) {
                return $this->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
            }

            $fileExtension = strtolower(pathinfo($document->getCheminFichier(), PATHINFO_EXTENSION));
            $extractedText = null;

            if ($fileExtension === 'pdf') {
                $extractedText = $this->ocrService->extractTextFromPDF($filePath);
            } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extractedText = $this->ocrService->extractTextFromImage($filePath);
            }

            $extractedText = $extractedText ? $this->ocrService->cleanText($extractedText) : '';

            if (empty($extractedText)) {
                return $this->json(['success' => false, 'message' => 'Aucun texte extrait du document'], 400);
            }

            $analysis = $this->geminiService->analyzeDocumentText($extractedText);

            return $this->json([
                'success' => !isset($analysis['error']),
                'analysis' => $analysis
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
        if (!$this->isGranted('DOCUMENT_CLASSIFY', $document)) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

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

            // La synchronisation d'échéance ne doit pas bloquer l'application de la catégorie.
            try {
                $echeance = $this->echeanceService->syncFromDocument($document);
                if ($echeance !== null) {
                    $this->entityManager->flush();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Echeance sync failed after category application: ' . $e->getMessage());
            }

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
            $tempName = uniqid('analyze_', true) . '.' . $file->guessExtension();
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
                $titleIn, mb_strlen($extractedText), mb_substr($extractedText, 0, 100)));

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
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';

        if (empty($question)) {
            return $this->json(['success' => false, 'message' => 'Veuillez poser une question.'], 400);
        }

        try {
            $filePath = $this->documentStorage->resolvePath($document);

            if (!$filePath || !file_exists($filePath)) {
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

            // 2. Obtenir la réponse (priorité à Gemini si dispo, sinon fallback local)
            $response = $this->chatService->askQuestion($extractedText, $question);

            if ($this->geminiService->isConfigured()) {
                $geminiResponse = $this->geminiService->chatWithDocument($extractedText, $question);

                if (!empty($geminiResponse) && $geminiResponse !== "Une erreur est survenue lors de la communication avec l'IA.") {
                    $response = $geminiResponse;
                }
            }

            return $this->json([
                'success' => true,
                'answer' => $response
            ]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
