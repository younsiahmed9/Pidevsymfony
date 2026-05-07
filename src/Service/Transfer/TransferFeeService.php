<?php

namespace App\Service\Transfer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class TransferFeeService
{
    private const BASE_RATE = 0.02;
    private const DISCOUNT_RATE = 0.01;
    private const DISCOUNT_THRESHOLD = 5;

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array{baseRate:float,appliedRate:float,feeAmount:float,previousTransfers:int}
     */
    public function calculateFeeForSourceCard(int $sourceCardId, float $amountInSourceCurrency): array
    {
        if ($sourceCardId <= 0) {
            throw new \InvalidArgumentException('Invalid source card id.');
        }

        $previousTransfers = (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM transaction
             WHERE carte_source_id = :sourceCardId
               AND statut IN (:statuses)
               AND type IN (:types)',
            [
                'sourceCardId' => $sourceCardId,
                'statuses' => ['SUCCESS', 'COMPLETED'],
                'types' => ['TRANSFERT', 'VIREMENT_PROGRAMME', 'TRANSFERT_PROGRAMME'],
            ],
            [
                'statuses' => ArrayParameterType::STRING,
                'types' => ArrayParameterType::STRING,
            ]
        );

        $appliedRate = $previousTransfers >= self::DISCOUNT_THRESHOLD ? self::DISCOUNT_RATE : self::BASE_RATE;
        $feeAmount = round(max($amountInSourceCurrency, 0) * $appliedRate, 2);

        return [
            'baseRate' => self::BASE_RATE,
            'appliedRate' => $appliedRate,
            'feeAmount' => $feeAmount,
            'previousTransfers' => $previousTransfers,
        ];
    }
}
