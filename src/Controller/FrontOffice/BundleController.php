<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\SavingsBundleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bundle', name: 'bundle_')]
final class BundleController extends AbstractController
{
    private SavingsBundleService $savingsService;

    public function __construct(SavingsBundleService $savingsService)
    {
        $this->savingsService = $savingsService;
    }

    /**
     * Page pour regrouper les restes de budgets vers l'épargne.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les budgets avec leurs résidus
        $budgets = $this->savingsService->getBudgetsResiduals($user);

        // Récupérer les comptes épargne existants
        $existingSavings = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_compte, numero_compte, solde FROM compte WHERE user_id = :user_id AND type_compte = "epargne" AND etat = "actif"',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/bundle/index.html.twig', [
            'budgets' => $budgets,
            'existingSavings' => $existingSavings
        ]);
    }

    /**
     * Exécute le transfert vers l'épargne.
     */
    #[Route('/apply', name: 'apply', methods: ['POST'])]
    public function apply(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        $sweeps = $payload['sweeps'] ?? [];
        $targetAccountId = !empty($payload['target_account_id']) ? (int)$payload['target_account_id'] : null;

        if (empty($sweeps)) {
            return $this->json(['error' => 'Aucun montant sélectionné'], 400);
        }

        $results = $this->savingsService->sweepToSavings($user, $sweeps, $targetAccountId);

        if (!$results['success']) {
            return $this->json(['error' => $results['message']], 400);
        }

        return $this->json([
            'success' => true,
            'message' => $results['message'],
            'total_swept' => $results['total_swept']
        ]);
    }
}
