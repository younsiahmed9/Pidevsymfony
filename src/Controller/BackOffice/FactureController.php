<?php

namespace App\Controller\BackOffice;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/facture', name: 'admin_facture_')]
final class FactureController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $factures = $entityManager->getConnection()->fetchAllAssociative(
              'SELECT f.id_facture AS id, f.user_id, f.montant,
                    f.date_facture AS dateFacture, f.date_echeance AS dateEcheance,
                    f.id_service AS service, f.id_produit AS produit,
                    f.statut, f.numero_facture AS numeroFacture,
                  TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                  TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                  u.email AS user_email
             FROM facture f
             INNER JOIN users u ON u.id = f.user_id
               ORDER BY f.id_facture DESC'
        );

        return $this->render('backoffice/facture/index.html.twig', [
            'factures' => $factures,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $dateFacture = !empty($data['dateFacture']) ? $data['dateFacture'] : (new \DateTime())->format('Y-m-d');
            $dateEcheance = !empty($data['dateEcheance']) ? $data['dateEcheance'] : $dateFacture;

            $entityManager->getConnection()->insert('facture', [
                'user_id' => $data['user_id'] ?? 1,
                'montant' => $data['montant'] ?? 0,
                'date_facture' => $dateFacture,
                'date_echeance' => $dateEcheance,
                'id_service' => !empty($data['service']) && ctype_digit((string) $data['service']) ? (int) $data['service'] : null,
                'id_produit' => !empty($data['produit']) && ctype_digit((string) $data['produit']) ? (int) $data['produit'] : null,
                'statut' => $data['statut'] ?? 'non_payee',
                'numero_facture' => $data['numeroFacture'] ?? 'FAC-' . strtoupper(bin2hex(random_bytes(4))),
            ]);

            return $this->redirectToRoute('admin_facture_index');
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

        return $this->render('backoffice/facture/new.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $facture = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_facture AS id, user_id, montant, date_facture AS dateFacture, date_echeance AS dateEcheance, id_service AS service, id_produit AS produit, statut, numero_facture AS numeroFacture
             FROM facture
             WHERE id_facture = :id',
            ['id' => $id]
        );

        if (!$facture) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $dateFacture = !empty($data['dateFacture']) ? $data['dateFacture'] : $facture['dateFacture'];
            $dateEcheance = !empty($data['dateEcheance']) ? $data['dateEcheance'] : ($facture['dateEcheance'] ?? $dateFacture);

            $entityManager->getConnection()->update('facture', [
                'user_id' => $data['user_id'] ?? $facture['user_id'],
                'montant' => $data['montant'] ?? 0,
                'date_facture' => $dateFacture,
                'date_echeance' => $dateEcheance,
                'id_service' => !empty($data['service']) && ctype_digit((string) $data['service']) ? (int) $data['service'] : null,
                'id_produit' => !empty($data['produit']) && ctype_digit((string) $data['produit']) ? (int) $data['produit'] : null,
                'statut' => $data['statut'] ?? 'non_payee',
                'numero_facture' => $data['numeroFacture'] ?? $facture['numeroFacture'],
            ], ['id_facture' => $id]);

            return $this->redirectToRoute('admin_facture_index');
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

        return $this->render('backoffice/facture/edit.html.twig', [
            'facture' => $facture,
            'users' => $users,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $facture = $entityManager->getConnection()->fetchAssociative(
            'SELECT f.id_facture AS id, f.user_id, f.montant,
                    f.date_facture AS dateFacture, f.date_echeance AS dateEcheance,
                    f.id_service AS service, f.id_produit AS produit,
                    f.statut, f.numero_facture AS numeroFacture,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM facture f
             INNER JOIN users u ON u.id = f.user_id
             WHERE f.id_facture = :id',
            ['id' => $id]
        );

        if (!$facture) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('backoffice/facture/show.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM facture WHERE id_facture = :id',
                ['id' => $id]
            );
        }

        return $this->redirectToRoute('admin_facture_index');
    }
}
