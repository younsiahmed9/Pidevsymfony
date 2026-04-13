<?php

namespace App\Controller\BackOffice;

use App\Form\AdminProduitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/produit', name: 'admin_produit_')]
final class ProduitController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $produits = $entityManager->getConnection()->fetchAllAssociative(
              'SELECT p.id_produit AS id, p.user_id,
                    p.nom_produit AS nomProduit, p.montant,
                    p.code_unique AS codeUnique, p.type_produit AS typeProduit,
                    p.statut, p.date_creation AS dateCreation,
                  TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                  TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                  u.email AS user_email
             FROM produit p
             INNER JOIN users u ON u.id = p.user_id
               ORDER BY p.id_produit DESC'
        );

        return $this->render('backoffice/produit/index.html.twig', [
            'produits' => $produits,
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

        $produitData = [
            'user_id' => '',
            'nomProduit' => '',
            'montant' => '',
            'codeUnique' => '',
            'typeProduit' => '',
            'statut' => 'disponible',
        ];

        $form = $this->createForm(AdminProduitType::class, $produitData, [
            'user_choices' => $this->mapUserChoices($users),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $entityManager->getConnection()->insert('produit', [
                'user_id' => (int) $data['user_id'],
                'nom_produit' => $data['nomProduit'],
                'montant' => $data['montant'],
                'code_unique' => $data['codeUnique'] !== '' ? $data['codeUnique'] : uniqid('PRD-'),
                'type_produit' => $data['typeProduit'],
                'statut' => $data['statut'],
                'date_creation' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return $this->redirectToRoute('admin_produit_index');
        }

        return $this->render('backoffice/produit/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $produit = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_produit AS id, user_id, nom_produit AS nomProduit, montant, code_unique AS codeUnique, type_produit AS typeProduit, statut, date_creation AS dateCreation FROM produit WHERE id_produit = :id',
            ['id' => $id]
        );

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
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

        $form = $this->createForm(AdminProduitType::class, $produit, [
            'user_choices' => $this->mapUserChoices($users),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $entityManager->getConnection()->update('produit', [
                'user_id' => (int) $data['user_id'],
                'nom_produit' => $data['nomProduit'],
                'montant' => $data['montant'],
                'code_unique' => $data['codeUnique'] !== '' ? $data['codeUnique'] : $produit['codeUnique'],
                'type_produit' => $data['typeProduit'],
                'statut' => $data['statut'],
            ], ['id_produit' => $id]);

            return $this->redirectToRoute('admin_produit_index');
        }

        return $this->render('backoffice/produit/edit.html.twig', [
            'produit' => $produit,
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

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $produit = $entityManager->getConnection()->fetchAssociative(
            'SELECT p.id_produit AS id, p.user_id,
                    p.nom_produit AS nomProduit, p.montant,
                    p.code_unique AS codeUnique, p.type_produit AS typeProduit,
                    p.statut, p.date_creation AS dateCreation,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM produit p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.id_produit = :id',
            ['id' => $id]
        );

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('backoffice/produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM produit WHERE id_produit = :id',
                ['id' => $id]
            );
        }

        return $this->redirectToRoute('admin_produit_index');
    }
}
