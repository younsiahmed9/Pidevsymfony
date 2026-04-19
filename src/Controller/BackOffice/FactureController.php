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
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Paramètres de recherche, filtre et tri
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sortBy', 'id_facture');
        $sortOrder = $request->query->get('sortOrder', 'DESC');
        $userId = $request->query->get('user_id', '');
        $dateFrom = $request->query->get('dateFrom', '');
        $dateTo = $request->query->get('dateTo', '');

        // Construction de la requête SQL
        $sql = 'SELECT f.id_facture AS id, f.user_id, f.montant,
                       f.date_facture AS dateFacture, f.date_echeance AS dateEcheance,
                       f.id_service AS service, f.id_produit AS produit,
                       f.statut, f.numero_facture AS numeroFacture,
                     TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                     TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                     u.email AS user_email,
                     s.nom_service AS serviceNom,
                     p.nom_produit AS produitNom
                FROM facture f
                INNER JOIN users u ON u.id = f.user_id
                LEFT JOIN service s ON s.id_service = f.id_service
                LEFT JOIN produit p ON p.id_produit = f.id_produit
                WHERE 1=1';
        
        $params = [];

        // Filtre de recherche
        if (!empty($search)) {
            $sql .= ' AND (f.numero_facture LIKE :search OR s.nom_service LIKE :search OR p.nom_produit LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filtre par statut
        if (!empty($statut)) {
            $sql .= ' AND f.statut = :statut';
            $params['statut'] = $statut;
        }

        // Filtre par utilisateur
        if (!empty($userId)) {
            $sql .= ' AND f.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        // Filtre par période
        if (!empty($dateFrom)) {
            $sql .= ' AND f.date_facture >= :dateFrom';
            $params['dateFrom'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $sql .= ' AND f.date_facture <= :dateTo';
            $params['dateTo'] = $dateTo;
        }

        // Tri
        $allowedSortFields = ['id_facture', 'numero_facture', 'montant', 'date_facture', 'date_echeance', 'statut'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id_facture';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
        
        $sql .= ' ORDER BY f.' . $sortBy . ' ' . $sortOrder;

        $factures = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        // Statistiques
        $statsSql = 'SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN statut = \'payee\' THEN 1 END) as payees,
                        COUNT(CASE WHEN statut = \'non_payee\' THEN 1 END) as non_payees,
                        COUNT(CASE WHEN statut = \'en_retard\' THEN 1 END) as en_retard,
                        COUNT(CASE WHEN statut = \'annulee\' THEN 1 END) as annulees,
                        SUM(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE 0 END) as montant_total,
                        AVG(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE NULL END) as montant_moyen,
                        COUNT(CASE WHEN id_service IS NOT NULL THEN 1 END) as factures_service,
                        COUNT(CASE WHEN id_produit IS NOT NULL THEN 1 END) as factures_produit
                     FROM facture';
        
        $statsParams = [];
        if (!empty($dateFrom) || !empty($dateTo)) {
            $statsSql .= ' WHERE 1=1';
            if (!empty($dateFrom)) {
                $statsSql .= ' AND date_facture >= :dateFrom';
                $statsParams['dateFrom'] = $dateFrom;
            }
            if (!empty($dateTo)) {
                $statsSql .= ' AND date_facture <= :dateTo';
                $statsParams['dateTo'] = $dateTo;
            }
        }
        
        $stats = $entityManager->getConnection()->fetchAllAssociative($statsSql, $statsParams)[0];

        // Liste des utilisateurs pour le filtre
        $users = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", -1)) AS nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", 1)) AS prenom, email
             FROM users ORDER BY full_name ASC'
        );

        return $this->render('backoffice/facture/index.html.twig', [
            'factures' => $factures,
            'stats' => $stats,
            'users' => $users,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'user_id' => $userId,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            // Préparer les données pour la validation
            $factureFormData = [
                'numeroFacture' => trim($data['numeroFacture'] ?? ''),
                'montant' => $data['montant'] ?? '',
                'dateFacture' => $data['dateFacture'] ?? (new \DateTime())->format('Y-m-d'),
                'dateEcheance' => $data['dateEcheance'] ?? '',
                'service' => $data['service'] ?? '',
                'produit' => $data['produit'] ?? '',
                'statut' => $data['statut'] ?? 'non_payee',
            ];
            
            // Valider les données
            $formErrors = $this->validateFactureInput($factureFormData, $entityManager);
            
            if ($formErrors === []) {
                $dateFacture = !empty($data['dateFacture']) ? $data['dateFacture'] : (new \DateTime())->format('Y-m-d');
                $dateEcheance = !empty($data['dateEcheance']) ? $data['dateEcheance'] : $dateFacture;
                
                // Valider que l'utilisateur existe
                $userId = (int) ($data['user_id'] ?? 0);
                if ($userId <= 0) {
                    // Si pas d'utilisateur valide, prendre le premier utilisateur disponible
                    $firstUser = $entityManager->getConnection()->fetchOne('SELECT id FROM users ORDER BY id ASC LIMIT 1');
                    if (!$firstUser) {
                        throw new \RuntimeException('Aucun utilisateur disponible dans la base de données.');
                    }
                    $userId = (int) $firstUser;
                } else {
                    // Vérifier que l'utilisateur existe
                    $userExists = $entityManager->getConnection()->fetchOne(
                        'SELECT id FROM users WHERE id = :user_id',
                        ['user_id' => $userId]
                    );
                    if (!$userExists) {
                        throw new \RuntimeException('L\'utilisateur sélectionné n\'existe pas.');
                    }
                }
                
                // Générer un numéro de facture unique
                $numeroFacture = trim($data['numeroFacture'] ?? '');
                if (empty($numeroFacture)) {
                    $numeroFacture = 'FAC-' . strtoupper(bin2hex(random_bytes(4)));
                }
                
                // Vérifier si le numéro de facture existe déjà
                $existingFacture = $entityManager->getConnection()->fetchOne(
                    'SELECT id_facture FROM facture WHERE numero_facture = :numero',
                    ['numero' => $numeroFacture]
                );
                
                if ($existingFacture) {
                    // Si le numéro existe déjà, en générer un nouveau
                    do {
                        $numeroFacture = 'FAC-' . strtoupper(bin2hex(random_bytes(4)));
                        $existingFacture = $entityManager->getConnection()->fetchOne(
                            'SELECT id_facture FROM facture WHERE numero_facture = :numero',
                            ['numero' => $numeroFacture]
                        );
                    } while ($existingFacture);
                }

                $entityManager->getConnection()->insert('facture', [
                    'user_id' => $userId,
                    'montant' => number_format((float) str_replace(',', '.', $factureFormData['montant']), 2, '.', ''),
                    'date_facture' => $dateFacture,
                    'date_echeance' => $dateEcheance,
                    'id_service' => !empty($data['service']) && ctype_digit((string) $data['service']) ? (int) $data['service'] : null,
                    'id_produit' => !empty($data['produit']) && ctype_digit((string) $data['produit']) ? (int) $data['produit'] : null,
                    'statut' => $factureFormData['statut'],
                    'numero_facture' => $numeroFacture,
                ]);

                $this->addFlash('success', 'Facture créée avec succès.');
                return $this->redirectToRoute('admin_facture_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
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

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, nom_service AS nomService, tarif
             FROM service
             ORDER BY nom_service ASC'
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant
             FROM produit
             ORDER BY nom_produit ASC'
        );

        return $this->render('backoffice/facture/new.html.twig', [
            'users' => $users,
            'services' => $services,
            'produits' => $produits,
            'facture' => $factureFormData ?? [],
            'formErrors' => $formErrors ?? [],
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
            try {
                $data = $request->request->all();
                
                // Préparer les données pour la validation
                $factureFormData = [
                    'numeroFacture' => trim($data['numeroFacture'] ?? $facture['numeroFacture']),
                    'montant' => $data['montant'] ?? $facture['montant'],
                    'dateFacture' => $data['dateFacture'] ?? $facture['dateFacture'],
                    'dateEcheance' => $data['dateEcheance'] ?? $facture['dateEcheance'] ?? '',
                    'service' => $data['service'] ?? $facture['service'] ?? '',
                    'produit' => $data['produit'] ?? $facture['produit'] ?? '',
                    'statut' => $data['statut'] ?? $facture['statut'],
                ];
                
                // Valider les données
                $formErrors = $this->validateFactureInput($factureFormData, $entityManager, $id);
                
                if ($formErrors === []) {
                    $dateFacture = !empty($data['dateFacture']) ? $data['dateFacture'] : $facture['dateFacture'];
                    $dateEcheance = !empty($data['dateEcheance']) ? $data['dateEcheance'] : ($facture['dateEcheance'] ?? $dateFacture);
                    
                    // Valider que l'utilisateur existe
                    $userId = (int) ($data['user_id'] ?? $facture['user_id']);
                    if ($userId <= 0) {
                        // Si pas d'utilisateur valide, prendre l'utilisateur existant de la facture
                        $userId = (int) $facture['user_id'];
                    } else {
                        // Vérifier que l'utilisateur existe
                        $userExists = $entityManager->getConnection()->fetchOne(
                            'SELECT id FROM users WHERE id = :user_id',
                            ['user_id' => $userId]
                        );
                        if (!$userExists) {
                            throw new EntityNotFoundException('L\'utilisateur sélectionné n\'existe pas.');
                        }
                    }
                    
                    // Générer un numéro de facture unique
                    $numeroFacture = trim($data['numeroFacture'] ?? '');
                    if (empty($numeroFacture)) {
                        $numeroFacture = $facture['numeroFacture'];
                    }
                    
                    // Vérifier si le numéro de facture existe déjà (sauf la facture actuelle)
                    $existingFacture = $entityManager->getConnection()->fetchOne(
                        'SELECT id_facture FROM facture WHERE numero_facture = :numero AND id_facture != :current_id',
                        ['numero' => $numeroFacture, 'current_id' => $id]
                    );
                    
                    if ($existingFacture) {
                        // Si le numéro existe déjà, en générer un nouveau
                        do {
                            $numeroFacture = 'FAC-' . strtoupper(bin2hex(random_bytes(4)));
                            $existingFacture = $entityManager->getConnection()->fetchOne(
                                'SELECT id_facture FROM facture WHERE numero_facture = :numero AND id_facture != :current_id',
                                ['numero' => $numeroFacture, 'current_id' => $id]
                            );
                        } while ($existingFacture);
                    }

                    $entityManager->getConnection()->update('facture', [
                        'user_id' => $userId,
                        'montant' => number_format((float) str_replace(',', '.', $factureFormData['montant']), 2, '.', ''),
                        'date_facture' => $dateFacture,
                        'date_echeance' => $dateEcheance,
                        'id_service' => !empty($data['service']) && ctype_digit((string) $data['service']) ? (int) $data['service'] : null,
                        'id_produit' => !empty($data['produit']) && ctype_digit((string) $data['produit']) ? (int) $data['produit'] : null,
                        'statut' => $factureFormData['statut'],
                        'numero_facture' => $numeroFacture,
                    ], ['id_facture' => $id]);

                    $this->addFlash('success', 'Facture mise à jour avec succès.');
                    return $this->redirectToRoute('admin_facture_index');
                }

                $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');

            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Erreur : Le numéro de facture existe déjà');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Erreur : Contrainte de clé étrangère violée');
            } catch (EntityNotFoundException $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            } catch (ValidationFailedException $e) {
                $errors = [];
                foreach ($e->getViolations() as $violation) {
                    $errors[] = $violation->getMessage();
                }
                $this->addFlash('error', implode(', ', $errors));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            }
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

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, nom_service AS nomService, tarif
             FROM service
             ORDER BY nom_service ASC'
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant
             FROM produit
             ORDER BY nom_produit ASC'
        );

        return $this->render('backoffice/facture/edit.html.twig', [
            'facture' => array_merge($facture, $factureFormData ?? []),
            'users' => $users,
            'services' => $services,
            'produits' => $produits,
            'formErrors' => $formErrors ?? [],
        ]);
    }

    private function validateFactureInput(array $data, EntityManagerInterface $entityManager, ?int $currentFactureId = null): array
    {
        $errors = [];

        $montant = str_replace(',', '.', (string) $data['montant']);
        if ($montant === '' || !is_numeric($montant) || (float) $montant <= 0) {
            $errors['montant'][] = 'Le montant doit être un nombre supérieur à 0.';
        }

        if (!in_array($data['statut'], ['non_payee', 'payee', 'en_retard', 'annulee'], true)) {
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
        } elseif ($dateFacture > new \DateTime()) {
            $errors['dateFacture'][] = 'La date de facture ne peut pas être postérieure à aujourd\'hui.';
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
                    'SELECT id_service FROM service WHERE id_service = :id',
                    ['id' => (int) $data['service']]
                );
                if (!$serviceExists) {
                    $errors['service'][] = 'Le service sélectionné n\'existe pas.';
                }
            }
        }

        if ($data['produit'] !== '') {
            if (!ctype_digit($data['produit'])) {
                $errors['produit'][] = 'Le produit sélectionné est invalide.';
            } else {
                $produitExists = $entityManager->getConnection()->fetchOne(
                    'SELECT id_produit FROM produit WHERE id_produit = :id',
                    ['id' => (int) $data['produit']]
                );
                if (!$produitExists) {
                    $errors['produit'][] = 'Le produit sélectionné n\'existe pas.';
                }
            }
        }

        return $errors;
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
