<?php
namespace App\Controller\Admin;

use App\Entity\Echeance;
use App\Form\EcheanceType;
use App\Repository\EcheanceRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/echeance')]
class EcheanceController extends AbstractController
{
    #[Route('/', name: 'admin_echeance_index', methods: ['GET'])]
    public function index(Request $request, EcheanceRepository $repo): Response
    {
        $query    = $request->query->get('q', '');
        $statut   = $request->query->get('statut', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo   = $request->query->get('date_to', '');

        return $this->render('admin/echeance/index.html.twig', [
            'echeances'  => $repo->search($query, $statut, $dateFrom, $dateTo),
            'nb_overdue' => count($repo->findOverdue()),
            'q'          => $query,
            'statut'     => $statut,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ]);
    }

    #[Route('/new', name: 'admin_echeance_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UtilisateurRepository $userRepo): Response
    {
        $echeance = new Echeance();
        $echeance->setUtilisateur($userRepo->findOneBy([]));

        $form = $this->createForm(EcheanceType::class, $echeance);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($echeance);
            $em->flush();
            $this->addFlash('success', 'Échéance créée avec succès.');
            return $this->redirectToRoute('admin_echeance_index');
        }
        return $this->render('admin/echeance/new.html.twig', ['echeance' => $echeance, 'form' => $form]);
    }

    #[Route('/{id}', name: 'admin_echeance_show', methods: ['GET'])]
    public function show(Echeance $echeance): Response
    {
        return $this->render('admin/echeance/show.html.twig', ['echeance' => $echeance]);
    }

    #[Route('/{id}/edit', name: 'admin_echeance_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Echeance $echeance, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EcheanceType::class, $echeance);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $echeance->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Échéance modifiée avec succès.');
            return $this->redirectToRoute('admin_echeance_index');
        }
        return $this->render('admin/echeance/edit.html.twig', ['echeance' => $echeance, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_echeance_delete', methods: ['POST'])]
    public function delete(Request $request, Echeance $echeance, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$echeance->getId(), $request->request->get('_token'))) {
            $em->remove($echeance);
            $em->flush();
            $this->addFlash('success', 'Échéance supprimée avec succès.');
        }
        return $this->redirectToRoute('admin_echeance_index');
    }
}
