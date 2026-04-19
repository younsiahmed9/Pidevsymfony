<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Entity\Produit;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Form\Exception\TransformationFailedException;

#[Route('/produit', name: 'produit_')]
final class ProduitController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        // Paramètres de recherche, filtre et tri
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $typeProduit = $request->query->get('typeProduit', '');
        $sortBy = $request->query->get('sortBy', 'id_produit');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        // Construction de la requête SQL
        $sql = 'SELECT id_produit AS id, user_id, nom_produit AS nomProduit, montant, 
                       code_unique AS codeUnique, type_produit AS typeProduit, statut, 
                       date_creation AS dateCreation 
                FROM produit 
                WHERE user_id = :user_id';
        
        $params = ['user_id' => $user->getId()];

        // Filtre de recherche
        if (!empty($search)) {
            $sql .= ' AND (nom_produit LIKE :search OR code_unique LIKE :search OR type_produit LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filtre par statut
        if (!empty($statut)) {
            $sql .= ' AND statut = :statut';
            $params['statut'] = $statut;
        }

        // Filtre par type de produit
        if (!empty($typeProduit)) {
            $sql .= ' AND type_produit = :typeProduit';
            $params['typeProduit'] = $typeProduit;
        }

        // Tri
        $allowedSortFields = ['id_produit', 'nom_produit', 'montant', 'code_unique', 'type_produit', 'statut', 'date_creation'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id_produit';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
        
        $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortOrder;

        $produits = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        // Statistiques pour l'utilisateur
        $stats = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN statut = \'disponible\' THEN 1 END) as disponibles,
                COUNT(CASE WHEN statut = \'indisponible\' THEN 1 END) as indisponibles,
                COUNT(CASE WHEN statut = \'archive\' THEN 1 END) as archives,
                COUNT(CASE WHEN type_produit = \'physique\' THEN 1 END) as physiques,
                COUNT(CASE WHEN type_produit = \'numerique\' THEN 1 END) as numeriques,
                COUNT(CASE WHEN type_produit = \'service\' THEN 1 END) as services,
                SUM(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE 0 END) as valeur_totale,
                AVG(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE NULL END) as prix_moyen
             FROM produit 
             WHERE user_id = :user_id',
            ['user_id' => $user->getId()]
        )[0];

        // Types de produits uniques pour le filtre
        $typesProduits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT type_produit FROM produit WHERE user_id = :user_id AND type_produit IS NOT NULL ORDER BY type_produit',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/produit/index.html.twig', [
            'produits' => $produits,
            'stats' => $stats,
            'typesProduits' => $typesProduits,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
                'typeProduit' => $typeProduit,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
            ],
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
            try {
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
                    // Validation supplémentaire avec l'entité
                    $produitEntity = new Produit();
                    $produitEntity->setNomProduit($produitFormData['nomProduit']);
                    $produitEntity->setMontant($produitFormData['montant']);
                    $produitEntity->setCodeUnique($produitFormData['codeUnique'] !== '' ? $produitFormData['codeUnique'] : $produit['codeUnique']);
                    $produitEntity->setTypeProduit($produitFormData['typeProduit']);
                    $produitEntity->setStatut($produitFormData['statut']);

                    // Valider l'entité
                    $validator = $this->container->get('validator');
                    $violations = $validator->validate($produitEntity);
                    
                    if (count($violations) > 0) {
                        throw new ValidationFailedException($produitEntity, $violations);
                    }

                    $entityManager->getConnection()->update('produit', [
                        'nom_produit' => $produitFormData['nomProduit'],
                        'montant' => number_format((float) str_replace(',', '.', $produitFormData['montant']), 2, '.', ''),
                        'code_unique' => $produitEntity->getCodeUnique(),
                        'type_produit' => $produitFormData['typeProduit'],
                        'statut' => $produitFormData['statut'],
                    ], ['id_produit' => $id, 'user_id' => $user->getId()]);

                    $this->addFlash('success', 'Produit mis à jour avec succès.');
                    return $this->redirectToRoute('produit_index');
                }

                $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');

            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Erreur : Le code unique existe déjà');
            } catch (ValidationFailedException $e) {
                $errors = [];
                foreach ($e->getViolations() as $violation) {
                    $errors[] = $violation->getMessage();
                }
                $this->addFlash('error', implode(', ', $errors));
            } catch (TransformationFailedException $e) {
                $this->addFlash('error', 'Erreur de transformation des données');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            }
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
