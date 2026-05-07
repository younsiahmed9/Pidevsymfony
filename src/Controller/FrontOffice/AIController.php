<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\AIService;
use App\Service\BudgetManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/depense/ai', name: 'depense_ai_')]
class AIController extends AbstractController
{
    /**
     * Endpoint pour tester la clé API Gemini (Debug).
     */
    #[Route('/debug/test-key', name: 'debug_test_key', methods: ['GET'])]
    public function debugTestKey(AIService $aiService): JsonResponse
    {
        // Optionnel : Restreindre cet accès aux administrateurs uniquement
        // $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $aiService->testApiKey();
        return $this->json($result);
    }

    /**
     * Endpoint pour récupérer les conseils financiers générés par l'IA.
     */
    #[Route('/advice', name: 'advice', methods: ['POST'])]
    public function getAdvice(
        EntityManagerInterface $entityManager,
        AIService $aiService,
        BudgetManagementService $budgetManagementService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Vous devez être connecté pour accéder au conseiller IA.'], 401);
        }

        // 1. Récupération des dernières transactions (30 max pour le contexte)
        $expenses = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT d.* FROM depense d WHERE d.user_id = :user_id ORDER BY d.date_depense DESC LIMIT 30',
            ['user_id' => $user->getId()]
        );

        // 2. Récupération des budgets actifs
        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT b.* FROM budget b WHERE b.user_id = :user_id AND b.statut = "actif"',
            ['user_id' => $user->getId()]
        );

        // 3. Récupération du solde total via le service de gestion budgétaire
        $totalBalance = $budgetManagementService->getTotalUserBalance($user);

        // 4. Appel du service IA pour générer le conseil personnalisé
        $advice = $aiService->getFinancialAdvice($expenses, $budgets, $totalBalance);

        return $this->json([
            'advice' => $advice
        ]);
    }
}
