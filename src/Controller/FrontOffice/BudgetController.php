<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/budget', name: 'budget_')]
final class BudgetController extends AbstractController
{
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

            $formErrors = $this->validateBudgetInput($budgetFormData);

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

        return $this->json([
            'valid' => $formErrors === [],
            'errors' => $formErrors,
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
