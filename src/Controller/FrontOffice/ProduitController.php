<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/produit', name: 'produit_')]
final class ProduitController extends AbstractController
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

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, user_id, nom_produit AS nomProduit, montant, code_unique AS codeUnique, type_produit AS typeProduit, statut, date_creation AS dateCreation FROM produit WHERE user_id = :user_id ORDER BY id_produit DESC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/produit/index.html.twig', [
            'produits' => $produits,
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

        $produitFormData = [
            'nomProduit' => '',
            'montant' => '',
            'codeUnique' => '',
            'typeProduit' => '',
            'statut' => 'disponible',
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $produitFormData = [
                'nomProduit' => trim((string) $request->request->get('nomProduit', '')),
                'montant' => trim((string) $request->request->get('montant', '')),
                'codeUnique' => trim((string) $request->request->get('codeUnique', '')),
                'typeProduit' => trim((string) $request->request->get('typeProduit', '')),
                'statut' => trim((string) $request->request->get('statut', 'disponible')),
            ];

            $formErrors = $this->validateProduitInput($produitFormData);

            if ($formErrors === []) {
                $entityManager->getConnection()->insert('produit', [
                    'user_id' => $user->getId(),
                    'nom_produit' => $produitFormData['nomProduit'],
                    'montant' => number_format((float) str_replace(',', '.', $produitFormData['montant']), 2, '.', ''),
                    'code_unique' => $produitFormData['codeUnique'] !== '' ? $produitFormData['codeUnique'] : uniqid('PRD-'),
                    'type_produit' => $produitFormData['typeProduit'],
                    'statut' => $produitFormData['statut'],
                    'date_creation' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'Produit créé avec succès.');
                return $this->redirectToRoute('produit_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/produit/new.html.twig', [
            'produit' => $produitFormData,
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

        $produit = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_produit AS id, user_id, nom_produit AS nomProduit, montant, code_unique AS codeUnique, type_produit AS typeProduit, statut, date_creation AS dateCreation FROM produit WHERE id_produit = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $produitFormData = [
            'id' => (string) ($produit['id'] ?? $id),
            'nomProduit' => (string) ($produit['nomProduit'] ?? ''),
            'montant' => (string) ($produit['montant'] ?? ''),
            'codeUnique' => (string) ($produit['codeUnique'] ?? ''),
            'typeProduit' => (string) ($produit['typeProduit'] ?? ''),
            'statut' => (string) ($produit['statut'] ?? 'disponible'),
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $produitFormData = [
                'id' => (string) ($produit['id'] ?? $id),
                'nomProduit' => trim((string) $request->request->get('nomProduit', '')),
                'montant' => trim((string) $request->request->get('montant', '')),
                'codeUnique' => trim((string) $request->request->get('codeUnique', $produitFormData['codeUnique'])),
                'typeProduit' => trim((string) $request->request->get('typeProduit', '')),
                'statut' => trim((string) $request->request->get('statut', 'disponible')),
            ];

            $formErrors = $this->validateProduitInput($produitFormData);

            if ($formErrors === []) {
                $entityManager->getConnection()->update('produit', [
                    'nom_produit' => $produitFormData['nomProduit'],
                    'montant' => number_format((float) str_replace(',', '.', $produitFormData['montant']), 2, '.', ''),
                    'code_unique' => $produitFormData['codeUnique'] !== '' ? $produitFormData['codeUnique'] : $produit['codeUnique'],
                    'type_produit' => $produitFormData['typeProduit'],
                    'statut' => $produitFormData['statut'],
                ], ['id_produit' => $id, 'user_id' => $user->getId()]);

                $this->addFlash('success', 'Produit mis à jour avec succès.');
                return $this->redirectToRoute('produit_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/produit/edit.html.twig', [
            'produit' => $produitFormData,
            'formErrors' => $formErrors,
        ]);
    }

    private function validateProduitInput(array $data): array
    {
        $errors = [];

        if ($data['nomProduit'] === '' || mb_strlen($data['nomProduit']) < 2) {
            $errors['nomProduit'][] = 'Le nom du produit est obligatoire (minimum 2 caractères).';
        }

        $montant = str_replace(',', '.', (string) $data['montant']);
        if ($montant === '' || !is_numeric($montant) || (float) $montant <= 0) {
            $errors['montant'][] = 'Le montant doit être un nombre supérieur à 0.';
        }

        if ($data['typeProduit'] === '' || mb_strlen($data['typeProduit']) < 2) {
            $errors['typeProduit'][] = 'Le type de produit est obligatoire.';
        }

        if ($data['codeUnique'] !== '' && !preg_match('/^[A-Za-z0-9\-_]{3,60}$/', $data['codeUnique'])) {
            $errors['codeUnique'][] = 'Le code unique doit contenir 3 à 60 caractères alphanumériques, tirets ou underscores.';
        }

        if (!in_array($data['statut'], ['disponible', 'vendu', 'hors_service', 'en_reparation'], true)) {
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

        $produit = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_produit AS id, user_id, nom_produit AS nomProduit, montant, code_unique AS codeUnique, type_produit AS typeProduit, statut, date_creation AS dateCreation FROM produit WHERE id_produit = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('frontoffice/produit/show.html.twig', [
            'produit' => $produit,
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
                'DELETE FROM produit WHERE id_produit = :id AND user_id = :user_id',
                ['id' => $id, 'user_id' => $user->getId()]
            );
        }

        return $this->redirectToRoute('produit_index');
    }
}
