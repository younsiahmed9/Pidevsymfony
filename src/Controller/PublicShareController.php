<?php

namespace App\Controller;

use App\Repository\DocumentBundleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour l'accès public aux bundles partagés
 */
class PublicShareController extends AbstractController
{
    #[Route('/share/bundle/{token}', name: 'app_public_bundle_share', methods: ['GET'])]
    public function viewBundle(string $token, DocumentBundleRepository $bundleRepository): Response
    {
        $bundle = $bundleRepository->findByToken($token);

        if (!$bundle) {
            return $this->render('public/error_share.html.twig', [
                'message' => 'Ce lien de partage est invalide ou a expiré.'
            ], new Response('', 404));
        }

        return $this->render('public/bundle_share.html.twig', [
            'bundle' => $bundle,
        ]);
    }
}
