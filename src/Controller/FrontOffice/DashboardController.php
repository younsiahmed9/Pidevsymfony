<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'front_dashboard_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $data = $this->getDashboardData($entityManager);

        if ($data instanceof Response) {
            return $data;
        }

        return $this->render('frontoffice/dashboard/index.html.twig', $data);
    }

    #[Route('/dashboard/pdf', name: 'front_dashboard_pdf', methods: ['GET'])]
    public function exportPdf(EntityManagerInterface $entityManager): Response
    {
        $data = $this->getDashboardData($entityManager);

        if ($data instanceof Response) {
            return $data;
        }

        $html = $this->renderView('frontoffice/dashboard/pdf.html.twig', $data);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $this->generatePdfDisposition(),
            ]
        );
    }

    private function getDashboardData(EntityManagerInterface $entityManager): array|Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_index');
        }

        $balanceTotal = (float) $entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(solde_total), 0) FROM portefeuille WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        // Fetch last transactions for the PDF/Dashboard
        $dernieres_transactions = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT t.* FROM transaction t 
             LEFT JOIN carte_virtuelle cv_src ON t.carte_source_id = cv_src.id 
             LEFT JOIN carte_virtuelle cv_dest ON t.carte_dest_id = cv_dest.id 
             LEFT JOIN portefeuille p_src ON cv_src.portefeuille_id = p_src.id 
             LEFT JOIN portefeuille p_dest ON cv_dest.portefeuille_id = p_dest.id 
             WHERE p_src.user_id = :uid OR p_dest.user_id = :uid 
             GROUP BY t.id 
             ORDER BY t.date DESC LIMIT 10',
            ['uid' => $user->getId()]
        );

        // --- Document Statistics ---
        $totalDocuments = (int) $entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM document WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        $totalCategories = (int) $entityManager->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT id_categorie) FROM document WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        $totalDossiers = (int) $entityManager->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT id_dossier) FROM document WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        $derniers_documents = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM document WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5',
            ['uid' => $user->getId()]
        );

        $documents_a_renouveler = (int) $entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM document WHERE user_id = :uid AND statut = 'a_renouveler'",
            ['uid' => $user->getId()]
        );

        $documents_archives = (int) $entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM document WHERE user_id = :uid AND statut = 'archive'",
            ['uid' => $user->getId()]
        );

        $portefeuilles = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT * FROM portefeuille WHERE user_id = :uid',
            ['uid' => $user->getId()]
        );

        $cartes_actives = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT cv.* FROM carte_virtuelle cv 
             JOIN portefeuille p ON cv.portefeuille_id = p.id 
             WHERE p.user_id = :uid AND cv.statut = "ACTIVE"',
            ['uid' => $user->getId()]
        );

        $virements_actifs = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT vp.* FROM virement_programme vp 
             JOIN carte_virtuelle cv ON vp.carte_source_id = cv.id 
             JOIN portefeuille p ON cv.portefeuille_id = p.id 
             WHERE p.user_id = :uid AND vp.is_active = 1',
            ['uid' => $user->getId()]
        );

        return [
            'balanceTotal' => $balanceTotal,
            'dernieres_transactions' => $dernieres_transactions,
            'portefeuilles' => $portefeuilles,
            'cartes_actives' => $cartes_actives,
            'virements_actifs' => $virements_actifs,
            'totalDocuments' => $totalDocuments,
            'totalCategories' => $totalCategories,
            'totalDossiers' => $totalDossiers,
            'derniers_documents' => $derniers_documents,
            'documentsARenouveler' => $documents_a_renouveler,
            'documentsArchives' => $documents_archives,
        ];
    }

    private function generatePdfDisposition(): string
    {
        return (new ResponseHeaderBag())->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'dashboard_fintrack_' . date('Y-m-d') . '.pdf'
        );
    }
}
