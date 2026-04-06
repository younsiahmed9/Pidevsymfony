<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Form\FactureType;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/facture')]
class FactureController extends AbstractController
{
    #[Route('/', name: 'facture_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        return $this->render('facture/index.html.twig', [
            'factures' => $factureRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'facture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $facture = new Facture();
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validation métier: au moins un Service ou un Produit doit être associé
            if (!$facture->getService() && !$facture->getProduit()) {
                $this->addFlash('error', 'La facture doit être liée à au moins un Service ou un Produit.');
                return $this->redirectToRoute('facture_new');
            }

            // Validation métier: date d'échéance doit être après date de facture
            if ($facture->getDateEcheance() <= $facture->getDateFacture()) {
                $this->addFlash('error', 'La date d\'échéance doit être après la date de facture.');
                return $this->redirectToRoute('facture_new');
            }

            $entityManager->persist($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Facture créée avec succès!');
            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        return $this->render('facture/show.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}/edit', name: 'facture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validation métier: au moins un Service ou un Produit doit être associé
            if (!$facture->getService() && !$facture->getProduit()) {
                $this->addFlash('error', 'La facture doit être liée à au moins un Service ou un Produit.');
                return $this->redirectToRoute('facture_edit', ['id' => $facture->getId()]);
            }

            // Validation métier: date d'échéance doit être après date de facture
            if ($facture->getDateEcheance() <= $facture->getDateFacture()) {
                $this->addFlash('error', 'La date d\'échéance doit être après la date de facture.');
                return $this->redirectToRoute('facture_edit', ['id' => $facture->getId()]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Facture modifiée avec succès!');
            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/edit.html.twig', [
            'form' => $form,
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}', name: 'facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->request->get('_token'))) {
            $entityManager->remove($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Facture supprimée avec succès!');
        }

        return $this->redirectToRoute('facture_index');
    }
}
