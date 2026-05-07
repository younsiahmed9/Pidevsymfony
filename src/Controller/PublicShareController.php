<?php

namespace App\Controller;

use App\Repository\PackRepository;
use App\Service\DocumentStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour l'accès public aux packs partagés
 */
class PublicShareController extends AbstractController
{
    public function __construct(private readonly DocumentStorageService $documentStorage)
    {
    }

    #[Route('/share/pack/{token}', name: 'app_public_pack_share', methods: ['GET'])]
    public function viewPack(string $token, PackRepository $packRepository): Response
    {
        $pack = $packRepository->findByToken($token);

        if (!$pack) {
            return $this->render('public/error_share.html.twig', [
                'message' => 'Ce lien de partage est invalide ou a expiré.'
            ], new Response('', 404));
        }

        return $this->render('public/pack_share.html.twig', [
            'pack' => $pack,
        ]);
    }

    #[Route('/share/pack/{token}/document/{documentId}/download', name: 'app_public_pack_document_download', methods: ['GET'])]
    public function downloadDocument(string $token, int $documentId, PackRepository $packRepository): BinaryFileResponse
    {
        $pack = $packRepository->findByToken($token);

        if (!$pack) {
            throw $this->createNotFoundException('Lien de partage introuvable.');
        }

        $document = null;
        foreach ($pack->getDocuments() as $candidate) {
            if ($candidate->getId() === $documentId && !$candidate->isDeleted()) {
                $document = $candidate;
                break;
            }
        }

        if (!$document) {
            throw $this->createNotFoundException('Document introuvable dans ce pack.');
        }

        $path = $this->documentStorage->resolvePath($document);
        if (!$path) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file($path, $document->getOriginalFilename() ?: $document->getCheminFichier());
    }

    #[Route('/share/pack/{token}/document/{documentId}/preview', name: 'app_public_pack_document_preview', methods: ['GET'])]
    public function previewDocument(string $token, int $documentId, PackRepository $packRepository): BinaryFileResponse
    {
        $pack = $packRepository->findByToken($token);

        if (!$pack) {
            throw $this->createNotFoundException('Lien de partage introuvable.');
        }

        $document = null;
        foreach ($pack->getDocuments() as $candidate) {
            if ($candidate->getId() === $documentId && !$candidate->isDeleted()) {
                $document = $candidate;
                break;
            }
        }

        if (!$document) {
            throw $this->createNotFoundException('Document introuvable dans ce pack.');
        }

        $path = $this->documentStorage->resolvePath($document);
        if (!$path) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $document->getMimeType() ?: (mime_content_type($path) ?: 'application/octet-stream'));
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getOriginalFilename() ?: $document->getCheminFichier()
        ));

        return $response;
    }
}
