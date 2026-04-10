<?php
namespace App\Controller\Admin;

use App\Entity\Credit;
use App\Form\CreditType;
use App\Repository\CreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/credit')]
class CreditController extends AbstractController
{
    #[Route('/', name: 'admin_credit_index', methods: ['GET'])]
    public function index(Request $request, CreditRepository $repo): Response
    {
        $query  = $request->query->get('q', '');
        $status = $request->query->get('status', '');

        return $this->render('admin/credit/index.html.twig', [
            'credits' => $repo->search($query, $status),
            'q'       => $query,
            'status'  => $status,
        ]);
    }

    #[Route('/new', name: 'admin_credit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $credit = new Credit();
        $form   = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $credit->setMensualite($this->calculerMensualite($credit));
            $em->persist($credit);
            $em->flush();
            $this->addFlash('success', 'Crédit créé avec succès.');
            return $this->redirectToRoute('admin_credit_index');
        }

        return $this->render('admin/credit/new.html.twig', ['credit' => $credit, 'form' => $form]);
    }

    #[Route('/{id}', name: 'admin_credit_show', methods: ['GET'])]
    public function show(Credit $credit): Response
    {
        return $this->render('admin/credit/show.html.twig', ['credit' => $credit]);
    }

    #[Route('/{id}/edit', name: 'admin_credit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Credit $credit, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $credit->setMensualite($this->calculerMensualite($credit));
            $em->flush();
            $this->addFlash('success', 'Crédit modifié avec succès.');
            return $this->redirectToRoute('admin_credit_index');
        }

        return $this->render('admin/credit/edit.html.twig', ['credit' => $credit, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_credit_delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$credit->getId(), $request->request->get('_token'))) {
            $em->remove($credit);
            $em->flush();
            $this->addFlash('success', 'Crédit supprimé avec succès.');
        }
        return $this->redirectToRoute('admin_credit_index');
    }

    private function calculerMensualite(Credit $credit): string
    {
        $p = (float)$credit->getMontant();
        $r = (float)$credit->getTauxInteret() / 100 / 12;
        $n = $credit->getDureeMois();
        if ($r > 0 && $n > 0) {
            $m = $p * $r / (1 - pow(1 + $r, -$n));
        } else {
            $m = $n > 0 ? $p / $n : 0;
        }
        return number_format($m, 2, '.', '');
    }
}
