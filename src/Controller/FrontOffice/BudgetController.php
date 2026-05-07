<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\BudgetManagementService;
use App\Service\SmsService;
use App\Service\VerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/budget', name: 'budget_')]
final class BudgetController extends AbstractController
{
    private BudgetManagementService $budgetService;
    private VerificationService $verificationService;
    private SmsService $smsService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        BudgetManagementService $budgetService,
        VerificationService $verificationService,
        SmsService $smsService,
        EntityManagerInterface $entityManager
    ) {
        $this->budgetService = $budgetService;
        $this->verificationService = $verificationService;
        $this->smsService = $smsService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budgets = $this->entityManager->getConnection()->fetchAllAssociative(
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
    public function new(Request $request): Response
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

            $formErrors = $this->validateBudgetInput($budgetFormData);

            // Calcul de Capacité : Si le montant dépasse le solde bancaire total
            $totalBalance = $this->budgetService->getTotalUserBalance($user);
            $totalAllocated = $this->budgetService->getTotalAllocatedBudget($user);
            $newAmount = (float) str_replace(',', '.', $budgetFormData['montant_total']);
            
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
                    'budgets' => $this->budgetService->getAvailableBudgetsForReallocation($user)
                ];
            }

            if ($formErrors === []) {
                $this->entityManager->getConnection()->insert('budget', [
                    'user_id' => $user->getId(),
                    'nom_budget' => $budgetFormData['nom_budget'],
                    'montant_total' => number_format($newAmount, 2, '.', ''),
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

    #[Route('/reallocate', name: 'reallocate', methods: ['POST'])]
    public function reallocate(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Vous devez être connecté.'], 401);
        }

        $fromId = (int) $request->request->get('from_id');
        $amount = (float) $request->request->get('amount');

        if ($fromId <= 0 || $amount <= 0) {
            return $this->json(['error' => 'Données de transfert invalides.'], 400);
        }

        // Initier la vérification 2FA
        $data = [
            'from_id' => $fromId,
            'amount' => $amount,
            'user_id' => $user->getId(),
            'pending_budget' => [
                'nom_budget' => $request->request->get('nom_budget'),
                'montant_total' => $request->request->get('montant_total'),
                'periode' => $request->request->get('periode'),
                'statut' => $request->request->get('statut'),
            ]
        ];

        $code = $this->verificationService->initiateVerification($data);
        
        // Envoyer le SMS via Twilio
        $message = "Votre code de vérification FinTrack pour la réallocation de budget est : $code. Ce code expire dans 10 minutes.";
        $phone = trim((string) ($user->getClient()?->getPhone() ?? ''));
        if ($phone === '') {
            return $this->json(['error' => 'Aucun numéro de téléphone renseigné sur votre profil.'], 400);
        }

        if (!$this->smsService->sendSms($phone, $message)) {
            return $this->json([
                'error' => $this->smsService->getLastFailureHintFr()
                    ?? 'Impossible d\'envoyer le SMS. Réessayez plus tard ou contactez le support.',
            ], 500);
        }

        return $this->json([
            'success' => true,
            'needs_verification' => true,
            'message' => 'Un code de vérification a été envoyé par SMS.',
        ]);
    }

    #[Route('/reallocate/confirm', name: 'reallocate_confirm', methods: ['POST'])]
    public function reallocateConfirm(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Vous devez être connecté.'], 401);
        }

        $code = (string) $request->request->get('code');
        
        if ($this->verificationService->verifyCode($code)) {
            $data = $this->verificationService->getStoredData();
            
            if ($data) {
                try {
                    $connection = $this->entityManager->getConnection();
                    $connection->beginTransaction();

                    // 1. Créer le nouveau budget
                    $budgetData = $data['pending_budget'];
                    $connection->insert('budget', [
                        'user_id' => $user->getId(),
                        'nom_budget' => $budgetData['nom_budget'],
                        'montant_total' => number_format((float) str_replace(',', '.', $budgetData['montant_total']), 2, '.', ''),
                        'periode' => $budgetData['periode'],
                        'statut' => $budgetData['statut'],
                        'date_creation' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]);
                    $newBudgetId = $connection->lastInsertId();

                    // 2. Réallouer les fonds depuis le budget source vers le nouveau budget
                    if ($this->budgetService->reallocate($data['from_id'], (int)$newBudgetId, $data['amount'], $user)) {
                        $connection->commit();
                        $this->verificationService->clearVerification();
                        
                        return $this->json([
                            'success' => true,
                            'redirect' => $this->generateUrl('budget_index')
                        ]);
                    }

                    $connection->rollBack();
                } catch (\Exception $e) {
                    if ($connection->isTransactionActive()) {
                        $connection->rollBack();
                    }
                    return $this->json(['error' => 'Erreur technique: ' . $e->getMessage()], 500);
                }
            }

            return $this->json(['error' => 'Erreur lors de la réallocation. Vérifiez les fonds disponibles.'], 400);
        }

        return $this->json(['error' => 'Code de vérification invalide ou expiré.'], 400);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $this->entityManager->getConnection()->fetchAssociative(
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
                $this->entityManager->getConnection()->update('budget', [
                    'nom_budget' => $budgetFormData['nom_budget'],
                    'montant_total' => number_format((float) str_replace(',', '.', $budgetFormData['montant_total']), 2, '.', ''),
                    'periode' => $budgetFormData['periode'],
                    'statut' => $budgetFormData['statut'],
                ], ['id_budget' => $id, 'user_id' => $user->getId()]);

                $this->addFlash('success', 'Budget mis à jour avec succès.');
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
            $totalBalance = $this->budgetService->getTotalUserBalance($user);
            $excludeId = $budgetFormData['id_budget'] !== '' ? (int)$budgetFormData['id_budget'] : null;
            $totalAllocated = $this->budgetService->getTotalAllocatedBudget($user, $excludeId);
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
            'available_budgets' => ($formErrors['montant_total'] ?? null) ? $this->budgetService->getAvailableBudgetsForReallocation($user) : []
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

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $budget = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT b.*, 
                    (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) AS total_depense
             FROM budget b
             WHERE b.id_budget = :id AND b.user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$budget) {
            throw $this->createNotFoundException('Budget introuvable.');
        }

        $depenses = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM depense WHERE id_budget = :budget_id ORDER BY date_depense DESC',
            ['budget_id' => $id]
        );

        return $this->render('frontoffice/budget/show.html.twig', [
            'budget' => $budget,
            'depenses' => $depenses,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $this->entityManager->getConnection()->executeStatement(
                'DELETE FROM budget WHERE id_budget = :id AND user_id = :user_id',
                ['id' => $id, 'user_id' => $user->getId()]
            );
        }

        return $this->redirectToRoute('budget_index');
    }
}
