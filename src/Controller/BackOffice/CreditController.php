<?php

namespace App\Controller\BackOffice;

use App\Entity\Credit;
use App\Form\AdminCreditType;
use App\Repository\CreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/credit', name: 'admin_credit_')]
class CreditController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, CreditRepository $creditRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $status = $request->query->get('status', '');

        $credits = $creditRepository->search($q, $status);

        return $this->render('backoffice/credit/index.html.twig', [
            'credits' => $credits,
            'q' => $q,
            'status' => $status,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $credit = new Credit();
        $form = $this->createForm(AdminCreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calcul simple de la mensualité (formule standard)
            $montant = (float) $credit->getMontant();
            $tauxAnnuel = (float) $credit->getTauxInteret() / 100;
            $duree = $credit->getDureeMois();

            if ($tauxAnnuel > 0) {
                $tauxMensuel = $tauxAnnuel / 12;
                $mensualite = ($montant * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$duree));
            } else {
                $mensualite = $montant / $duree;
            }

            $credit->setMensualite(number_format($mensualite, 2, '.', ''));

            $entityManager->persist($credit);
            $entityManager->flush();

            $this->addFlash('success', 'Crédit enregistré avec succès.');
            return $this->redirectToRoute('admin_credit_index');
        }

        return $this->render('backoffice/credit/new.html.twig', [
            'credit' => $credit,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Credit $credit): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('backoffice/credit/show.html.twig', [
            'credit' => $credit,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AdminCreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalcul mensualité
            $montant = (float) $credit->getMontant();
            $tauxAnnuel = (float) $credit->getTauxInteret() / 100;
            $duree = $credit->getDureeMois();

            if ($tauxAnnuel > 0) {
                $tauxMensuel = $tauxAnnuel / 12;
                $mensualite = ($montant * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$duree));
            } else {
                $mensualite = $montant / $duree;
            }

            $credit->setMensualite(number_format($mensualite, 2, '.', ''));

            $entityManager->flush();

            $this->addFlash('success', 'Crédit mis à jour.');
            return $this->redirectToRoute('admin_credit_index');
        }

        return $this->render('backoffice/credit/edit.html.twig', [
            'credit' => $credit,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$credit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($credit);
            $entityManager->flush();
            $this->addFlash('success', 'Crédit supprimé.');
        }

        return $this->redirectToRoute('admin_credit_index');
    }
}
