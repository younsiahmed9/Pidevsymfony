<?php

namespace App\Controller\BackOffice;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Form\Exception\TransformationFailedException;

#[Route('/admin/produit')]
class ProduitController extends AbstractController
{
    #[Route('/', name: 'admin_produit_index', methods: ['GET'])]
    public function index(ProduitRepository $produitRepository): Response
    {
        try {
            return $this->render('backoffice/produit/index.html.twig', [
                'produits' => $produitRepository->findBy(['isDeleted' => false]),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            return $this->render('backoffice/produit/index.html.twig', ['produits' => []]);
        }
    }

    #[Route('/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
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
                    return $this->redirectToRoute('admin_produit_index');
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('error', '❌ Erreur : Le code unique existe déjà');
                } catch (ValidationFailedException $e) {
                    $this->addFlash('error', '❌ Erreur de validation : ' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('backoffice/produit/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_produit_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Produit $produit): Response
    {
        try {
            return $this->render('backoffice/produit/show.html.twig', [
                'produit' => $produit,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('admin_produit_index');
        }
    }

    #[Route('/{id}/edit', name: 'admin_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->flush();

                    $this->addFlash('success', '✏️ Produit modifié avec succès !');
                    return $this->redirectToRoute('admin_produit_index');
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('error', '❌ Erreur : Le code unique existe déjà');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('backoffice/produit/edit.html.twig', [
            'form' => $form,
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}', name: 'admin_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), $request->request->get('_token'))) {
            try {
                // Soft delete
                $produit->setIsDeleted(true);
                $entityManager->flush();

                $this->addFlash('success', '🗑️ Produit archivé avec succès !');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', '❌ Erreur : Suppression impossible car ce produit est utilisé ailleurs');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_produit_index');
    }
}
