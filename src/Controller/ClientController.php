<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Form\BudgetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_USER')]
class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $conn = $em->getConnection();
        
        // Comprehensive SQL to get budgets + spent amount
        $budgets = $conn->fetchAllAssociative(
            'SELECT b.id_budget as id, b.nom_budget, b.montant_total, b.periode, b.statut, b.date_creation,
                    (SELECT COALESCE(SUM(d.montant), 0) FROM depense d WHERE d.id_budget = b.id_budget) as total_depense
             FROM budget b 
             WHERE b.id_utilisateur = :uid',
            ['uid' => $user->getId()]
        );

        // Raw SQL for expenses
        $depenses = $conn->fetchAllAssociative(
            'SELECT d.*, b.nom_budget as budget_name 
             FROM depense d 
             LEFT JOIN budget b ON d.id_budget = b.id_budget 
             WHERE d.id_utilisateur = :uid 
             ORDER BY d.date_depense DESC',
            ['uid' => $user->getId()]
        );

        return $this->render('client/index.html.twig', [
            'budgets' => $budgets,
            'depenses' => $depenses,
        ]);
    }

    #[Route('/depense/new', name: 'app_client_depense_new')]
    public function newDepense(Request $request, EntityManagerInterface $em): Response
    {
        $depense = new \App\Entity\Depense();
        $depense->setUtilisateur($this->getUser());
        
        // Pre-select budget and set category by default
        $budgetId = $request->query->get('budget');
        if ($budgetId) {
            $budget = $em->getRepository(\App\Entity\Budget::class)->find($budgetId);
            if ($budget && ($budget->getUtilisateur() === $this->getUser() || $this->getUser()->getId() == $budget->getUtilisateur()->getId())) {
                $depense->setBudget($budget);
                $depense->setCategorie($budget->getNomBudget()); // Set category as budget name by default
            }
        }

        $form = $this->createForm(\App\Form\DepenseType::class, $depense, [
            'user' => $this->getUser()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($depense);
            $em->flush();

            $this->addFlash('success', 'Dépense enregistrée !');
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('client/depense_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter une Dépense'
        ]);
    }

    #[Route('/depense/edit/{id}', name: 'app_client_depense_edit')]
    public function editDepense(Request $request, \App\Entity\Depense $depense, EntityManagerInterface $em): Response
    {
        if ($depense->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(\App\Form\DepenseType::class, $depense, [
            'user' => $this->getUser()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Dépense mise à jour !');
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('client/depense_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier la Dépense'
        ]);
    }

    #[Route('/depense/delete/{id}', name: 'app_client_depense_delete', methods: ['POST'])]
    public function deleteDepense(Request $request, \App\Entity\Depense $depense, EntityManagerInterface $em): Response
    {
        if ($depense->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_depense'.$depense->getId(), $request->request->get('_token'))) {
            $em->remove($depense);
            $em->flush();
            $this->addFlash('success', 'Dépense supprimée !');
        }

        return $this->redirectToRoute('app_client_dashboard');
    }

    #[Route('/budget/new', name: 'app_client_budget_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $budget = new Budget();
        $budget->setUtilisateur($this->getUser());
        
        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($budget);
            $em->flush();

            $this->addFlash('success', 'Budget créé avec succès !');
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('client/budget_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Nouveau Budget'
        ]);
    }

    #[Route('/budget/edit/{id}', name: 'app_client_budget_edit')]
    public function edit(Request $request, Budget $budget, EntityManagerInterface $em): Response
    {
        // Check if the budget belongs to the current user
        if ($budget->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce budget.');
        }

        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Budget mis à jour avec succès !');
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('client/budget_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier le Budget'
        ]);
    }

    #[Route('/budget/delete/{id}', name: 'app_client_budget_delete', methods: ['POST'])]
    public function delete(Request $request, Budget $budget, EntityManagerInterface $em): Response
    {
        if ($budget->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce budget.');
        }

        if ($this->isCsrfTokenValid('delete'.$budget->getId(), $request->request->get('_token'))) {
            $em->remove($budget);
            $em->flush();
            $this->addFlash('success', 'Budget supprimé avec succès !');
        }

        return $this->redirectToRoute('app_client_dashboard');
    }
}
