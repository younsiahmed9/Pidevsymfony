<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/service', name: 'service_')]
final class ServiceController extends AbstractController
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

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, user_id, nom_service AS nomService, tarif, type_service AS typeService, frequence, date_debut AS dateDebut, date_fin AS dateFin, statut FROM service WHERE user_id = :user_id ORDER BY id_service DESC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/service/index.html.twig', [
            'services' => $services,
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
                $entityManager->getConnection()->update('service', [
                    'nom_service' => $serviceFormData['nomService'],
                    'tarif' => number_format((float) str_replace(',', '.', $serviceFormData['tarif']), 2, '.', ''),
                    'type_service' => $serviceFormData['typeService'],
                    'frequence' => $serviceFormData['frequence'],
                    'date_debut' => $serviceFormData['dateDebut'],
                    'date_fin' => $serviceFormData['dateFin'] !== '' ? $serviceFormData['dateFin'] : null,
                    'statut' => $serviceFormData['statut'],
                ], ['id_service' => $id, 'user_id' => $user->getId()]);

                $this->addFlash('success', 'Service mis à jour avec succès.');
                return $this->redirectToRoute('service_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
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
