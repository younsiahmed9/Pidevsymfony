<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Budget;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
// #[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(Utilisateur::class)->findAll();
        $conn = $em->getConnection();
        
        // 1. Basic Stats
        $activeUsers = 0;
        $activeClients = 0;
        $administrators = 0;
        foreach ($users as $user) {
            if ($user->isActive()) {
                $activeUsers++;
                if (in_array('ROLE_ADMIN', $user->getRoles())) $administrators++;
                else $activeClients++;
            }
        }

        // 2. Financial Metrics
        $totalExpenses = $conn->fetchOne('SELECT SUM(montant) FROM depense') ?: 0;
        $globalBudget = $conn->fetchOne('SELECT SUM(montant_total) FROM budget') ?: 0;
        $globalBalance = $globalBudget - $totalExpenses;

        // 3. Top Categories (based on budget names)
        $topCategories = $conn->fetchAllAssociative(
            'SELECT nom_budget as label, COUNT(*) as count FROM budget GROUP BY nom_budget ORDER BY count DESC LIMIT 5'
        );

        // 4. Recent Activity (Mix of new budgets and expenses)
        $recentActivity = $conn->fetchAllAssociative(
            "(SELECT 'budget' as type, nom_budget as detail, montant_total as amount, date_creation as date, u.full_name as user 
              FROM budget b JOIN utilisateur u ON b.id_utilisateur = u.id_utilisateur)
             UNION ALL
             (SELECT 'depense' as type, categorie as detail, montant as amount, date_depense as date, u.full_name as user 
              FROM depense d JOIN utilisateur u ON d.id_utilisateur = u.id_utilisateur)
             ORDER BY date DESC LIMIT 10"
        );

        // 5. Monthly Evolution (for chart)
        $monthlyStats = $conn->fetchAllAssociative(
            "SELECT DATE_FORMAT(date_depense, '%M') as month, SUM(montant) as total 
             FROM depense GROUP BY month ORDER BY MIN(date_depense) ASC LIMIT 6"
        );

        // 6. All Budgets for the funding tool
        $allBudgets = $em->getRepository(Budget::class)->findAll();

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'allBudgets' => $allBudgets,
            'stats' => [
                'totalUsers' => count($users),
                'activeUsers' => $activeUsers,
                'activeClients' => $activeClients,
                'administrators' => $administrators,
                'totalExpenses' => $totalExpenses,
                'globalBalance' => $globalBalance,
            ],
            'topCategories' => $topCategories,
            'recentActivity' => $recentActivity,
            'monthlyStats' => $monthlyStats,
        ]);
    }

    #[Route('/user/delete/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, Utilisateur $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_delete_user_' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/user/update/{id}', name: 'app_admin_user_update', methods: ['POST'])]
    public function updateUser(Request $request, Utilisateur $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_edit_user_' . $user->getId(), $request->request->get('_token'))) {
            $fullName = $request->request->get('full_name');
            $email = $request->request->get('email');
            $isActive = $request->request->get('is_active') === '1';

            $user->setFullName($fullName);
            $user->setEmail($email);
            $user->setIsActive($isActive);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $em->flush();
            $this->addFlash('success', 'User updated successfully.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/budget/fund/{id}', name: 'app_admin_budget_fund', methods: ['POST'])]
    public function fundBudget(Request $request, Budget $budget, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_fund_budget_' . $budget->getId(), $request->request->get('_token'))) {
            $amount = $request->request->get('amount');
            if ($amount > 0) {
                $newTotal = $budget->getMontantTotal() + $amount;
                $budget->setMontantTotal($newTotal);
                $em->flush();
                $this->addFlash('success', sprintf('Le budget "%s" a été crédité de %s €.', $budget->getNomBudget(), $amount));
            }
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/export/users', name: 'app_admin_export_users')]
    public function exportUsers(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(Utilisateur::class)->findAll();
        $csvHeader = ['ID', 'Full Name', 'Email', 'Status', 'Roles'];
        
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeader);

        foreach ($users as $user) {
            fputcsv($handle, [
                $user->getId(),
                $user->getFullName(),
                $user->getEmail(),
                $user->isActive() ? 'Active' : 'Inactive',
                implode(', ', $user->getRoles())
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="users_export.csv"');

        return $response;
    }
}
