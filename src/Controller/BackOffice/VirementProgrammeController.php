<?php

namespace App\Controller\BackOffice;

use App\Repository\VirementProgrammeRepository;
use App\Service\Admin\PdfExportService;
use App\Service\Transfer\ScheduledTransferEngineConfigManager;
use App\Service\Transfer\ScheduledTransferEngineResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/virement-programme', name: 'admin_virement_programme_')]
final class VirementProgrammeController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(VirementProgrammeRepository $virementProgrammeRepository, ScheduledTransferEngineResolver $engineResolver, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $virements = $virementProgrammeRepository->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')->addSelect('u')
            ->leftJoin('v.carte_source', 'cs')->addSelect('cs')
            ->leftJoin('v.carte_dest', 'cd')->addSelect('cd')
            ->orderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();

        $symfonyLogs = [];
        $n8nLogs = [];

        try {
            $symfonyLogs = $entityManager->getConnection()->fetchAllAssociative(
                'SELECT id, virement_programme_id, execution_type, status, scheduled_for, executed_at, transaction_id, amount, currency, fee_amount, error_code
                 FROM transfer_execution_log
                 ORDER BY executed_at DESC
                 LIMIT 30'
            );
        } catch (\Throwable) {
            $symfonyLogs = [];
        }

        try {
            $n8nLogs = $entityManager->getConnection()->fetchAllAssociative(
                'SELECT id, virement_programme_id, execution_type, status, scheduled_for, executed_at, transaction_id, amount, currency, fee_amount, error_code
                 FROM transfer_execution_log_n8n
                 ORDER BY executed_at DESC
                 LIMIT 30'
            );
        } catch (\Throwable) {
            $n8nLogs = [];
        }

        return $this->render('backoffice/virement_programme/index.html.twig', [
            'virements' => $virements,
            'engine' => $engineResolver->getEngine(),
            'symfonyLogs' => $symfonyLogs,
            'n8nLogs' => $n8nLogs,
        ]);
    }

    #[Route('/engine/{engine}', name: 'switch_engine', methods: ['POST'])]
    public function switchEngine(string $engine, Request $request, ScheduledTransferEngineConfigManager $configManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('switch_engine_' . $engine, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_virement_programme_index');
        }

        try {
            $configManager->setEngine($engine);
            $this->addFlash('success', sprintf('Moteur basculé vers "%s". Pense à vider le cache si besoin.', $engine));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Impossible de basculer le moteur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_virement_programme_index');
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(VirementProgrammeRepository $virementProgrammeRepository, PdfExportService $pdfExportService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $virements = $virementProgrammeRepository->createQueryBuilder('v')
            ->leftJoin('v.user', 'u')->addSelect('u')
            ->leftJoin('v.carte_source', 'cs')->addSelect('cs')
            ->leftJoin('v.carte_dest', 'cd')->addSelect('cd')
            ->orderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('backoffice/virement_programme/export_pdf.html.twig', [
            'virements' => $virements,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        return $pdfExportService->renderPdfResponse(
            $html,
            sprintf('admin-virements-programmes-%s.pdf', (new \DateTimeImmutable())->format('Ymd-His'))
        );
    }
}
