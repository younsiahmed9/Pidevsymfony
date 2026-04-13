<?php

namespace App\Controller\FrontOffice;

use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/categorie', name: 'categorie_')]
class CategorieController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(CategorieRepository $categorieRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $categories = $categorieRepository->findAll();

        return $this->render('frontoffice/categorie/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $categorie = new Categorie();
        
        if ($request->isMethod('POST')) {
            $categorie->setNomCategorie($request->request->get('nom_categorie'));
            $categorie->setDescription($request->request->get('description'));
            $categorie->setIcon($request->request->get('icon'));
            $categorie->setCouleur($request->request->get('couleur'));

            $entityManager->persist($categorie);
            $entityManager->flush();

            $this->addFlash('success', 'Catégorie créée avec succès.');
            return $this->redirectToRoute('categorie_index');
        }

        return $this->render('frontoffice/categorie/new.html.twig', [
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Categorie $categorie): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('frontoffice/categorie/show.html.twig', [
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Categorie $categorie, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($request->isMethod('POST')) {
            $categorie->setNomCategorie($request->request->get('nom_categorie'));
            $categorie->setDescription($request->request->get('description'));
            $categorie->setIcon($request->request->get('icon'));
            $categorie->setCouleur($request->request->get('couleur'));

            $entityManager->flush();

            $this->addFlash('success', 'Catégorie mise à jour.');
            return $this->redirectToRoute('categorie_index');
        }

        return $this->render('frontoffice/categorie/edit.html.twig', [
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Categorie $categorie, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->isCsrfTokenValid('delete'.$categorie->getId(), $request->request->get('_token'))) {
            $entityManager->remove($categorie);
            $entityManager->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('categorie_index');
    }
}
