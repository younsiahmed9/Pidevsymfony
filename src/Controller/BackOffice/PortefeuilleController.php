<?php

namespace App\Controller\BackOffice;

use App\Entity\Portefeuille;
use App\Repository\PortefeuilleRepository;
use App\Service\Admin\PdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/portefeuille', name: 'admin_portefeuille_')]
final class PortefeuilleController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PortefeuilleRepository $portefeuilleRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $portefeuilles = $portefeuilleRepository->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('backoffice/portefeuille/index.html.twig', [
            'portefeuilles' => $portefeuilles,
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(PortefeuilleRepository $portefeuilleRepository, PdfExportService $pdfExportService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $portefeuilles = $portefeuilleRepository->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('backoffice/portefeuille/export_pdf.html.twig', [
            'portefeuilles' => $portefeuilles,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        return $pdfExportService->renderPdfResponse(
            $html,
            sprintf('admin-portefeuilles-%s.pdf', (new \DateTimeImmutable())->format('Ymd-His'))
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Portefeuille $portefeuille, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete' . $portefeuille->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_portefeuille_index');
        }

        $cardsCount = (int) $entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM carte_virtuelle WHERE portefeuille_id = :pid',
            ['pid' => $portefeuille->getId()]
        );

        if ($cardsCount > 0) {
            $this->addFlash('warning', 'Suppression impossible: ce portefeuille contient des cartes.');

            return $this->redirectToRoute('admin_portefeuille_index');
        }

        $entityManager->remove($portefeuille);
        $entityManager->flush();

        $this->addFlash('success', 'Portefeuille supprimé avec succès.');

        return $this->redirectToRoute('admin_portefeuille_index');
    }
}
