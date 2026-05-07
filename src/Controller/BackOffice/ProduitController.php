<?php

namespace App\Controller\BackOffice;

use App\Entity\Produit;
use App\Form\AdminProduitType;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/produit', name: 'admin_produit_')]
final class ProduitController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Paramètres de recherche, filtre et tri
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $typeProduit = $request->query->get('typeProduit', '');
        $sortBy = $request->query->get('sortBy', 'id_produit');
        $sortOrder = $request->query->get('sortOrder', 'DESC');
        $userId = $request->query->get('user_id', '');

        // Construction de la requête SQL
        $sql = 'SELECT p.id_produit AS id, p.user_id,
                       p.nom_produit AS nomProduit, p.montant,
                       p.code_unique AS codeUnique, p.type_produit AS typeProduit,
                       p.statut, p.date_creation AS dateCreation,
                     TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                     TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                     u.email AS user_email
                FROM produit p
                INNER JOIN users u ON u.id = p.user_id
                WHERE 1=1';
        
        $params = [];

        // Filtre de recherche
        if (!empty($search)) {
            $sql .= ' AND (p.nom_produit LIKE :search OR p.code_unique LIKE :search OR p.type_produit LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filtre par statut
        if (!empty($statut)) {
            $sql .= ' AND p.statut = :statut';
            $params['statut'] = $statut;
        }

        // Filtre par type de produit
        if (!empty($typeProduit)) {
            $sql .= ' AND p.type_produit = :typeProduit';
            $params['typeProduit'] = $typeProduit;
        }

        // Filtre par utilisateur
        if (!empty($userId)) {
            $sql .= ' AND p.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        // Tri
        $allowedSortFields = ['id_produit', 'nom_produit', 'montant', 'code_unique', 'type_produit', 'statut', 'date_creation'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id_produit';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
        
        $sql .= ' ORDER BY p.' . $sortBy . ' ' . $sortOrder;

        $produits = $entityManager->getConnection()->fetchAllAssociative($sql, $params);
        $produits = array_map(function (array $produit): array {
            $produit['typeProduitLabel'] = $this->getTypeProduitLabel($produit['typeProduit'] ?? null);
            $produit['codeUniqueDisplay'] = $this->getCodeUniqueDisplay($produit['codeUnique'] ?? null, (int) ($produit['id'] ?? 0));

            return $produit;
        }, $produits);

        // Statistiques
        $stats = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN statut = \'disponible\' THEN 1 END) as disponibles,
                COUNT(CASE WHEN statut = \'indisponible\' THEN 1 END) as indisponibles,
                COUNT(CASE WHEN statut = \'archive\' THEN 1 END) as archives,
                COUNT(CASE WHEN type_produit = \'physique\' THEN 1 END) as physiques,
                COUNT(CASE WHEN type_produit = \'numerique\' THEN 1 END) as numeriques,
                COUNT(CASE WHEN type_produit = \'service\' THEN 1 END) as services,
                SUM(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE 0 END) as valeur_totale
             FROM produit'
        )[0];

        // Liste des utilisateurs pour le filtre
        $users = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", -1)) AS nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", 1)) AS prenom, email
             FROM users ORDER BY full_name ASC'
        );

        // Types autorisés côté backoffice
        $typesProduits = [
            ['value' => 'carte_prepaye', 'label' => 'Carte Prépayée'],
            ['value' => 'carte_cadeaux', 'label' => 'Carte Cadeaux'],
            ['value' => 'carte_abonnement', 'label' => 'Carte Abonnement'],
        ];

        return $this->render('backoffice/produit/index.html.twig', [
            'produits' => $produits,
            'stats' => $stats,
            'users' => $users,
            'typesProduits' => $typesProduits,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
                'typeProduit' => $typeProduit,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'user_id' => $userId,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
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
            'typeProduit' => 'carte_prepaye',
            'statut' => 'disponible',
        ];

        $form = $this->createForm(AdminProduitType::class, $produitData, [
            'user_choices' => $this->mapUserChoices($users),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $typeProduit = $this->normalizeTypeProduitValue($data['typeProduit'] ?? null);
                $codeUnique = $this->normalizeCodeUnique($data['codeUnique'] ?? null);
                if ($codeUnique === '') {
                    $codeUnique = $this->generateUniqueCode($entityManager);
                }

                // Validation supplémentaire
                $produit = new Produit();
                $produit->setNomProduit($data['nomProduit']);
                $produit->setMontant($data['montant']);
                $produit->setCodeUnique($codeUnique);
                $produit->setTypeProduit($typeProduit);
                $produit->setStatut($data['statut']);

                // Valider l'entité
                $violations = $validator->validate($produit);
                
                if (count($violations) > 0) {
                    throw new ValidationFailedException($produit, $violations);
                }

                $entityManager->getConnection()->insert('produit', [
                    'user_id' => (int) $data['user_id'],
                    'nom_produit' => $data['nomProduit'],
                    'montant' => $data['montant'],
                    'code_unique' => $codeUnique,
                    'type_produit' => $typeProduit,
                    'statut' => $data['statut'],
                    'date_creation' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);

                $this->addFlash('success', 'Produit ajouté avec succès !');
                return $this->redirectToRoute('admin_produit_index');

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

        return $this->render('backoffice/produit/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
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
            try {
                $data = $form->getData();
                $typeProduit = $this->normalizeTypeProduitValue($data['typeProduit'] ?? null);
                $codeUnique = $this->normalizeCodeUnique($data['codeUnique'] ?? null);
                if ($codeUnique === '') {
                    $existingCode = $this->normalizeCodeUnique($produit['codeUnique'] ?? null);
                    $codeUnique = $existingCode !== '' ? $existingCode : $this->generateUniqueCode($entityManager);
                }

                // Validation supplémentaire
                $produitEntity = new Produit();
                $produitEntity->setNomProduit($data['nomProduit']);
                $produitEntity->setMontant($data['montant']);
                $produitEntity->setCodeUnique($codeUnique);
                $produitEntity->setTypeProduit($typeProduit);
                $produitEntity->setStatut($data['statut']);

                // Valider l'entité
                $violations = $validator->validate($produitEntity);
                
                if (count($violations) > 0) {
                    throw new ValidationFailedException($produitEntity, $violations);
                }

                $entityManager->getConnection()->update('produit', [
                    'user_id' => (int) $data['user_id'],
                    'nom_produit' => $data['nomProduit'],
                    'montant' => $data['montant'],
                    'code_unique' => $codeUnique,
                    'type_produit' => $typeProduit,
                    'statut' => $data['statut'],
                ], ['id_produit' => $id]);

                $this->addFlash('success', 'Produit modifié avec succès !');
                return $this->redirectToRoute('admin_produit_index');

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

        $produit['typeProduitLabel'] = $this->getTypeProduitLabel($produit['typeProduit'] ?? null);
        $produit['codeUniqueDisplay'] = $this->getCodeUniqueDisplay($produit['codeUnique'] ?? null, (int) ($produit['id'] ?? 0));

        return $this->render('backoffice/produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            try {
                // Vérifier si le produit existe
                $produit = $entityManager->getConnection()->fetchOne(
                    'SELECT id_produit FROM produit WHERE id_produit = :id',
                    ['id' => $id]
                );

                if (!$produit) {
                    throw new EntityNotFoundException('Produit introuvable');
                }

                $entityManager->getConnection()->executeStatement(
                    'DELETE FROM produit WHERE id_produit = :id',
                    ['id' => $id]
                );

                $this->addFlash('success', 'Produit archivé avec succès !');

            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Impossible de supprimer ce produit : il est utilisé dans des factures');
            } catch (EntityNotFoundException $e) {
                $this->addFlash('error', 'Produit introuvable');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_produit_index');
    }

    private function getTypeProduitLabel(?string $type): string
    {
        $normalizedType = $this->normalizeTypeProduitValue($type);

        return match ($normalizedType) {
            'carte_prepaye' => 'Carte Prépayée',
            'carte_cadeaux' => 'Carte Cadeaux',
            'carte_abonnement' => 'Carte Abonnement',
            default => 'Non défini',
        };
    }

    private function normalizeTypeProduitValue(mixed $type): string
    {
        $value = strtolower(trim((string) $type));

        return match ($value) {
            'carte_prepaye', 'carte_prepayee', 'carte prepayee', 'carte prepayee', 'carte prepaye', 'prepayee', 'prepayee carte' => 'carte_prepaye',
            'carte_cadeaux', 'carte cadeau', 'carte cadeaux', 'cadeau', 'cadeaux' => 'carte_cadeaux',
            'carte_abonnement', 'carte abonnement', 'abonnement' => 'carte_abonnement',
            default => 'carte_prepaye',
        };
    }

    private function normalizeCodeUnique(mixed $codeUnique): string
    {
        $value = strtoupper(trim((string) $codeUnique));

        // Ignore les entrées qui ressemblent à un type produit au lieu d'un code.
        if ($value === '' || in_array($this->normalizeTypeProduitValue($value), ['carte_prepaye', 'carte_cadeaux', 'carte_abonnement'], true) && str_starts_with($value, 'CARTE')) {
            return '';
        }

        return $value;
    }

    private function getCodeUniqueDisplay(?string $codeUnique, int $id): string
    {
        $normalized = $this->normalizeCodeUnique($codeUnique);
        if ($normalized !== '') {
            return $normalized;
        }

        return sprintf('PRD-%06d', max($id, 0));
    }

    private function generateUniqueCode(EntityManagerInterface $entityManager): string
    {
        $connection = $entityManager->getConnection();

        do {
            $candidate = 'PRD-' . strtoupper(bin2hex(random_bytes(4)));
            $exists = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM produit WHERE code_unique = :code',
                ['code' => $candidate]
            ) > 0;
        } while ($exists);

        return $candidate;
    }
}
