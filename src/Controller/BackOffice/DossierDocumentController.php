<?php

namespace App\Controller\BackOffice;

use App\Entity\Dossier;
use App\Repository\DossierRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/dossier-document', name: 'admin_dossier_document_')]
class DossierDocumentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DossierRepository $dossierRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $dossiers = $dossierRepository->search($q);

        return $this->render('backoffice/dossier_document/index.html.twig', [
            'dossiers' => $dossiers,
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $dossier = new Dossier();
        $users = $userRepository->findAll();

        if ($request->isMethod('POST')) {
            $user = $userRepository->find($request->request->get('id_utilisateur'));
            
            if ($user) {
                $dossier->setUtilisateur($user);
            }

            $dossier->setNomDossier($request->request->get('nom_dossier'));
            $dossier->setDescription($request->request->get('description'));
            $dossier->setUpdatedAt(new \DateTime());

            $entityManager->persist($dossier);
            $entityManager->flush();

            $this->addFlash('success', 'Dossier créé avec succès.');
            return $this->redirectToRoute('admin_dossier_document_index');
        }

        return $this->render('backoffice/dossier_document/new.html.twig', [
            'dossier' => $dossier,
            'users' => $users,
            'selectedUserId' => null,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Dossier $dossier): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('backoffice/dossier_document/show.html.twig', [
            'dossier' => $dossier,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dossier $dossier, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $userRepository->findAll();

        if ($request->isMethod('POST')) {
            $user = $userRepository->find($request->request->get('id_utilisateur'));
            
            if ($user) {
                $dossier->setUtilisateur($user);
            }

            $dossier->setNomDossier($request->request->get('nom_dossier'));
            $dossier->setDescription($request->request->get('description'));
            $dossier->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Dossier mis à jour.');
            return $this->redirectToRoute('admin_dossier_document_index');
        }

        return $this->render('backoffice/dossier_document/edit.html.twig', [
            'dossier' => $dossier,
            'users' => $users,
            'selectedUserId' => $dossier->getUtilisateur()->getId(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Dossier $dossier, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$dossier->getId(), $request->request->get('_token'))) {
            $entityManager->remove($dossier);
            $entityManager->flush();
            $this->addFlash('success', 'Dossier supprimé.');
        }

        return $this->redirectToRoute('admin_dossier_document_index');
    }
}