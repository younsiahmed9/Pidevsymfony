<?php

namespace App\Controller\BackOffice;

use App\Entity\Service;
use App\Form\AdminServiceType;
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

#[Route('/admin/service', name: 'admin_service_')]
final class ServiceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Paramètres de recherche, filtre et tri
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $typeService = $request->query->get('typeService', '');
        $frequence = $request->query->get('frequence', '');
        $sortBy = $request->query->get('sortBy', 'id_service');
        $sortOrder = $request->query->get('sortOrder', 'DESC');
        $userId = $request->query->get('user_id', '');

        // Construction de la requête SQL
        $sql = 'SELECT s.id_service AS id, s.user_id,
                       s.nom_service AS nomService, s.tarif,
                       s.type_service AS typeService, s.frequence,
                       s.date_debut AS dateDebut, s.date_fin AS dateFin,
                       s.statut,
                     TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                     TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                     u.email AS user_email
                FROM service s
                INNER JOIN users u ON u.id = s.user_id
                WHERE 1=1';
        
        $params = [];

        // Filtre de recherche
        if (!empty($search)) {
            $sql .= ' AND (s.nom_service LIKE :search OR s.type_service LIKE :search OR s.frequence LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filtre par statut
        if (!empty($statut)) {
            $sql .= ' AND s.statut = :statut';
            $params['statut'] = $statut;
        }

        // Filtre par type de service
        if (!empty($typeService)) {
            $sql .= ' AND s.type_service = :typeService';
            $params['typeService'] = $typeService;
        }

        // Filtre par fréquence
        if (!empty($frequence)) {
            $sql .= ' AND s.frequence = :frequence';
            $params['frequence'] = $frequence;
        }

        // Filtre par utilisateur
        if (!empty($userId)) {
            $sql .= ' AND s.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        // Tri
        $allowedSortFields = ['id_service', 'nom_service', 'tarif', 'type_service', 'frequence', 'statut', 'date_debut', 'date_fin'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id_service';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
        
        $sql .= ' ORDER BY s.' . $sortBy . ' ' . $sortOrder;

        $services = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        // Statistiques
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
             FROM service'
        )[0];

        // Liste des utilisateurs pour le filtre
        $users = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", -1)) AS nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(full_name, ""), " ", 1)) AS prenom, email
             FROM users ORDER BY full_name ASC'
        );

        // Types de services uniques pour le filtre
        $typesServices = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT type_service FROM service WHERE type_service IS NOT NULL ORDER BY type_service'
        );

        // Fréquences uniques pour le filtre
        $frequences = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT frequence FROM service WHERE frequence IS NOT NULL ORDER BY frequence'
        );

        return $this->render('backoffice/service/index.html.twig', [
            'services' => $services,
            'stats' => $stats,
            'users' => $users,
            'typesServices' => $typesServices,
            'frequences' => $frequences,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
                'typeService' => $typeService,
                'frequence' => $frequence,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'user_id' => $userId,
            ],
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

        $serviceData = [
            'user_id' => '',
            'nomService' => '',
            'tarif' => '',
            'typeService' => '',
            'frequence' => 'mensuel',
            'dateDebut' => (new \DateTime())->format('Y-m-d'),
            'dateFin' => '',
            'statut' => 'actif',
        ];

        $form = $this->createForm(AdminServiceType::class, $serviceData, [
            'user_choices' => $this->mapUserChoices($users),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $entityManager->getConnection()->insert('service', [
                'user_id' => (int) $data['user_id'],
                'nom_service' => $data['nomService'],
                'tarif' => $data['tarif'],
                'type_service' => $data['typeService'],
                'frequence' => $data['frequence'],
                'date_debut' => $data['dateDebut'] ?: (new \DateTime())->format('Y-m-d'),
                'date_fin' => $data['dateFin'] !== '' ? $data['dateFin'] : null,
                'statut' => $data['statut'],
            ]);

            return $this->redirectToRoute('admin_service_index');
        }

        return $this->render('backoffice/service/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $service = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_service AS id, user_id, nom_service AS nomService, tarif, type_service AS typeService, frequence, date_debut AS dateDebut, date_fin AS dateFin, statut FROM service WHERE id_service = :id',
            ['id' => $id]
        );

        if (!$service) {
            throw $this->createNotFoundException('Service introuvable.');
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

        $form = $this->createForm(AdminServiceType::class, $service, [
            'user_choices' => $this->mapUserChoices($users),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();

                // Validation supplémentaire
                $serviceEntity = new Service();
                $serviceEntity->setNomService($data['nomService']);
                $serviceEntity->setTarif($data['tarif']);
                $serviceEntity->setTypeService($data['typeService']);
                $serviceEntity->setFrequence($data['frequence']);
                $serviceEntity->setDateDebut($data['dateDebut'] ? new \DateTime($data['dateDebut']) : new \DateTime($service['dateDebut']));
                $serviceEntity->setDateFin($data['dateFin'] !== '' ? new \DateTime($data['dateFin']) : null);
                $serviceEntity->setStatut($data['statut']);

                // Valider l'entité
                $validator = $this->container->get('validator');
                $violations = $validator->validate($serviceEntity);
                
                if (count($violations) > 0) {
                    throw new ValidationFailedException($serviceEntity, $violations);
                }

                $entityManager->getConnection()->update('service', [
                    'user_id' => (int) $data['user_id'],
                    'nom_service' => $data['nomService'],
                    'tarif' => $data['tarif'],
                    'type_service' => $data['typeService'],
                    'frequence' => $data['frequence'],
                    'date_debut' => $serviceEntity->getDateDebut()->format('Y-m-d'),
                    'date_fin' => $serviceEntity->getDateFin() ? $serviceEntity->getDateFin()->format('Y-m-d') : null,
                    'statut' => $data['statut'],
                ], ['id_service' => $id]);

                $this->addFlash('success', 'Service modifié avec succès !');
                return $this->redirectToRoute('admin_service_index');

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

        return $this->render('backoffice/service/edit.html.twig', [
            'service' => $service,
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

        $service = $entityManager->getConnection()->fetchAssociative(
            'SELECT s.id_service AS id, s.user_id,
                    s.nom_service AS nomService, s.tarif,
                    s.type_service AS typeService, s.frequence,
                    s.date_debut AS dateDebut, s.date_fin AS dateFin,
                    s.statut,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", -1)) AS user_nom,
                    TRIM(SUBSTRING_INDEX(COALESCE(u.full_name, ""), " ", 1)) AS user_prenom,
                    u.email AS user_email
             FROM service s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.id_service = :id',
            ['id' => $id]
        );

        if (!$service) {
            throw $this->createNotFoundException('Service introuvable.');
        }

        return $this->render('backoffice/service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM service WHERE id_service = :id',
                ['id' => $id]
            );
        }

        return $this->redirectToRoute('admin_service_index');
    }
}
