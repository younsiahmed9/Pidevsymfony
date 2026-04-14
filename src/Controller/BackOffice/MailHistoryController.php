<?php

namespace App\Controller\BackOffice;

use App\Repository\MailDeliveryLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/mail-history', name: 'admin_mail_history_')]
final class MailHistoryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(MailDeliveryLogRepository $mailDeliveryLogRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $logs = $mailDeliveryLogRepository->findRecentGlobal(500);

        return $this->render('backoffice/mail_history/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}