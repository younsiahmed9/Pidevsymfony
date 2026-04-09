<?php

namespace App\Controller\BackOffice;

use App\Entity\Facture;
use App\Form\FactureType;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/facture')]
class FactureController extends AbstractController
{
    #[Route('/', name: 'admin_facture_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        return $this->render('backoffice/facture/index.html.twig', [
            'factures' => $factureRepository->findBy(['isDeleted' => false]),
        ]);
    }

    #[Route('/new', name: 'admin_facture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, FactureRepository $factureRepository): Response
    {
        $facture = new Facture();
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Logic for automatic fields
                    if ($facture->getProduit()) {
                        $facture->setMontant($facture->getProduit()->getMontant());
                    } elseif ($facture->getService()) {
                        $facture->setMontant($facture->getService()->getTarif());
                    }

                    if ($facture->getDateFacture()) {
                        $dateEcheance = clone $facture->getDateFacture();
                        $dateEcheance->modify('+30 days');
                        $facture->setDateEcheance($dateEcheance);

                        $dateStr = $facture->getDateFacture()->format('Y-m-d');
                        $nextSeq = count($factureRepository->findBy(['date_facture' => $facture->getDateFacture()])) + 1;
                        $facture->setNumeroFacture(sprintf("FAC-%s-%03d", $dateStr, $nextSeq));
                    }

                    $entityManager->persist($facture);
                    $entityManager->flush();

                    $this->addFlash('success', '💰 Facture générée avec succès ! Numéro : ' . $facture->getNumeroFacture());
                    return $this->redirectToRoute('admin_facture_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Veuillez sélectionner un produit OU un service');
            }
        }

        return $this->render('backoffice/facture/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_facture_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Facture $facture): Response
    {
        return $this->render('backoffice/facture/show.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_facture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->flush();
                    $this->addFlash('success', '✏️ Facture modifiée avec succès !');
                    return $this->redirectToRoute('admin_facture_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('backoffice/facture/edit.html.twig', [
            'form' => $form,
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}', name: 'admin_facture_delete', methods: ['POST'])]
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

        return $this->redirectToRoute('admin_facture_index');
    }
}
