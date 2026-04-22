<?php
namespace App\Controller\User;

use App\Entity\Credit;
use App\Form\UserCreditType;
use App\Repository\CreditRepository;
use App\Repository\CompteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user/credit')]
class CreditController extends AbstractController
{
    #[Route('/', name: 'user_credit_index', methods: ['GET'])]
    public function index(Request $request, CreditRepository $repo): Response
    {
        $query  = $request->query->get('q', '');
        $status = $request->query->get('status', '');

        return $this->render('user/credit/index.html.twig', [
            'credits' => $repo->search($query, $status),
            'q'       => $query,
            'status'  => $status,
        ]);
    }

    #[Route('/new', name: 'user_credit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CompteRepository $compteRepo): Response
    {
        $credit = new Credit();
        $form   = $this->createForm(UserCreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $credit->setStatus('en_attente');
            $credit->setDateDebut(new \DateTime());
            $credit->setMensualite($this->calculerMensualite($credit));

            $em->persist($credit);
            $em->flush();

            $this->addFlash('success', 'Votre demande de crédit a été soumise. Statut : En attente.');
            return $this->redirectToRoute('user_credit_index');
        }

        $comptes     = $compteRepo->findAll();
        $comptesData = [];
        foreach ($comptes as $c) {
            $comptesData[$c->getId()] = [
                'solde'        => (float) $c->getSolde(),
                'numeroCompte' => $c->getNumeroCompte(),
                'etat'         => $c->getEtat(),
            ];
        }

        return $this->render('user/credit/new.html.twig', [
            'credit'      => $credit,
            'form'        => $form,
            'comptesJson' => json_encode($comptesData),
        ]);
    }

    #[Route('/{id}', name: 'user_credit_show', methods: ['GET'])]
    public function show(Credit $credit): Response
    {
        return $this->render('user/credit/show.html.twig', ['credit' => $credit]);
    }

    #[Route('/{id}/edit', name: 'user_credit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Credit $credit, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserCreditType::class, $credit);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $credit->setMensualite($this->calculerMensualite($credit));
            $em->flush();
            $this->addFlash('success', 'Crédit modifié avec succès.');
            return $this->redirectToRoute('user_credit_index');
        }
        return $this->render('user/credit/edit.html.twig', ['credit' => $credit, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'user_credit_delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$credit->getId(), $request->request->get('_token'))) {
            $em->remove($credit);
            $em->flush();
            $this->addFlash('success', 'Crédit supprimé.');
        }
        return $this->redirectToRoute('user_credit_index');
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
