<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\DocumentOCRService;
use App\Service\DocumentStorageService;
use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/translation')]
class TranslationController extends AbstractController
{
    public function __construct(
        private DocumentOCRService $ocrService,
        private DocumentStorageService $documentStorage,
        private TranslationService $translationService,
        private DocumentRepository $documentRepository,
    ) {
    }

    /**
     * Extrait et traduit le texte d'un document
     */
    #[Route('/document/{id}/translate', name: 'api_translate_document', methods: ['POST'])]
    public function translateDocument(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);
        
        if ($document->getUtilisateur() !== $this->getUser()) {
             return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $targetLanguage = $data['targetLanguage'] ?? 'en';

        try {
            $filePath = $this->documentStorage->resolvePath($document);

            // Vérifier que le fichier existe
            if (!$filePath || !file_exists($filePath)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Fichier du document non trouvé',
                ], 404);
            }

            // Extraire le texte selon le type de fichier
            $extractedText = null;
            $fileExtension = strtolower(pathinfo($document->getCheminFichier(), PATHINFO_EXTENSION));

            if ($fileExtension === 'pdf') {
                $extractedText = $this->ocrService->extractTextFromPDF($filePath);
            } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extractedText = $this->ocrService->extractTextFromImage($filePath);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => "Type de fichier non supporté: .$fileExtension",
                ], 400);
            }

            if (!$extractedText || trim($extractedText) === '') {
                return $this->json([
                    'success' => false,
                    'message' => '<strong>⚠️ Impossible d\'extraire le texte du document</strong><br/><br/>
                                <strong>Causes possibles:</strong><br/>
                                • PDF scané ou image de faible qualité<br/>
                                • Fichier corrompu ou protégé<br/>
                                • Document vide ou sans contenu textuel<br/>
                                • APIs externes temporairement indisponibles<br/><br/>
                                
                                <strong>Solutions recommandées:</strong><br/>
                                1️⃣ <strong>Vérifiez le fichier:</strong><br/>
                                &nbsp;&nbsp;• Ouvrez le PDF/image dans un lecteur<br/>
                                &nbsp;&nbsp;• Assurez-vous qu\'il contient du texte visible<br/>
                                &nbsp;&nbsp;• Augmentez la résolution si c\'est une image<br/><br/>
                                
                                2️⃣ <strong>Convertissez le fichier:</strong><br/>
                                &nbsp;&nbsp;• PDFs scannés: utiliser OCR (Adobe, ILovePDF)<br/>
                                &nbsp;&nbsp;• Images: scanner avec meilleure qualité<br/><br/>
                                
                                3️⃣ <strong>Installez les outils locaux (meilleure qualité):</strong><br/>
                                &nbsp;&nbsp;• <code>pdftotext</code> (PDFs texte)<br/>
                                &nbsp;&nbsp;• <code>tesseract</code> (Images OCR)<br/>
                                &nbsp;&nbsp;Cela donnera les meilleurs résultats<br/><br/>',
                    'suggestions' => [
                        'pdf_quality' => 'Le PDF doit être text-based, pas une image scannée',
                        'image_quality' => 'L\'image doit avoir une bonne résolution (300+ DPI)',
                        'file_format' => 'Formats supportés: PDF, JPG, PNG, GIF, WEBP',
                    ],
                ], 400);
            }

            // Nettoyer et limiter le texte
            $extractedText = $this->ocrService->cleanText($extractedText);
            $limitedText = $this->ocrService->limitText($extractedText, 5000);

            // Traduire
            $translatedText = $this->translationService->translateText(
                $limitedText,
                $targetLanguage
            );

            if (!$translatedText) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur lors de la traduction. Vérifiez votre connexion internet.',
                ], 500);
            }

            return $this->json([
                'success' => true,
                'original' => [
                    'text' => $extractedText,
                    'charCount' => mb_strlen($extractedText),
                    'wordCount' => str_word_count($extractedText),
                ],
                'translated' => [
                    'text' => $translatedText,
                    'language' => $targetLanguage,
                    'charCount' => mb_strlen($translatedText),
                    'wordCount' => str_word_count($translatedText),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retourne les langues supportées
     */
    #[Route('/languages', name: 'api_get_languages', methods: ['GET'])]
    public function getLanguages(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'languages' => $this->translationService->getSupportedLanguages(),
        ]);
    }
}
