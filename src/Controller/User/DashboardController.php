<?php
namespace App\Controller\User;

use App\Repository\CompteRepository;
use App\Repository\CreditRepository;
use App\Repository\DocumentRepository;
use App\Repository\EcheanceRepository;
use App\Repository\DossierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'user_dashboard')]
    public function index(
        CompteRepository $compteRepo,
        CreditRepository $creditRepo,
        DocumentRepository $docRepo,
        EcheanceRepository $echeanceRepo,
        DossierRepository $dossierRepo,
    ): Response {
        $comptes   = $compteRepo->findAll();
        $credits   = $creditRepo->findAll();
        $documents = $docRepo->findAll();
        $dossiers  = $dossierRepo->findAll();

        // Solde total
        $totalSolde = array_sum(array_map(fn($c) => (float)$c->getSolde(), $comptes));

        // Credits en attente
        $creditsPending = count(array_filter($credits, fn($c) => $c->getStatus() === 'en_attente'));

        // Documents expirés
        $docsAlerte = count(array_filter($documents, fn($d) => in_array($d->getStatut(), ['expire', 'a_renouveler'])));

        // Echéances
        $overdue  = $echeanceRepo->findOverdue();
        $upcoming = $echeanceRepo->search('', 'pending', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));

        // Score global: actif=100, bloque=40, clos=0
        $scoreGlobal = 0;
        if (count($comptes) > 0) {
            $scores = array_map(fn($c) => match($c->getEtat()) {
                'actif'  => 100,
                'bloque' => 40,
                default  => 0,
            }, $comptes);
            $scoreGlobal = (int)round(array_sum($scores) / count($scores));
        }

        // Utilisation crédit = total crédits / solde * 100
        $totalCredits = array_sum(array_map(fn($c) => (float)$c->getMontant(), $credits));
        $utilisationCredit = $totalSolde > 0 ? min(100, (int)round($totalCredits / $totalSolde * 100)) : 0;

        // Fiabilité paiement = 100 - (en_retard / total_echeances * 100)
        $totalEcheances = count($echeanceRepo->findAll());
        $fiabilite = $totalEcheances > 0 ? max(0, 100 - (int)round(count($overdue) / $totalEcheances * 100)) : 100;

        // Max empruntable = solde * 0.33
        $maxEmpruntable = round($totalSolde * 0.33, 2);

        return $this->render('user/dashboard.html.twig', [
            'comptes'            => $comptes,
            'credits'            => $credits,
            'documents'          => $documents,
            'dossiers'           => $dossiers,
            'total_solde'        => $totalSolde,
            'credits_pending'    => $creditsPending,
            'docs_alerte'        => $docsAlerte,
            'nb_overdue'         => count($overdue),
            'upcoming'           => $upcoming,
            'recent_docs'        => $docRepo->findBy([], ['createdAt' => 'DESC'], 4),
            'score_global'       => $scoreGlobal,
            'utilisation_credit' => $utilisationCredit,
            'fiabilite_paiement' => $fiabilite,
            'max_empruntable'    => $maxEmpruntable,
        ]);
    }
}
