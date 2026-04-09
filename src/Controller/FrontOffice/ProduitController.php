<?php

namespace App\Controller\FrontOffice;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProduitController extends AbstractController
{
    // ========== READ (LISTE) ==========
    #[Route('/produit', name: 'front_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        try {
            $produits = $produitRepository->findBy(['isDeleted' => false], ['id' => 'DESC']);
            return $this->render('frontoffice/produit/index.html.twig', [
                'produits' => $produits
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Impossible de charger la liste des produits');
            return $this->render('frontoffice/produit/index.html.twig', ['produits' => []]);
        }
    }

    // ========== CREATE ==========
    #[Route('/produit/new', name: 'front_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->persist($produit);
                    $entityManager->flush();

                    $this->addFlash('success', '✅ Produit ajouté avec succès !');
                    return $this->redirectToRoute('front_produit_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('frontoffice/produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    // ========== READ (DETAIL) ==========
    #[Route('/produit/{id}', name: 'front_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        try {
            if ($produit->isDeleted()) {
                throw new \Exception("Produit archivé");
            }
            return $this->render('frontoffice/produit/show.html.twig', [
                'produit' => $produit,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : Produit non trouvé');
            return $this->redirectToRoute('front_produit_index');
        }
    }

    // ========== UPDATE ==========
    #[Route('/produit/{id}/edit', name: 'front_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->flush();
                    $this->addFlash('success', '✏️ Produit modifié avec succès !');
                    return $this->redirectToRoute('front_produit_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('frontoffice/produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    // ========== DELETE (SOFT) ==========
    #[Route('/produit/{id}/delete', name: 'front_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), $request->request->get('_token'))) {
            try {
                $produit->setIsDeleted(true);
                $entityManager->flush();
                $this->addFlash('success', '🗑️ Produit archivé avec succès !');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            }
        }
        
        return $this->redirectToRoute('front_produit_index');
    }
}
