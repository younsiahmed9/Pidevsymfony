<?php
namespace App\Controller\Admin;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categorie')]
class CategorieController extends AbstractController
{
    #[Route('/', name: 'admin_categorie_index', methods: ['GET'])]
    public function index(Request $request, CategorieRepository $repo): Response
    {
        $query = $request->query->get('q', '');
        return $this->render('admin/categorie/index.html.twig', [
            'categories' => $repo->search($query),
            'q'          => $query,
        ]);
    }

    #[Route('/new', name: 'admin_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new Categorie();
        $form      = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'Categorie creee avec succes.');
            return $this->redirectToRoute('admin_categorie_index');
        }
        return $this->render('admin/categorie/new.html.twig', ['categorie' => $categorie, 'form' => $form]);
    }

    #[Route('/{id}', name: 'admin_categorie_show', methods: ['GET'])]
    public function show(Categorie $categorie): Response
    {
        return $this->render('admin/categorie/show.html.twig', ['categorie' => $categorie]);
    }

    #[Route('/{id}/edit', name: 'admin_categorie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Categorie modifiee avec succes.');
            return $this->redirectToRoute('admin_categorie_index');
        }
        return $this->render('admin/categorie/edit.html.twig', ['categorie' => $categorie, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_categorie_delete', methods: ['POST'])]
    public function delete(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$categorie->getId(), $request->request->get('_token'))) {
            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'Categorie supprimee avec succes.');
        }
        return $this->redirectToRoute('admin_categorie_index');
    }
}
