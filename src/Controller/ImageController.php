<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/images')]
class ImageController extends AbstractController
{
    private $documentsDir;
    private $cacheDir;

    public function __construct(#[Autowire(param: 'kernel.project_dir')] string $kernelProjectDir)
    {
        $this->documentsDir = $kernelProjectDir . '/public/uploads/documents';
        $this->cacheDir = $kernelProjectDir . '/var/cache/images';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    #[Route('/document/{filename}', name: 'image_document', methods: ['GET'])]
    public function document(string $filename): Response
    {
        return $this->serveImage($filename);
    }

    #[Route('/thumbnail/{filename}', name: 'image_thumbnail', methods: ['GET'])]
    public function thumbnail(string $filename): Response
    {
        return $this->serveThumbnail($filename);
    }

    private function serveImage(string $filename): Response
    {
        // Sécurité : valider le nom du fichier
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $filename)) {
            throw $this->createNotFoundException('Invalid filename');
        }

        $filepath = $this->documentsDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Image not found');
        }

        // Déterminer le type MIME
        $mimeType = $this->getMimeType($filepath);

        // Lire et retourner le fichier
        $response = new Response(file_get_contents($filepath));
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'public, max-age=31536000');
        $response->headers->set('Pragma', 'public');

        return $response;
    }

    private function serveThumbnail(string $filename): Response
    {
        // Sécurité
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $filename)) {
            throw $this->createNotFoundException('Invalid filename');
        }

        $filepath = $this->documentsDir . '/' . $filename;
        $cacheFile = $this->cacheDir . '/' . hash('md5', $filename) . '_thumb.webp';

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Image not found');
        }

        // Utiliser le cache si disponible et le fichier source n'a pas changé
        if (file_exists($cacheFile) && filemtime($cacheFile) > filemtime($filepath)) {
            $response = new Response(file_get_contents($cacheFile));
            $response->headers->set('Content-Type', 'image/webp');
            $response->headers->set('Cache-Control', 'public, max-age=31536000');
            return $response;
        }

        // Générer la vignette
        $thumbnailData = $this->generateThumbnail($filepath);

        if (!$thumbnailData) {
            // Fallback : retourner l'image originale
            return $this->serveImage($filename);
        }

        // Sauvegarder en cache
        file_put_contents($cacheFile, $thumbnailData);

        $response = new Response($thumbnailData);
        $response->headers->set('Content-Type', 'image/webp');
        $response->headers->set('Cache-Control', 'public, max-age=31536000');

        return $response;
    }

    private function generateThumbnail(string $filepath): ?string
    {
        $mimeType = $this->getMimeType($filepath);

        // Vérifier si GD est disponible
        if (!extension_loaded('gd')) {
            return null;
        }

        // Charger l'image
        $image = $this->loadImage($filepath, $mimeType);
        if (!$image) {
            return null;
        }

        // Dimensionner la vignette (250x350 max, maintenir proportions)
        $width = imagesx($image);
        $height = imagesy($image);

        $maxWidth = 250;
        $maxHeight = 350;

        // Calculer les nouvelles dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Créer une nouvelle image
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

        // Activer la transparence pour les PNG et GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Redimensionner l'image
        imagecopyresampled(
            $thumbnail, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Convertir en WebP avec compression
        ob_start();
        imagewebp($thumbnail, null, 85);
        $data = ob_get_clean();

        imagedestroy($image);
        imagedestroy($thumbnail);

        return $data;
    }

    private function loadImage(string $filepath, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filepath);
            case 'image/png':
                return imagecreatefrompng($filepath);
            case 'image/gif':
                return imagecreatefromgif($filepath);
            case 'image/webp':
                return imagecreatefromwebp($filepath);
            default:
                return null;
        }
    }

    private function getMimeType(string $filepath): string
    {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        return match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
