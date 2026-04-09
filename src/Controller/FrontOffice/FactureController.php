<?php

namespace App\Controller\FrontOffice;

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
    #[Route('/', name: 'front_facture_index', methods: ['GET'])]
    public function index(Request $request, FactureRepository $factureRepository): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');

        $qb = $factureRepository->createQueryBuilder('f');

        if ($search) {
            $qb->andWhere('f.numeroFacture LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        if ($status) {
            $qb->andWhere('f.statut = :status')
               ->setParameter('status', $status);
        }

        $factures = $qb->orderBy('f.id', 'DESC')->getQuery()->getResult();

        return $this->render('frontoffice/facture/index.html.twig', [
            'factures' => $factures,
            'currentSearch' => $search,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/new', name: 'front_facture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $facture = new Facture();
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->persist($facture);
                    $entityManager->flush();

                    $this->addFlash('success', '💰 Facture générée avec succès ! Numéro : ' . $facture->getNumeroFacture());
                    return $this->redirectToRoute('front_facture_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('frontoffice/facture/new.html.twig', [
            'facture' => $facture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'front_facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        try {
            if ($facture->isDeleted()) {
                throw new \Exception("Facture archivée");
            }
            return $this->render('frontoffice/facture/show.html.twig', [
                'facture' => $facture,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : Facture non trouvée');
            return $this->redirectToRoute('front_facture_index');
        }
    }

    #[Route('/{id}/edit', name: 'front_facture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->flush();
                    $this->addFlash('success', '✏️ Facture modifiée avec succès !');
                    return $this->redirectToRoute('front_facture_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('frontoffice/facture/edit.html.twig', [
            'facture' => $facture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'front_facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->request->get('_token'))) {
            try {
                $facture->setIsDeleted(true);
                $entityManager->flush();
                $this->addFlash('success', '🗑️ Facture archivée avec succès !');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('front_facture_index');
    }
}
