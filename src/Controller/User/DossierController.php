<?php
namespace App\Controller\User;

use App\Entity\Dossier;
use App\Form\DossierType;
use App\Repository\DossierRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user/dossier')]
class DossierController extends AbstractController
{
    #[Route('/', name: 'user_dossier_index', methods: ['GET'])]
    public function index(Request $request, DossierRepository $repo): Response
    {
        $query = $request->query->get('q', '');
        return $this->render('user/dossier/index.html.twig', ['dossiers' => $repo->search($query), 'q' => $query]);
    }

    #[Route('/new', name: 'user_dossier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UtilisateurRepository $userRepo): Response
    {
        $dossier = new Dossier();
        $dossier->setUtilisateur($userRepo->findOneBy([]));

        $form = $this->createForm(DossierType::class, $dossier);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($dossier);
            $em->flush();
            $this->addFlash('success', 'Dossier créé avec succès.');
            return $this->redirectToRoute('user_dossier_index');
        }
        return $this->render('user/dossier/new.html.twig', ['dossier' => $dossier, 'form' => $form]);
    }

    #[Route('/{id}', name: 'user_dossier_show', methods: ['GET'])]
    public function show(Dossier $dossier): Response
    {
        return $this->render('user/dossier/show.html.twig', ['dossier' => $dossier]);
    }

    #[Route('/{id}/edit', name: 'user_dossier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dossier $dossier, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DossierType::class, $dossier);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $dossier->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Dossier modifié avec succès.');
            return $this->redirectToRoute('user_dossier_index');
        }
        return $this->render('user/dossier/edit.html.twig', ['dossier' => $dossier, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'user_dossier_delete', methods: ['POST'])]
    public function delete(Request $request, Dossier $dossier, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dossier->getId(), $request->request->get('_token'))) {
            $em->remove($dossier);
            $em->flush();
            $this->addFlash('success', 'Dossier supprimé avec succès.');
        }
        return $this->redirectToRoute('user_dossier_index');
    }
}
