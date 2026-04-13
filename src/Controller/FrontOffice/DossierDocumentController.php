<?php

namespace App\Controller\FrontOffice;

use App\Entity\Dossier;
use App\Entity\User;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dossier-document', name: 'dossier_document_')]
class DossierDocumentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DossierRepository $dossierRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dossiers = $dossierRepository->findBy(['utilisateur' => $user], ['createdAt' => 'DESC']);

        return $this->render('frontoffice/dossier_document/index.html.twig', [
            'dossiers' => $dossiers,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = new Dossier();
        $dossierFormData = [
            'nom_dossier' => '',
            'description' => '',
        ];
        
        if ($request->isMethod('POST')) {
            $dossierFormData = [
                'nom_dossier' => trim((string) $request->request->get('nom_dossier', '')),
                'description' => trim((string) $request->request->get('description', '')),
            ];

            $dossier->setUtilisateur($user);
            $dossier->setNomDossier($dossierFormData['nom_dossier']);
            $dossier->setDescription($dossierFormData['description'] !== '' ? $dossierFormData['description'] : null);
            $dossier->setUpdatedAt(new \DateTime());

            $entityManager->persist($dossier);
            $entityManager->flush();

            $this->addFlash('success', 'Votre dossier a été créé avec succès.');
            return $this->redirectToRoute('dossier_document_index');
        }

        return $this->render('frontoffice/dossier_document/new.html.twig', [
            'dossier' => $dossier,
            'dossierFormData' => $dossierFormData,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, DossierRepository $dossierRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$dossier) {
            throw $this->createNotFoundException('Dossier introuvable ou accès refusé.');
        }

        return $this->render('frontoffice/dossier_document/show.html.twig', [
            'dossier' => $dossier,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, DossierRepository $dossierRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$dossier) {
            throw $this->createNotFoundException('Dossier introuvable ou accès refusé.');
        }

        $dossierFormData = [
            'nom_dossier' => $dossier->getNomDossier(),
            'description' => $dossier->getDescription() ?? '',
        ];

        if ($request->isMethod('POST')) {
            $dossierFormData = [
                'nom_dossier' => trim((string) $request->request->get('nom_dossier', '')),
                'description' => trim((string) $request->request->get('description', '')),
            ];

            $dossier->setNomDossier($dossierFormData['nom_dossier']);
            $dossier->setDescription($dossierFormData['description'] !== '' ? $dossierFormData['description'] : null);
            $dossier->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Le dossier a été mis à jour.');
            return $this->redirectToRoute('dossier_document_index');
        }

        return $this->render('frontoffice/dossier_document/edit.html.twig', [
            'dossier' => $dossier,
            'dossierFormData' => $dossierFormData,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, DossierRepository $dossierRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if ($dossier && $this->isCsrfTokenValid('delete'.$dossier->getId(), $request->request->get('_token'))) {
            $entityManager->remove($dossier);
            $entityManager->flush();
            $this->addFlash('success', 'Dossier supprimé.');
        }

        return $this->redirectToRoute('dossier_document_index');
    }
}