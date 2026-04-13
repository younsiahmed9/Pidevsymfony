<?php

namespace App\Controller\BackOffice;

use App\Form\AdminDepenseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/depense', name: 'admin_depense_')]
final class DepenseController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $depenses = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT d.*, b.nom_budget,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM depense d
             INNER JOIN users u ON u.id = d.user_id
             LEFT JOIN budget b ON b.id_budget = d.id_budget
             ORDER BY d.id_depense DESC'
        );

        return $this->render('backoffice/depense/index.html.twig', [
            'depenses' => $depenses,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT
                id,
                TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", -1)) AS nom,
                TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", 1)) AS prenom,
                email
             FROM users
             ORDER BY full_name ASC, email ASC'
        );

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_budget, nom_budget FROM budget ORDER BY nom_budget ASC'
        );

        $depenseData = [
            'user_id' => '',
            'id_budget' => '',
            'categorie' => '',
            'montant' => '',
            'date_depense' => (new \DateTime())->format('Y-m-d'),
            'description' => '',
            'mode_paiement' => 'virement',
        ];

        $form = $this->createForm(AdminDepenseType::class, $depenseData, [
            'user_choices' => $this->mapUserChoices($users),
            'budget_choices' => $this->mapBudgetChoices($budgets),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $entityManager->getConnection()->insert('depense', [
                'user_id' => (int) $data['user_id'],
                'id_budget' => !empty($data['id_budget']) ? (int) $data['id_budget'] : null,
                'categorie' => $data['categorie'],
                'montant' => $data['montant'],
                'date_depense' => $data['date_depense'] ?: (new \DateTime())->format('Y-m-d'),
                'description' => $data['description'] ?? '',
                'mode_paiement' => $data['mode_paiement'],
            ]);

            return $this->redirectToRoute('admin_depense_index');
        }

        return $this->render('backoffice/depense/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM depense WHERE id_depense = :id',
            ['id' => $id]
        );

        if (!$depense) {
            throw $this->createNotFoundException('Dépense introuvable.');
        }

        $users = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT
                id,
                TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", -1)) AS nom,
                TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", 1)) AS prenom,
                email
             FROM users
             ORDER BY full_name ASC, email ASC'
        );

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_budget, nom_budget FROM budget ORDER BY nom_budget ASC'
        );

        $form = $this->createForm(AdminDepenseType::class, $depense, [
            'user_choices' => $this->mapUserChoices($users),
            'budget_choices' => $this->mapBudgetChoices($budgets),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $entityManager->getConnection()->update('depense', [
                'user_id' => (int) $data['user_id'],
                'id_budget' => !empty($data['id_budget']) ? (int) $data['id_budget'] : null,
                'categorie' => $data['categorie'],
                'montant' => $data['montant'],
                'date_depense' => $data['date_depense'] ?: $depense['date_depense'],
                'description' => $data['description'] ?? '',
                'mode_paiement' => $data['mode_paiement'],
            ], ['id_depense' => $id]);

            return $this->redirectToRoute('admin_depense_index');
        }

        return $this->render('backoffice/depense/edit.html.twig', [
            'depense' => $depense,
            'form' => $form->createView(),
        ]);
    }

    private function mapUserChoices(array $users): array
    {
        $choices = [];
        foreach ($users as $user) {
            $label = trim(sprintf('%s %s (%s)', (string) ($user['prenom'] ?? ''), (string) ($user['nom'] ?? ''), (string) ($user['email'] ?? '')));
            $choices[$label] = (int) ($user['id'] ?? 0);
        }

        return $choices;
    }

    private function mapBudgetChoices(array $budgets): array
    {
        $choices = [];
        foreach ($budgets as $budget) {
            $choices[(string) ($budget['nom_budget'] ?? 'Budget')] = (int) ($budget['id_budget'] ?? 0);
        }

        return $choices;
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $depense = $entityManager->getConnection()->fetchAssociative(
            'SELECT d.*, b.nom_budget,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM depense d
             INNER JOIN users u ON u.id = d.user_id
             LEFT JOIN budget b ON b.id_budget = d.id_budget
             WHERE d.id_depense = :id',
            ['id' => $id]
        );

        if (!$depense) {
            throw $this->createNotFoundException('Dépense introuvable.');
        }

        return $this->render('backoffice/depense/show.html.twig', [
            'depense' => $depense,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM depense WHERE id_depense = :id',
                ['id' => $id]
            );
        }

        return $this->redirectToRoute('admin_depense_index');
    }
}
