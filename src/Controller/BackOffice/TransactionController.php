<?php

namespace App\Controller\BackOffice;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\Admin\PdfExportService;
use App\Service\Admin\TransactionQrCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/transaction', name: 'admin_transaction_')]
final class TransactionController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(TransactionRepository $transactionRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $transactions = $transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.carte_source', 'cs')->addSelect('cs')
            ->leftJoin('t.carte_dest', 'cd')->addSelect('cd')
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('backoffice/transaction/index.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Transaction $transaction, TransactionQrCodeService $transactionQrCodeService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('backoffice/transaction/show.html.twig', [
            'transaction' => $transaction,
            'qrCodeDataUri' => $transactionQrCodeService->generateDataUri($transaction),
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(TransactionRepository $transactionRepository, PdfExportService $pdfExportService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $transactions = $transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.carte_source', 'cs')->addSelect('cs')
            ->leftJoin('t.carte_dest', 'cd')->addSelect('cd')
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('backoffice/transaction/export_pdf.html.twig', [
            'transactions' => $transactions,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        return $pdfExportService->renderPdfResponse(
            $html,
            sprintf('admin-transactions-%s.pdf', (new \DateTimeImmutable())->format('Ymd-His'))
        );
    }
}
