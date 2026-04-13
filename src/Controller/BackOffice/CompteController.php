<?php

namespace App\Controller\BackOffice;

use App\Entity\Compte;
use App\Form\AdminCompteType;
use App\Repository\CompteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/compte', name: 'admin_compte_')]
class CompteController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, CompteRepository $compteRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $type = $request->query->get('type', '');
        $etat = $request->query->get('etat', '');

        $comptes = $compteRepository->search($q, $type, $etat);

        return $this->render('backoffice/compte/index.html.twig', [
            'comptes' => $comptes,
            'q' => $q,
            'type' => $type,
            'etat' => $etat,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $compte = new Compte();
        $form = $this->createForm(AdminCompteType::class, $compte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($compte->getDateCreation() === null) {
                $compte->setDateCreation(new \DateTime());
            }

            $entityManager->persist($compte);
            $entityManager->flush();

            $this->addFlash('success', 'Compte cree avec succes.');
            return $this->redirectToRoute('admin_compte_index');
        }

        return $this->render('backoffice/compte/new.html.twig', [
            'compte' => $compte,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Compte $compte): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('backoffice/compte/show.html.twig', [
            'compte' => $compte,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Compte $compte, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AdminCompteType::class, $compte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Compte mis à jour avec succès.');
            return $this->redirectToRoute('admin_compte_index');
        }

        return $this->render('backoffice/compte/edit.html.twig', [
            'compte' => $compte,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Compte $compte, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$compte->getId(), $request->request->get('_token'))) {
            $entityManager->remove($compte);
            $entityManager->flush();
            $this->addFlash('success', 'Compte supprimé.');
        }

        return $this->redirectToRoute('admin_compte_index');
    }
}
