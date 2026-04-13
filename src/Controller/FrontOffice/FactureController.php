<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/facture', name: 'facture_')]
final class FactureController extends AbstractController
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

        $factures = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT f.id_facture AS id,
                    f.montant,
                    f.date_facture AS dateFacture,
                    f.date_echeance AS dateEcheance,
                    f.id_service AS service,
                    f.id_produit AS produit,
                    f.statut,
                    f.numero_facture AS numeroFacture,
                    f.user_id,
                    s.nom_service AS serviceNom,
                    p.nom_produit AS produitNom
             FROM facture f
             LEFT JOIN service s ON s.id_service = f.id_service
             LEFT JOIN produit p ON p.id_produit = f.id_produit
             WHERE f.user_id = :user_id
             ORDER BY f.id_facture DESC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/facture/index.html.twig', [
            'factures' => $factures,
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

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, nom_service AS nomService
             FROM service
             WHERE user_id = :user_id
             ORDER BY nom_service ASC',
            ['user_id' => $user->getId()]
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit
             FROM produit
             WHERE user_id = :user_id
             ORDER BY nom_produit ASC',
            ['user_id' => $user->getId()]
        );

        $factureFormData = [
            'numeroFacture' => '',
            'montant' => '',
            'dateFacture' => (new \DateTime())->format('Y-m-d'),
            'dateEcheance' => '',
            'service' => '',
            'produit' => '',
            'statut' => 'non_payee',
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $factureFormData = [
                'numeroFacture' => trim((string) $request->request->get('numeroFacture', '')),
                'montant' => trim((string) $request->request->get('montant', '')),
                'dateFacture' => trim((string) $request->request->get('dateFacture', (new \DateTime())->format('Y-m-d'))),
                'dateEcheance' => trim((string) $request->request->get('dateEcheance', '')),
                'service' => trim((string) $request->request->get('service', '')),
                'produit' => trim((string) $request->request->get('produit', '')),
                'statut' => trim((string) $request->request->get('statut', 'non_payee')),
            ];

            $formErrors = $this->validateFactureInput($factureFormData, $entityManager, (int) $user->getId());

            if ($formErrors === []) {
                $entityManager->getConnection()->insert('facture', [
                    'user_id' => $user->getId(),
                    'montant' => number_format((float) str_replace(',', '.', $factureFormData['montant']), 2, '.', ''),
                    'date_facture' => $factureFormData['dateFacture'],
                    'date_echeance' => $factureFormData['dateEcheance'] !== '' ? $factureFormData['dateEcheance'] : $factureFormData['dateFacture'],
                    'id_service' => $factureFormData['service'] !== '' ? (int) $factureFormData['service'] : null,
                    'id_produit' => $factureFormData['produit'] !== '' ? (int) $factureFormData['produit'] : null,
                    'statut' => $factureFormData['statut'],
                    'numero_facture' => $factureFormData['numeroFacture'] !== '' ? $factureFormData['numeroFacture'] : 'FAC-' . strtoupper(bin2hex(random_bytes(4))),
                ]);

                $this->addFlash('success', 'Facture créée avec succès.');
                return $this->redirectToRoute('facture_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/facture/new.html.twig', [
            'facture' => $factureFormData,
            'services' => $services,
            'produits' => $produits,
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

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, nom_service AS nomService
             FROM service
             WHERE user_id = :user_id
             ORDER BY nom_service ASC',
            ['user_id' => $user->getId()]
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit
             FROM produit
             WHERE user_id = :user_id
             ORDER BY nom_produit ASC',
            ['user_id' => $user->getId()]
        );

        $facture = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_facture AS id, montant, date_facture AS dateFacture, date_echeance AS dateEcheance, id_service AS service, id_produit AS produit, statut, numero_facture AS numeroFacture, user_id
             FROM facture
             WHERE id_facture = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$facture) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        $factureFormData = [
            'id' => (string) ($facture['id'] ?? $id),
            'numeroFacture' => (string) ($facture['numeroFacture'] ?? ''),
            'montant' => (string) ($facture['montant'] ?? ''),
            'dateFacture' => (string) ($facture['dateFacture'] ?? (new \DateTime())->format('Y-m-d')),
            'dateEcheance' => (string) ($facture['dateEcheance'] ?? ''),
            'service' => (string) ($facture['service'] ?? ''),
            'produit' => (string) ($facture['produit'] ?? ''),
            'statut' => (string) ($facture['statut'] ?? 'non_payee'),
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $factureFormData = [
                'id' => (string) ($facture['id'] ?? $id),
                'numeroFacture' => trim((string) $request->request->get('numeroFacture', $factureFormData['numeroFacture'])),
                'montant' => trim((string) $request->request->get('montant', '')),
                'dateFacture' => trim((string) $request->request->get('dateFacture', $factureFormData['dateFacture'])),
                'dateEcheance' => trim((string) $request->request->get('dateEcheance', '')),
                'service' => trim((string) $request->request->get('service', '')),
                'produit' => trim((string) $request->request->get('produit', '')),
                'statut' => trim((string) $request->request->get('statut', 'non_payee')),
            ];

            $formErrors = $this->validateFactureInput($factureFormData, $entityManager, (int) $user->getId(), $id);

            if ($formErrors === []) {
                $entityManager->getConnection()->update('facture', [
                    'montant' => number_format((float) str_replace(',', '.', $factureFormData['montant']), 2, '.', ''),
                    'date_facture' => $factureFormData['dateFacture'],
                    'date_echeance' => $factureFormData['dateEcheance'] !== '' ? $factureFormData['dateEcheance'] : $factureFormData['dateFacture'],
                    'id_service' => $factureFormData['service'] !== '' ? (int) $factureFormData['service'] : null,
                    'id_produit' => $factureFormData['produit'] !== '' ? (int) $factureFormData['produit'] : null,
                    'statut' => $factureFormData['statut'],
                    'numero_facture' => $factureFormData['numeroFacture'] !== '' ? $factureFormData['numeroFacture'] : $facture['numeroFacture'],
                ], ['id_facture' => $id, 'user_id' => $user->getId()]);

                $this->addFlash('success', 'Facture mise à jour avec succès.');
                return $this->redirectToRoute('facture_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/facture/edit.html.twig', [
            'facture' => $factureFormData,
            'services' => $services,
            'produits' => $produits,
            'formErrors' => $formErrors,
        ]);
    }

    private function validateFactureInput(array $data, EntityManagerInterface $entityManager, int $userId, ?int $currentFactureId = null): array
    {
        $errors = [];

        $montant = str_replace(',', '.', (string) $data['montant']);
        if ($montant === '' || !is_numeric($montant) || (float) $montant <= 0) {
            $errors['montant'][] = 'Le montant doit être un nombre supérieur à 0.';
        }

        if (!in_array($data['statut'], ['non_payee', 'payee', 'en_retard'], true)) {
            $errors['statut'][] = 'Le statut sélectionné est invalide.';
        }

        if ($data['numeroFacture'] !== '' && !preg_match('/^[A-Za-z0-9\-]{3,40}$/', $data['numeroFacture'])) {
            $errors['numeroFacture'][] = 'Le numéro de facture est invalide.';
        }

        if ($data['numeroFacture'] !== '' && !isset($errors['numeroFacture'])) {
            $query = 'SELECT id_facture FROM facture WHERE numero_facture = :numero';
            $params = ['numero' => $data['numeroFacture']];

            if ($currentFactureId !== null) {
                $query .= ' AND id_facture <> :current_id';
                $params['current_id'] = $currentFactureId;
            }

            $existingFactureId = $entityManager->getConnection()->fetchOne($query, $params);
            if ($existingFactureId) {
                $errors['numeroFacture'][] = 'Ce numéro de facture existe déjà. Veuillez choisir une référence unique.';
            }
        }

        $dateFacture = \DateTime::createFromFormat('Y-m-d', (string) $data['dateFacture']);
        if (!$dateFacture || $dateFacture->format('Y-m-d') !== $data['dateFacture']) {
            $errors['dateFacture'][] = 'La date de facture est invalide.';
        }

        if ($data['dateEcheance'] !== '') {
            $dateEcheance = \DateTime::createFromFormat('Y-m-d', (string) $data['dateEcheance']);
            if (!$dateEcheance || $dateEcheance->format('Y-m-d') !== $data['dateEcheance']) {
                $errors['dateEcheance'][] = 'La date d\'échéance est invalide.';
            } elseif (!isset($errors['dateFacture']) && $dateEcheance < $dateFacture) {
                $errors['dateEcheance'][] = 'La date d\'échéance doit être postérieure à la date de facture.';
            }
        }

        if ($data['service'] !== '') {
            if (!ctype_digit($data['service'])) {
                $errors['service'][] = 'Le service sélectionné est invalide.';
            } else {
                $serviceExists = $entityManager->getConnection()->fetchOne(
                    'SELECT id_service FROM service WHERE id_service = :id AND user_id = :user_id',
                    ['id' => (int) $data['service'], 'user_id' => $userId]
                );
                if (!$serviceExists) {
                    $errors['service'][] = 'Le service sélectionné ne vous appartient pas.';
                }
            }
        }

        if ($data['produit'] !== '') {
            if (!ctype_digit($data['produit'])) {
                $errors['produit'][] = 'Le produit sélectionné est invalide.';
            } else {
                $produitExists = $entityManager->getConnection()->fetchOne(
                    'SELECT id_produit FROM produit WHERE id_produit = :id AND user_id = :user_id',
                    ['id' => (int) $data['produit'], 'user_id' => $userId]
                );
                if (!$produitExists) {
                    $errors['produit'][] = 'Le produit sélectionné ne vous appartient pas.';
                }
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

        $facture = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_facture AS id, montant, date_facture AS dateFacture, date_echeance AS dateEcheance, id_service AS service, id_produit AS produit, statut, numero_facture AS numeroFacture, user_id
             FROM facture
             WHERE id_facture = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$facture) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('frontoffice/facture/show.html.twig', [
            'facture' => $facture,
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
                'DELETE FROM facture WHERE id_facture = :id AND user_id = :user_id',
                ['id' => $id, 'user_id' => $user->getId()]
            );
        }

        return $this->redirectToRoute('facture_index');
    }
}
