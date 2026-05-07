<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Invoice\InvoicePdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/invoices', name: 'api_invoice_')]
final class InvoiceController extends AbstractController
{
    private InvoicePdfService $invoicePdfService;
    private EntityManagerInterface $entityManager;

    public function __construct(InvoicePdfService $invoicePdfService, EntityManagerInterface $entityManager)
    {
        $this->invoicePdfService = $invoicePdfService;
        $this->entityManager = $entityManager;
    }

    #[Route('/product/{id}/pdf', name: 'product_pdf', methods: ['POST'])]
    public function productInvoicePdf(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise'], 401);
        }

        $product = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant, 
                    code_unique AS codeUnique, type_produit AS typeProduit, statut, 
                    date_creation AS dateCreation 
             FROM produit 
             WHERE id_produit = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$product) {
            return $this->json(['message' => 'Produit introuvable'], 404);
        }

        $publicBaseUrl = $this->buildPublicBaseUrl($request);

        try {
            return $this->invoicePdfService->generateProductInvoiceResponse($user, $product, $publicBaseUrl);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Erreur technique: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/product/{id}/qr', name: 'product_qr', methods: ['GET'])]
    public function productInvoiceQr(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('Authentification requise', 401);
        }

        $product = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant, 
                    code_unique AS codeUnique, type_produit AS typeProduit, statut, 
                    date_creation AS dateCreation 
             FROM produit 
             WHERE id_produit = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$product) {
            return new Response('Produit introuvable', 404);
        }

        $publicBaseUrl = $this->buildPublicBaseUrl($request);

        try {
            $svg = $this->invoicePdfService->generateProductInvoiceQrSvg($user, $product, $publicBaseUrl);
            return new Response($svg, 200, ['Content-Type' => 'image/svg+xml']);
        } catch (\Exception $e) {
            return new Response('Erreur: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/public/{token}/pdf', name: 'public_pdf', methods: ['GET'])]
    public function publicInvoicePdf(string $token, Request $request): Response
    {
        $payload = $this->invoicePdfService->parsePublicInvoiceToken($token);
        if (!$payload) {
            return $this->render('public/error_share.html.twig', [
                'message' => 'Lien de consultation invalide ou expiré.'
            ], new Response('', 403));
        }

        $userId = $payload['userId'];
        $productId = $payload['productId'];

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $product = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant, 
                    code_unique AS codeUnique, type_produit AS typeProduit, statut, 
                    date_creation AS dateCreation 
             FROM produit 
             WHERE id_produit = :id AND user_id = :uid',
            ['id' => $productId, 'uid' => $userId]
        );

        if (!$user || !$product) {
            return $this->render('public/error_share.html.twig', [
                'message' => 'Données de facture introuvables.'
            ], new Response('', 404));
        }

        $publicBaseUrl = $this->buildPublicBaseUrl($request);

        try {
            return $this->invoicePdfService->generateProductInvoiceResponse($user, $product, $publicBaseUrl);
        } catch (\Exception $e) {
            return new Response('Erreur de génération PDF publique: ' . $e->getMessage(), 500);
        }
    }

    private function buildPublicBaseUrl(Request $request): string
    {
        $basePath = rtrim($request->getBasePath(), '/');

        return $request->getSchemeAndHttpHost() . $basePath;
    }
}
