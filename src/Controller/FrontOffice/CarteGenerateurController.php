<?php

namespace App\Controller\FrontOffice;

use App\Service\GeminiCardGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Exception\LogicException as MimeLogicException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/mes-comptes/generateur-carte')]
class CarteGenerateurController extends AbstractController
{
    #[Route('/', name: 'app_front_office_cartegenerateur', methods: ['GET', 'POST'])]
    public function index(Request $request, GeminiCardGeneratorService $geminiService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Augmenter le timeout PHP pour cette action (génération d'image peut être lente)
        set_time_limit(180); // génération / téléchargement image externe (Pollinations) peut dépasser 60 s

        $generatedImageUrl = null;
        $themeOverlayUrl = null;
        $error = null;

        $originalImageBase64 = null;

        if ($request->isMethod('POST')) {
            $imageFile = $request->files->get('card_image');
            $theme = $request->request->get('theme');

            if ($imageFile && $theme) {
                $mimeType = $this->resolveUploadedImageMimeType($imageFile);
                if ($mimeType !== null && str_starts_with($mimeType, 'image/')) {
                    $originalImageData = file_get_contents($imageFile->getPathname());
                    $originalImageBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($originalImageData);
                    
                    try {
                        $cardResult = $geminiService->generateCustomCard($imageFile->getPathname(), $theme);
                        $generatedImageUrl = $cardResult->composedImageDataUrl;
                        $themeOverlayUrl = $cardResult->requiresClientSideLayers ? $cardResult->themeImageUrl : null;
                    } catch (\Exception $e) {
                        $errorMessage = $e->getMessage();
                        $error = "Erreur lors de la génération du thème: $errorMessage";
                        error_log("❌ Erreur complète génération carte: " . $e->getMessage());
                        error_log("   Stack: " . $e->getTraceAsString());
                    }
                } else {
                    $error = "Veuillez uploader un fichier image valide.";
                }
            } else {
                $error = "L'image et le thème sont requis.";
            }
        }

        return $this->render('frontoffice/carte_generateur/index.html.twig', [
            'generated_image_url' => $generatedImageUrl,
            'theme_overlay_url' => $themeOverlayUrl,
            'original_image' => $originalImageBase64,
            'error' => $error,
            'theme' => $request->request->get('theme', '')
        ]);
    }

    /**
     * Sans extension PHP fileinfo (souvent désactivée sous Apache XAMPP), Symfony ne peut pas deviner le MIME : on retombe sur l'extension.
     */
    private function resolveUploadedImageMimeType(UploadedFile $file): ?string
    {
        if (\function_exists('finfo_open')) {
            try {
                $guessed = $file->getMimeType();
                if ($guessed !== '' && str_starts_with($guessed, 'image/')) {
                    return $guessed;
                }
            } catch (MimeLogicException) {
            }
        }

        return $this->mimeTypeFromImageExtension($file->getClientOriginalExtension());
    }

    private function mimeTypeFromImageExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };
    }
}