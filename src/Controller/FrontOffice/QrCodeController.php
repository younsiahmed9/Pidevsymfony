<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/qrcode')]
final class QrCodeController extends AbstractController
{
    #[Route('/wallet/{id}', name: 'front_qrcode_wallet', methods: ['GET'])]
    public function walletQr(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('Non autorisé', 401);
        }

        $wallet = $entityManager->getConnection()->fetchAssociative(
            'SELECT p.id, p.nom, p.devise_principale, p.solde_total, u.full_name, u.email
             FROM portefeuille p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.id = :id AND p.user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$wallet) {
            return new Response('Portefeuille introuvable', 404);
        }

        $cartes = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT numero_carte, type, solde, devise FROM carte_virtuelle WHERE portefeuille_id = :pid',
            ['pid' => $id]
        );

        $cardDetails = "";
        foreach ($cartes as $c) {
            $cardDetails .= sprintf("\n- Carte %s: %s (%s %s)", 
                $c['type'], 
                $c['numero_carte'], 
                number_format((float)$c['solde'], 2, ',', ' '), 
                $c['devise']
            );
        }

        $data = sprintf(
            "FINTRACK - PORTEFEUILLE\n-------------------\nNom: %s\nPropriétaire: %s\nSolde Global: %s %s\n\nCARTES RATTACHÉES:%s",
            $wallet['nom'],
            $wallet['full_name'] ?: $wallet['email'],
            number_format((float)$wallet['solde_total'], 2, ',', ' '),
            $wallet['devise_principale'],
            $cardDetails ?: "\nAucune carte"
        );
        
        return $this->generateQr($data);
    }

    #[Route('/card/{id}', name: 'front_qrcode_card', methods: ['GET'])]
    public function cardQr(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('Non autorisé', 401);
        }

        $carte = $entityManager->getConnection()->fetchAssociative(
            'SELECT c.numero_carte, c.cvv, c.date_expiration, c.solde, c.devise, c.type, 
                    p.nom AS wallet_nom, u.full_name
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             INNER JOIN users u ON u.id = p.user_id
             WHERE c.id = :id AND p.user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$carte) {
            return new Response('Carte introuvable', 404);
        }

        $data = sprintf(
            "FINTRACK - CARTE VIRTUELLE\n-------------------\nType: %s\nNuméro: %s\nCVV: %s\nExpiration: %s\nSolde: %s %s\n\nPropriétaire: %s\nPortefeuille: %s",
            $carte['type'],
            $carte['numero_carte'],
            $carte['cvv'] ?: '***',
            $carte['date_expiration'] ? (new \DateTime($carte['date_expiration']))->format('m/y') : 'N/A',
            number_format((float)$carte['solde'], 2, ',', ' '),
            $carte['devise'],
            $carte['full_name'] ?: $user->getEmail(),
            $carte['wallet_nom']
        );
        
        return $this->generateQr($data);
    }

    private function generateQr(string $data): Response
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return new Response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }
}
