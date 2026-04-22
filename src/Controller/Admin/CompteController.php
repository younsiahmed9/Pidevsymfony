<?php
namespace App\Controller\Admin;

use App\Entity\Compte;
use App\Form\CompteType;
use App\Repository\CompteRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/compte')]
class CompteController extends AbstractController
{
    #[Route('/', name: 'admin_compte_index', methods: ['GET'])]
    public function index(Request $request, CompteRepository $repo): Response
    {
        $query = $request->query->get('q', '');
        $type  = $request->query->get('type', '');
        $etat  = $request->query->get('etat', '');

        return $this->render('admin/compte/index.html.twig', [
            'comptes' => $repo->search($query, $type, $etat),
            'q'       => $query,
            'type'    => $type,
            'etat'    => $etat,
        ]);
    }

    #[Route('/new', name: 'admin_compte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UtilisateurRepository $userRepo): Response
    {
        $compte = new Compte();
        $compte->setUtilisateur($userRepo->findOneBy([]));

        $form = $this->createForm(CompteType::class, $compte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($compte);
            $em->flush();
            $this->addFlash('success', 'Compte créé avec succès.');
            return $this->redirectToRoute('admin_compte_index');
        }

        return $this->render('admin/compte/new.html.twig', ['compte' => $compte, 'form' => $form]);
    }

    #[Route('/{id}', name: 'admin_compte_show', methods: ['GET'])]
    public function show(Compte $compte): Response
    {
        return $this->render('admin/compte/show.html.twig', ['compte' => $compte]);
    }

    #[Route('/{id}/edit', name: 'admin_compte_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Compte $compte, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CompteType::class, $compte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Compte modifié avec succès.');
            return $this->redirectToRoute('admin_compte_index');
        }

        return $this->render('admin/compte/edit.html.twig', ['compte' => $compte, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_compte_delete', methods: ['POST'])]
    public function delete(Request $request, Compte $compte, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$compte->getId(), $request->request->get('_token'))) {
            $em->remove($compte);
            $em->flush();
            $this->addFlash('success', 'Compte supprimé avec succès.');
        }
        return $this->redirectToRoute('admin_compte_index');
    }
}
