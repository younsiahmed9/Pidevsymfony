<?php
namespace App\Controller\Admin;

use App\Repository\CompteRepository;
use App\Repository\CreditRepository;
use App\Repository\DocumentRepository;
use App\Repository\DossierRepository;
use App\Repository\EcheanceRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function index(
        CompteRepository $compteRepo,
        CreditRepository $creditRepo,
        DocumentRepository $docRepo,
        DossierRepository $dossierRepo,
        EcheanceRepository $echeanceRepo,
        UtilisateurRepository $userRepo,
        CategorieRepository $catRepo,
    ): Response {
        $comptes    = $compteRepo->findAll();
        $credits    = $creditRepo->findAll();
        $documents  = $docRepo->findAll();
        $echeances  = $echeanceRepo->findAll();
        $users      = $userRepo->findAll();

        // Totals by statut
        $comptesActifs    = count(array_filter($comptes, fn($c) => $c->getEtat() === 'actif'));
        $creditsPending   = count(array_filter($credits, fn($c) => $c->getStatus() === 'en_attente'));
        $creditsApprouves = count(array_filter($credits, fn($c) => $c->getStatus() === 'approuve'));
        $docsExpires      = count(array_filter($documents, fn($d) => $d->getStatut() === 'expire'));
        $docsARenouveler  = count(array_filter($documents, fn($d) => $d->getStatut() === 'a_renouveler'));
        $echeancesOverdue = count($echeanceRepo->findOverdue());

        // Financial totals
        $totalSoldes  = array_sum(array_map(fn($c) => (float)$c->getSolde(), $comptes));
        $totalCredits = array_sum(array_map(fn($c) => (float)$c->getMontant(), $credits));

        // Upcoming echeances (next 30 days)
        $upcomingEch  = $echeanceRepo->search('', 'pending', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));

        return $this->render('admin/dashboard.html.twig', [
            'total_comptes'         => count($comptes),
            'total_credits'         => count($credits),
            'total_documents'       => count($documents),
            'total_dossiers'        => count($dossierRepo->findAll()),
            'total_users'           => count($users),
            'total_categories'      => count($catRepo->findAll()),
            'comptes_actifs'        => $comptesActifs,
            'credits_pending'       => $creditsPending,
            'credits_approuves'     => $creditsApprouves,
            'docs_expires'          => $docsExpires,
            'docs_a_renouveler'     => $docsARenouveler,
            'echeances_overdue'     => $echeancesOverdue,
            'total_soldes'          => $totalSoldes,
            'total_credits_montant' => $totalCredits,
            'recent_comptes'        => $compteRepo->findBy([], ['dateCreation' => 'DESC'], 6),
            'recent_credits'        => $creditRepo->findBy([], ['dateDebut' => 'DESC'], 5),
            'recent_documents'      => $docRepo->findBy([], ['createdAt' => 'DESC'], 5),
            'upcoming_echeances'    => $upcomingEch,
        ]);
    }
}
