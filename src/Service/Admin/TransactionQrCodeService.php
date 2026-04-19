<?php

namespace App\Service\Admin;

use App\Entity\Transaction;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

final class TransactionQrCodeService
{
    public function generateDataUri(Transaction $transaction): string
    {
        $payload = [
            'id' => $transaction->getId(),
            'montant' => $transaction->getMontant(),
            'devise' => $transaction->getDevise(),
            'date' => $transaction->getDate()?->format('Y-m-d H:i:s'),
            'statut' => $transaction->getStatut(),
        ];

        $result = Builder::create()
            ->data((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(280)
            ->margin(12)
            ->build();

        return $result->getDataUri();
    }
}
