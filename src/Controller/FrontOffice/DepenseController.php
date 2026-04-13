<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/depense', name: 'depense_')]
final class DepenseController extends AbstractController
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

        $depenses = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT d.*, b.nom_budget
             FROM depense d
             LEFT JOIN budget b ON b.id_budget = d.id_budget
             WHERE d.user_id = :user_id
             ORDER BY d.date_depense DESC',
            ['user_id' => $user->getId()]
        );

        $totalAmount = 0.0;
        foreach ($depenses as $depense) {
            $totalAmount += (float) ($depense['montant'] ?? 0);
        }

        $avgAmount = count($depenses) > 0 ? $totalAmount / count($depenses) : 0.0;

        return $this->render('frontoffice/depense/index.html.twig', [
            'depenses' => $depenses,
            'totalAmount' => $totalAmount,
            'avgAmount' => $avgAmount,
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

        $depenseFormData = [
            'id_depense' => '',
            'id_budget' => '',
            'categorie' => '',
            'montant' => '',
            'date_depense' => (new \DateTime())->format('Y-m-d'),
            'description' => '',
            'mode_paiement' => 'virement',
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $depenseFormData = [
                'id_depense' => '',
                'id_budget' => trim((string) $request->request->get('id_budget', '')),
                'categorie' => trim((string) $request->request->get('categorie', '')),
                'montant' => trim((string) $request->request->get('montant', '')),
                'date_depense' => trim((string) $request->request->get('date_depense', (new \DateTime())->format('Y-m-d'))),
                'description' => trim((string) $request->request->get('description', '')),
                'mode_paiement' => trim((string) $request->request->get('mode_paiement', 'virement')),
            ];

            $formErrors = $this->validateDepenseInput($depenseFormData, $entityManager, (int) $user->getId());

            if ($formErrors === []) {
                $entityManager->getConnection()->insert('depense', [
                    'user_id' => $user->getId(),
                    'id_budget' => (int) $depenseFormData['id_budget'],
                    'categorie' => $depenseFormData['categorie'],
                    'montant' => number_format((float) str_replace(',', '.', $depenseFormData['montant']), 2, '.', ''),
                    'date_depense' => $depenseFormData['date_depense'],
                    'description' => $depenseFormData['description'],
                    'mode_paiement' => $depenseFormData['mode_paiement'],
                ]);

                $this->addFlash('success', 'Dépense enregistrée avec succès.');
                return $this->redirectToRoute('depense_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_budget, nom_budget FROM budget WHERE user_id = :user_id ORDER BY nom_budget ASC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/depense/new.html.twig', [
            'budgets' => $budgets,
            'depense' => $depenseFormData,
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

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM depense WHERE id_depense = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$depense) {
            throw $this->createNotFoundException('Dépense introuvable.');
        }

        $depenseFormData = [
            'id_depense' => (string) ($depense['id_depense'] ?? $id),
            'id_budget' => (string) ($depense['id_budget'] ?? ''),
            'categorie' => (string) ($depense['categorie'] ?? ''),
            'montant' => (string) ($depense['montant'] ?? ''),
            'date_depense' => (string) ($depense['date_depense'] ?? (new \DateTime())->format('Y-m-d')),
            'description' => (string) ($depense['description'] ?? ''),
            'mode_paiement' => (string) ($depense['mode_paiement'] ?? 'virement'),
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $depenseFormData = [
                'id_depense' => (string) ($depense['id_depense'] ?? $id),
                'id_budget' => trim((string) $request->request->get('id_budget', '')),
                'categorie' => trim((string) $request->request->get('categorie', '')),
                'montant' => trim((string) $request->request->get('montant', '')),
                'date_depense' => trim((string) $request->request->get('date_depense', $depenseFormData['date_depense'])),
                'description' => trim((string) $request->request->get('description', '')),
                'mode_paiement' => trim((string) $request->request->get('mode_paiement', 'virement')),
            ];

            $formErrors = $this->validateDepenseInput($depenseFormData, $entityManager, (int) $user->getId());

            if ($formErrors === []) {
                $entityManager->getConnection()->update('depense', [
                    'id_budget' => (int) $depenseFormData['id_budget'],
                    'categorie' => $depenseFormData['categorie'],
                    'montant' => number_format((float) str_replace(',', '.', $depenseFormData['montant']), 2, '.', ''),
                    'date_depense' => $depenseFormData['date_depense'],
                    'description' => $depenseFormData['description'],
                    'mode_paiement' => $depenseFormData['mode_paiement'],
                ], ['id_depense' => $id, 'user_id' => $user->getId()]);

                $this->addFlash('success', 'Dépense mise à jour avec succès.');
                return $this->redirectToRoute('depense_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_budget, nom_budget FROM budget WHERE user_id = :user_id ORDER BY nom_budget ASC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/depense/edit.html.twig', [
            'depense' => $depenseFormData,
            'budgets' => $budgets,
            'formErrors' => $formErrors,
        ]);
    }

    private function validateDepenseInput(array $data, EntityManagerInterface $entityManager, int $userId): array
    {
        $errors = [];

        if ($data['categorie'] === '' || mb_strlen($data['categorie']) < 2) {
            $errors['categorie'][] = 'La catégorie est obligatoire (minimum 2 caractères).';
        }

        $montant = str_replace(',', '.', (string) $data['montant']);
        if ($montant === '' || !is_numeric($montant) || (float) $montant <= 0) {
            $errors['montant'][] = 'Le montant doit être un nombre supérieur à 0.';
        }

        $dateDepense = \DateTime::createFromFormat('Y-m-d', (string) $data['date_depense']);
        if (!$dateDepense || $dateDepense->format('Y-m-d') !== $data['date_depense']) {
            $errors['date_depense'][] = 'La date de dépense est invalide.';
        }

        if (!in_array($data['mode_paiement'], ['virement', 'espece', 'carte'], true)) {
            $errors['mode_paiement'][] = 'Le mode de paiement sélectionné est invalide.';
        }

        if ($data['description'] !== '' && mb_strlen($data['description']) > 500) {
            $errors['description'][] = 'La description ne doit pas dépasser 500 caractères.';
        }

        if ($data['id_budget'] === '') {
            $errors['id_budget'][] = 'Vous devez choisir un budget pour cette dépense.';
        } elseif (!ctype_digit($data['id_budget'])) {
            $errors['id_budget'][] = 'Le budget sélectionné est invalide.';
        } else {
            $budget = $entityManager->getConnection()->fetchOne(
                'SELECT id_budget FROM budget WHERE id_budget = :id AND user_id = :user_id',
                ['id' => (int) $data['id_budget'], 'user_id' => $userId]
            );

            if (!$budget) {
                $errors['id_budget'][] = 'Le budget sélectionné ne vous appartient pas.';
            }
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

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT d.*, b.nom_budget FROM depense d LEFT JOIN budget b ON b.id_budget = d.id_budget WHERE d.id_depense = :id AND d.user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$depense) {
            throw $this->createNotFoundException('Dépense introuvable.');
        }

        return $this->render('frontoffice/depense/show.html.twig', [
            'depense' => $depense,
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
                'DELETE FROM depense WHERE id_depense = :id AND user_id = :user_id',
                ['id' => $id, 'user_id' => $user->getId()]
            );
        }

        return $this->redirectToRoute('depense_index');
    }
}
