<?php

namespace App\Controller\BackOffice;

use App\Form\AdminServiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/service', name: 'admin_service_')]
final class ServiceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $services = $entityManager->getConnection()->fetchAllAssociative(
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
               ORDER BY s.id_service DESC'
        );

        return $this->render('backoffice/service/index.html.twig', [
            'services' => $services,
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
            $data = $form->getData();

            $entityManager->getConnection()->update('service', [
                'user_id' => (int) $data['user_id'],
                'nom_service' => $data['nomService'],
                'tarif' => $data['tarif'],
                'type_service' => $data['typeService'],
                'frequence' => $data['frequence'],
                'date_debut' => $data['dateDebut'] ?: $service['dateDebut'],
                'date_fin' => $data['dateFin'] !== '' ? $data['dateFin'] : null,
                'statut' => $data['statut'],
            ], ['id_service' => $id]);

            return $this->redirectToRoute('admin_service_index');
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
