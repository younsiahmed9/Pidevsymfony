<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Entity\Service;
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

#[Route('/service', name: 'service_')]
final class ServiceController extends AbstractController
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
        $typeService = $request->query->get('typeService', '');
        $frequence = $request->query->get('frequence', '');
        $sortBy = $request->query->get('sortBy', 'id_service');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        // Construction de la requête SQL
        $sql = 'SELECT id_service AS id, user_id, nom_service AS nomService, tarif, 
                       type_service AS typeService, frequence, date_debut AS dateDebut, 
                       date_fin AS dateFin, statut 
                FROM service 
                WHERE user_id = :user_id';
        
        $params = ['user_id' => $user->getId()];

        // Filtre de recherche
        if (!empty($search)) {
            $sql .= ' AND (nom_service LIKE :search OR type_service LIKE :search OR frequence LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filtre par statut
        if (!empty($statut)) {
            $sql .= ' AND statut = :statut';
            $params['statut'] = $statut;
        }

        // Filtre par type de service
        if (!empty($typeService)) {
            $sql .= ' AND type_service = :typeService';
            $params['typeService'] = $typeService;
        }

        // Filtre par fréquence
        if (!empty($frequence)) {
            $sql .= ' AND frequence = :frequence';
            $params['frequence'] = $frequence;
        }

        // Tri
        $allowedSortFields = ['id_service', 'nom_service', 'tarif', 'type_service', 'frequence', 'statut', 'date_debut', 'date_fin'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id_service';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
        
        $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortOrder;

        $services = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        // Statistiques pour l'utilisateur
        $stats = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN statut = \'actif\' THEN 1 END) as actifs,
                COUNT(CASE WHEN statut = \'inactif\' THEN 1 END) as inactifs,
                COUNT(CASE WHEN statut = \'suspendu\' THEN 1 END) as suspendus,
                COUNT(CASE WHEN type_service = \'ponctuel\' THEN 1 END) as ponctuels,
                COUNT(CASE WHEN type_service = \'abonnement\' THEN 1 END) as abonnements,
                COUNT(CASE WHEN frequence = \'mensuel\' THEN 1 END) as mensuels,
                COUNT(CASE WHEN frequence = \'annuel\' THEN 1 END) as annuels,
                SUM(CASE WHEN tarif > 0 THEN CAST(tarif AS DECIMAL(10,2)) ELSE 0 END) as revenu_total,
                AVG(CASE WHEN tarif > 0 THEN CAST(tarif AS DECIMAL(10,2)) ELSE NULL END) as tarif_moyen
             FROM service 
             WHERE user_id = :user_id',
            ['user_id' => $user->getId()]
        )[0];

        // Types de services uniques pour le filtre
        $typesServices = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT type_service FROM service WHERE user_id = :user_id AND type_service IS NOT NULL ORDER BY type_service',
            ['user_id' => $user->getId()]
        );

        // Fréquences uniques pour le filtre
        $frequences = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT frequence FROM service WHERE user_id = :user_id AND frequence IS NOT NULL ORDER BY frequence',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/service/index.html.twig', [
            'services' => $services,
            'stats' => $stats,
            'typesServices' => $typesServices,
            'frequences' => $frequences,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
                'typeService' => $typeService,
                'frequence' => $frequence,
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

        $serviceFormData = [
            'id' => '',
            'nomService' => '',
            'tarif' => '',
            'typeService' => '',
            'frequence' => 'mensuel',
            'dateDebut' => (new \DateTime())->format('Y-m-d'),
            'dateFin' => '',
            'statut' => 'actif',
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $serviceFormData = [
                'id' => '',
                'nomService' => trim((string) $request->request->get('nomService', '')),
                'tarif' => trim((string) $request->request->get('tarif', '')),
                'typeService' => trim((string) $request->request->get('typeService', '')),
                'frequence' => trim((string) $request->request->get('frequence', '')),
                'dateDebut' => trim((string) $request->request->get('dateDebut', (new \DateTime())->format('Y-m-d'))),
                'dateFin' => trim((string) $request->request->get('dateFin', '')),
                'statut' => trim((string) $request->request->get('statut', 'actif')),
            ];

            $formErrors = $this->validateServiceInput($serviceFormData);

            if ($formErrors === []) {
                $entityManager->getConnection()->insert('service', [
                    'user_id' => $user->getId(),
                    'nom_service' => $serviceFormData['nomService'],
                    'tarif' => number_format((float) str_replace(',', '.', $serviceFormData['tarif']), 2, '.', ''),
                    'type_service' => $serviceFormData['typeService'],
                    'frequence' => $serviceFormData['frequence'],
                    'date_debut' => $serviceFormData['dateDebut'],
                    'date_fin' => $serviceFormData['dateFin'] !== '' ? $serviceFormData['dateFin'] : null,
                    'statut' => $serviceFormData['statut'],
                ]);

                $this->addFlash('success', 'Service créé avec succès.');
                return $this->redirectToRoute('service_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/service/new.html.twig', [
            'service' => $serviceFormData,
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

        $service = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_service AS id, user_id, nom_service AS nomService, tarif, type_service AS typeService, frequence, date_debut AS dateDebut, date_fin AS dateFin, statut FROM service WHERE id_service = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$service) {
            throw $this->createNotFoundException('Service introuvable.');
        }

        $serviceFormData = [
            'id' => (string) ($service['id'] ?? ''),
            'nomService' => (string) ($service['nomService'] ?? ''),
            'tarif' => (string) ($service['tarif'] ?? ''),
            'typeService' => (string) ($service['typeService'] ?? ''),
            'frequence' => (string) ($service['frequence'] ?? 'mensuel'),
            'dateDebut' => (string) ($service['dateDebut'] ?? (new \DateTime())->format('Y-m-d')),
            'dateFin' => (string) ($service['dateFin'] ?? ''),
            'statut' => (string) ($service['statut'] ?? 'actif'),
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            try {
                $serviceFormData = [
                    'id' => (string) ($service['id'] ?? $id),
                    'nomService' => trim((string) $request->request->get('nomService', '')),
                    'tarif' => trim((string) $request->request->get('tarif', '')),
                    'typeService' => trim((string) $request->request->get('typeService', '')),
                    'frequence' => trim((string) $request->request->get('frequence', '')),
                    'dateDebut' => trim((string) $request->request->get('dateDebut', $serviceFormData['dateDebut'])),
                    'dateFin' => trim((string) $request->request->get('dateFin', '')),
                    'statut' => trim((string) $request->request->get('statut', 'actif')),
                ];

                $formErrors = $this->validateServiceInput($serviceFormData);

                if ($formErrors === []) {
                    // Validation supplémentaire avec l'entité
                    $serviceEntity = new Service();
                    $serviceEntity->setNomService($serviceFormData['nomService']);
                    $serviceEntity->setTarif($serviceFormData['tarif']);
                    $serviceEntity->setTypeService($serviceFormData['typeService']);
                    $serviceEntity->setFrequence($serviceFormData['frequence'] ?: null);
                    $serviceEntity->setDateDebut(new \DateTime($serviceFormData['dateDebut']));
                    $serviceEntity->setDateFin($serviceFormData['dateFin'] !== '' ? new \DateTime($serviceFormData['dateFin']) : null);
                    $serviceEntity->setStatut($serviceFormData['statut']);

                    // Valider l'entité
                    $validator = $this->container->get('validator');
                    $violations = $validator->validate($serviceEntity);
                    
                    if (count($violations) > 0) {
                        throw new ValidationFailedException($serviceEntity, $violations);
                    }

                    $entityManager->getConnection()->update('service', [
                        'nom_service' => $serviceFormData['nomService'],
                        'tarif' => number_format((float) str_replace(',', '.', $serviceFormData['tarif']), 2, '.', ''),
                        'type_service' => $serviceFormData['typeService'],
                        'frequence' => $serviceFormData['frequence'],
                        'date_debut' => $serviceEntity->getDateDebut()->format('Y-m-d'),
                        'date_fin' => $serviceEntity->getDateFin() ? $serviceEntity->getDateFin()->format('Y-m-d') : null,
                        'statut' => $serviceFormData['statut'],
                    ], ['id_service' => $id, 'user_id' => $user->getId()]);

                    $this->addFlash('success', 'Service mis à jour avec succès.');
                    return $this->redirectToRoute('service_index');
                }

                $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');

            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Erreur : Une contrainte unique a été violée');
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

        return $this->render('frontoffice/service/edit.html.twig', [
            'service' => $serviceFormData,
            'formErrors' => $formErrors,
        ]);
    }

    private function validateServiceInput(array $data): array
    {
        $errors = [];

        if ($data['nomService'] === '' || mb_strlen($data['nomService']) < 2) {
            $errors['nomService'][] = 'Le nom du service est obligatoire (minimum 2 caractères).';
        }

        $tarif = str_replace(',', '.', (string) $data['tarif']);
        if ($tarif === '' || !is_numeric($tarif) || (float) $tarif <= 0) {
            $errors['tarif'][] = 'Le tarif doit être un nombre supérieur à 0.';
        }

        if ($data['typeService'] === '' || mb_strlen($data['typeService']) < 2) {
            $errors['typeService'][] = 'Le type de service est obligatoire.';
        }

        if (!in_array($data['frequence'], ['unique', 'mensuel', 'trimestriel', 'annuel'], true)) {
            $errors['frequence'][] = 'La fréquence sélectionnée est invalide.';
        }

        if (!in_array($data['statut'], ['actif', 'suspendu', 'termine'], true)) {
            $errors['statut'][] = 'Le statut sélectionné est invalide.';
        }

        $dateDebut = \DateTime::createFromFormat('Y-m-d', (string) $data['dateDebut']);
        if (!$dateDebut || $dateDebut->format('Y-m-d') !== $data['dateDebut']) {
            $errors['dateDebut'][] = 'La date de début est invalide.';
        }

        if ($data['dateFin'] !== '') {
            $dateFin = \DateTime::createFromFormat('Y-m-d', (string) $data['dateFin']);
            if (!$dateFin || $dateFin->format('Y-m-d') !== $data['dateFin']) {
                $errors['dateFin'][] = 'La date de fin est invalide.';
            } elseif (!isset($errors['dateDebut']) && $dateFin < $dateDebut) {
                $errors['dateFin'][] = 'La date de fin doit être postérieure à la date de début.';
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

        $service = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_service AS id, user_id, nom_service AS nomService, tarif, type_service AS typeService, frequence, date_debut AS dateDebut, date_fin AS dateFin, statut FROM service WHERE id_service = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$service) {
            throw $this->createNotFoundException('Service introuvable.');
        }

        return $this->render('frontoffice/service/show.html.twig', [
            'service' => $service,
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
                'DELETE FROM service WHERE id_service = :id AND user_id = :user_id',
                ['id' => $id, 'user_id' => $user->getId()]
            );
        }

        return $this->redirectToRoute('service_index');
    }
}
