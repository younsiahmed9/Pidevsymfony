<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Repository\MailDeliveryLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MailHistoryController extends AbstractController
{
    #[Route('/mail-history', name: 'front_mail_history_index', methods: ['GET'])]
    public function index(MailDeliveryLogRepository $mailDeliveryLogRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $logs = $mailDeliveryLogRepository->findRecentForUser($user, 200);

        return $this->render('frontoffice/mail_history/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}