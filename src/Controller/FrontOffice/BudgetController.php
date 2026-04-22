<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Service\BudgetNotificationService;
use App\Service\BudgetManagementService;
use App\Service\SmsService;
use App\Service\VerificationService;


#[Route('/budget', name: 'budget_')]
final class BudgetController extends AbstractController
{
    private BudgetNotificationService $budgetNotificationService;
    private BudgetManagementService $budgetManagementService;
    private SmsService $smsService;
    private VerificationService $verificationService;

    public function __construct(
        BudgetNotificationService $budgetNotificationService,
        BudgetManagementService $budgetManagementService,
        SmsService $smsService,
        VerificationService $verificationService
    ) {
        $this->budgetNotificationService = $budgetNotificationService;
        $this->budgetManagementService = $budgetManagementService;
        $this->smsService = $smsService;
        $this->verificationService = $verificationService;
    }


    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_budget, nom_budget, montant_total, periode, statut, date_creation,
                    (SELECT COALESCE(SUM(montant), 0) FROM depense WHERE id_budget = budget.id_budget) as total_depense
             FROM budget
             WHERE user_id = :user_id
             ORDER BY id_budget DESC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/budget/index.html.twig', [
            'budgets' => $budgets,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budgetFormData = [
            'id_budget' => '',
            'nom_budget' => '',
            'montant_total' => '',
            'periode' => 'mensuel',
            'statut' => 'actif',
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $budgetFormData = [
                'id_budget' => '',
                'nom_budget' => trim((string) $request->request->get('nom_budget', '')),
                'montant_total' => trim((string) $request->request->get('montant_total', '')),
                'periode' => trim((string) $request->request->get('periode', 'mensuel')),
                'statut' => trim((string) $request->request->get('statut', 'actif')),
            ];

            $totalBalance = $this->budgetManagementService->getTotalUserBalance($user);
            $totalAllocated = $this->budgetManagementService->getTotalAllocatedBudget($user);
            $newAmount = (float) str_replace(',', '.', $budgetFormData['montant_total']);

            $formErrors = $this->validateBudgetInput($budgetFormData);

            if ($formErrors === [] && ($totalAllocated + $newAmount) > $totalBalance) {
                $available = max(0, $totalBalance - $totalAllocated);
                $formErrors['montant_total'][] = sprintf(
                    "Capacité insuffisante ! Votre solde total est de %.2f TND. Il vous reste %.2f TND à allouer.",
                    $totalBalance,
                    $available
                );
                
                // On ajoute un flag spécial pour le template
                $overflowContext = [
                    'available_balance' => $totalBalance,
                    'remaining_allocation' => $available,
                    'overflow_amount' => ($totalAllocated + $newAmount) - $totalBalance,
                    'budgets' => $this->budgetManagementService->getAvailableBudgetsForReallocation($user)
                ];
            }


            if ($formErrors === []) {
                $entityManager->getConnection()->insert('budget', [
                    'user_id' => $user->getId(),
                    'nom_budget' => $budgetFormData['nom_budget'],
                    'montant_total' => number_format((float) str_replace(',', '.', $budgetFormData['montant_total']), 2, '.', ''),
                    'periode' => $budgetFormData['periode'],
                    'statut' => $budgetFormData['statut'],
                    'date_creation' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'Budget créé avec succès.');
                return $this->redirectToRoute('budget_index');
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'valid' => false,
                    'errors' => $formErrors,
                ], 422);
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/budget/new.html.twig', [
            'budget' => $budgetFormData,
            'formErrors' => $formErrors,
            'overflowContext' => $overflowContext ?? null,
        ]);

    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT b.*, 
                    (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) AS total_depense
             FROM budget b
             WHERE b.id_budget = :id AND b.user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            throw $this->createNotFoundException('Budget introuvable.');
        }

        $budgetFormData = [
            'id_budget' => (string) ($budget['id_budget'] ?? ''),
            'nom_budget' => (string) ($budget['nom_budget'] ?? ''),
            'montant_total' => (string) ($budget['montant_total'] ?? ''),
            'periode' => (string) ($budget['periode'] ?? 'mensuel'),
            'statut' => (string) ($budget['statut'] ?? 'actif'),
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $budgetFormData = [
                'id_budget' => (string) ($budget['id_budget'] ?? ''),
                'nom_budget' => trim((string) $request->request->get('nom_budget', '')),
                'montant_total' => trim((string) $request->request->get('montant_total', '')),
                'periode' => trim((string) $request->request->get('periode', 'mensuel')),
                'statut' => trim((string) $request->request->get('statut', 'actif')),
            ];

            $formErrors = $this->validateBudgetInput($budgetFormData);

            if ($formErrors === []) {
                $entityManager->getConnection()->update('budget', [
                    'nom_budget' => $budgetFormData['nom_budget'],
                    'montant_total' => number_format((float) str_replace(',', '.', $budgetFormData['montant_total']), 2, '.', ''),
                    'periode' => $budgetFormData['periode'],
                    'statut' => $budgetFormData['statut'],
                ], ['id_budget' => $id, 'user_id' => $user->getId()]);

                $this->addFlash('success', 'Budget mis à jour avec succès.');
                
                // Vérifier le seuil du budget après modification du montant total
                $this->budgetNotificationService->checkAndNotify($id, $user);

                return $this->redirectToRoute('budget_index');

            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'valid' => false,
                    'errors' => $formErrors,
                ], 422);
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/budget/edit.html.twig', [
            'budget' => $budgetFormData,
            'budgetMeta' => [
                'id_budget' => $budget['id_budget'],
                'nom_budget' => $budget['nom_budget'],
            ],
            'formErrors' => $formErrors,
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Vous devez être connecté.']]], 401);
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budgetFormData = [
            'id_budget' => trim((string) $request->request->get('id_budget', '')),
            'nom_budget' => trim((string) $request->request->get('nom_budget', '')),
            'montant_total' => trim((string) $request->request->get('montant_total', '')),
            'periode' => trim((string) $request->request->get('periode', 'mensuel')),
            'statut' => trim((string) $request->request->get('statut', 'actif')),
        ];

        $formErrors = $this->validateBudgetInput($budgetFormData);

        if (
            $budgetFormData['nom_budget'] === '' &&
            $budgetFormData['montant_total'] === ''
        ) {
            $formErrors['general'][] = 'Les champs obligatoires ne peuvent pas être vides.';
        }

        if ($formErrors === []) {
            $totalBalance = $this->budgetManagementService->getTotalUserBalance($user);
            $excludeId = $budgetFormData['id_budget'] !== '' ? (int)$budgetFormData['id_budget'] : null;
            $totalAllocated = $this->budgetManagementService->getTotalAllocatedBudget($user, $excludeId);
            $newAmount = (float) str_replace(',', '.', $budgetFormData['montant_total']);

            if (($totalAllocated + $newAmount) > $totalBalance) {
                $available = max(0, $totalBalance - $totalAllocated);
                $formErrors['montant_total'][] = sprintf(
                    "Capacité insuffisante (Solde: %.2f TND, Dispo: %.2f TND).",
                    $totalBalance,
                    $available
                );
            }
        }

        return $this->json([
            'valid' => $formErrors === [],
            'errors' => $formErrors,
            'overflow' => $formErrors['montant_total'] ?? null ? true : false,
            'available_budgets' => ($formErrors['montant_total'] ?? null) ? $this->budgetManagementService->getAvailableBudgetsForReallocation($user) : []
        ], $formErrors === [] ? 200 : 422);

    }

    private function validateBudgetInput(array $data): array
    {
        $errors = [];

        if ($data['nom_budget'] === '' || mb_strlen($data['nom_budget']) < 2) {
            $errors['nom_budget'][] = 'Le nom du budget est obligatoire (minimum 2 caractères).';
        } elseif (!preg_match('/[A-Za-zÀ-ÿ]/u', $data['nom_budget'])) {
            $errors['nom_budget'][] = 'Le nom du budget doit contenir au moins une lettre.';
        } elseif (!preg_match('/^[\p{L}\d\s\-\']+$/u', $data['nom_budget'])) {
            $errors['nom_budget'][] = 'Le nom du budget contient des caractères non autorisés.';
        }

        $montant = preg_replace('/\s+/', '', str_replace(',', '.', (string) $data['montant_total']));
        if ($montant === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $montant) || (float) $montant <= 0) {
            $errors['montant_total'][] = 'Le montant limite doit être un nombre supérieur à 0.';
        }

        if (!in_array($data['periode'], ['mensuel', 'annuel', 'ponctuel'], true)) {
            $errors['periode'][] = 'La période sélectionnée est invalide.';
        }

        if (!in_array($data['statut'], ['actif', 'en_attente'], true)) {
            $errors['statut'][] = 'Le statut sélectionné est invalide.';
        }

        return $errors;
    }

    #[Route('/reallocate', name: 'reallocate', methods: ['POST'])]
    public function reallocate(Request $request, BudgetManagementService $budgetManagementService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        try {
            $fromId = (int) $request->request->get('from_id');
            $amountToTake = (float) $request->request->get('amount');
            $targetId = $request->request->get('id_budget') !== '' ? (int)$request->request->get('id_budget') : null;
            $nomBudget = trim((string)$request->request->get('nom_budget', ''));
            $montantTotalCible = (float)str_replace(',', '.', (string)$request->request->get('montant_total', '0'));
            $periode = $request->request->get('periode', 'mensuel');
            $statut = $request->request->get('statut', 'actif');

            if ($amountToTake <= 0) {
                return $this->json(['success' => false, 'error' => 'Le montant à prélever doit être supérieur à 0.'], 400);
            }

            if ($montantTotalCible <= 0) {
                return $this->json(['success' => false, 'error' => 'Le montant limite du budget doit être supérieur à 0.'], 400);
            }

            // 1. Validation de la capacité
            $totalBalance = $budgetManagementService->getTotalUserBalance($user);
            $totalAllocatedOthers = $budgetManagementService->getTotalAllocatedBudget($user, $targetId);
            
            $freeBalance = $totalBalance - $totalAllocatedOthers;
            $availableAfterReallocate = $freeBalance + $amountToTake;

            if ($availableAfterReallocate < $montantTotalCible) {
                return $this->json([
                    'success' => false, 
                    'error' => sprintf("Capacité insuffisante : même avec ce transfert, vous n'avez que %.2f TND disponibles pour un budget de %.2f TND.", $availableAfterReallocate, $montantTotalCible)
                ], 400);
            }

            // 2. Préparation des données pour la session
            $data = [
                'from_id' => $fromId,
                'amount' => $amountToTake,
                'target_id' => $targetId,
                'nom_budget' => $nomBudget,
                'montant_total' => $montantTotalCible,
                'periode' => $periode,
                'statut' => $statut,
            ];

            // 3. Initiation de la vérification (SMS)
            $code = $this->verificationService->initiateVerification($data);
            
            // On récupère le téléphone de l'utilisateur depuis son profil Client
            $client = $user->getClient();
            $userPhone = ($client && $client->getPhone()) ? $client->getPhone() : "+21699000000"; // Fallback vers numéro démo

            if ($this->smsService->sendSms($userPhone, "Votre code de transfert FinTrack est : $code")) {
                return $this->json([
                    'success' => true,
                    'needs_verification' => true,
                    'message' => 'Un code de vérification a été envoyé par SMS.'
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi du SMS. Veuillez réessayer.'
            ], 500);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur technique : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/reallocate/confirm', name: 'reallocate_confirm', methods: ['POST'])]
    public function reallocateConfirm(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $code = (string) $request->request->get('code');
        
        if (!$this->verificationService->verifyCode($code)) {
            return $this->json(['success' => false, 'error' => 'Code de vérification invalide ou expiré.'], 400);
        }

        $data = $this->verificationService->getStoredData();
        if (!$data) {
            return $this->json(['success' => false, 'error' => 'Données de transfert perdues. Veuillez recommencer.'], 400);
        }

        // 2. Exécution finale (étape 2)
        if (!$this->budgetManagementService->reallocate($data['from_id'], $data['amount'], $user)) {
            return $this->json(['success' => false, 'error' => 'Impossible de récupérer les fonds du budget source.'], 400);
        }

        $connection = $entityManager->getConnection();
        if ($data['target_id']) {
            $connection->update('budget', [
                'nom_budget' => $data['nom_budget'],
                'montant_total' => number_format($data['montant_total'], 2, '.', ''),
                'periode' => $data['periode'],
                'statut' => $data['statut'],
            ], ['id_budget' => $data['target_id'], 'user_id' => $user->getId()]);
        } else {
            $connection->insert('budget', [
                'user_id' => $user->getId(),
                'nom_budget' => $data['nom_budget'],
                'montant_total' => number_format($data['montant_total'], 2, '.', ''),
                'periode' => $data['periode'],
                'statut' => $data['statut'],
                'date_creation' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $this->verificationService->clearVerification();
        $this->addFlash('success', 'Transfert sécurisé effectué avec succès !');

        return $this->json([
            'success' => true, 
            'redirect' => $this->generateUrl('budget_index')
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT b.*, 
                    (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) AS total_depense
             FROM budget b
             WHERE b.id_budget = :id AND b.user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            throw $this->createNotFoundException('Budget introuvable.');
        }

        $depenses = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM depense WHERE id_budget = :budget_id ORDER BY date_depense DESC',
            ['budget_id' => $id]
        );

        return $this->render('frontoffice/budget/show.html.twig', [
            'budget' => $budget,
            'depenses' => $depenses,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM budget WHERE id_budget = :id AND user_id = :user_id',
                ['id' => $id, 'user_id' => $user->getId()]
            );
        }

        return $this->redirectToRoute('budget_index');
    }
}


