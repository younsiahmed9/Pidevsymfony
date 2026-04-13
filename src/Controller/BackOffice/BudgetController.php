<?php

namespace App\Controller\BackOffice;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/budget', name: 'admin_budget_')]
final class BudgetController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $budgets = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT b.*,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM budget b
             INNER JOIN users u ON u.id = b.user_id
             ORDER BY b.id_budget DESC'
        );

        return $this->render('backoffice/budget/index.html.twig', [
            'budgets' => $budgets,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $entityManager->getConnection()->insert('budget', [
                'user_id' => $data['user_id'] ?? 1,
                'nom_budget' => $data['nom_budget'] ?? '',
                'montant_total' => $data['montant_total'] ?? 0,
                'periode' => $data['periode'] ?? '',
                'statut' => $data['statut'] ?? 'actif',
                'date_creation' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return $this->redirectToRoute('admin_budget_index');
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

        return $this->render('backoffice/budget/new.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM budget WHERE id_budget = :id',
            ['id' => $id]
        );

        if (!$budget) {
            throw $this->createNotFoundException('Budget introuvable.');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $entityManager->getConnection()->update('budget', [
                'user_id' => $data['user_id'] ?? $budget['user_id'],
                'nom_budget' => $data['nom_budget'] ?? '',
                'montant_total' => $data['montant_total'] ?? 0,
                'periode' => $data['periode'] ?? '',
                'statut' => $data['statut'] ?? 'actif',
            ], ['id_budget' => $id]);

            return $this->redirectToRoute('admin_budget_index');
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

        return $this->render('backoffice/budget/edit.html.twig', [
            'budget' => $budget,
            'users' => $users,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $budget = $entityManager->getConnection()->fetchAssociative(
            'SELECT b.*,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM budget b
             INNER JOIN users u ON u.id = b.user_id
             WHERE b.id_budget = :id',
            ['id' => $id]
        );

        if (!$budget) {
            throw $this->createNotFoundException('Budget introuvable.');
        }

        // Fetch associated expenses
        $depenses = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM depense WHERE id_budget = :budget_id ORDER BY id_depense DESC',
            ['budget_id' => $id]
        );

        return $this->render('backoffice/budget/show.html.twig', [
            'budget' => $budget,
            'depenses' => $depenses,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM budget WHERE id_budget = :id',
                ['id' => $id]
            );
        }

        return $this->redirectToRoute('admin_budget_index');
    }
}
