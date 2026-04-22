<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Echeance;
use App\Entity\User;
use App\Repository\EcheanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Advanced business logic for Echeances (deadlines).
 *
 * Responsibilities:
 *   - Automatic overdue detection & status sync
 *   - Smart reminder date calculation
 *   - OCR-sourced echeance creation from Document dates
 *   - Statistics for user & admin dashboards
 */
class EcheanceService
{
    private const REMINDER_DEFAULT_DAYS_BEFORE = 3;
    private const URGENT_THRESHOLD_DAYS        = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EcheanceRepository     $echeanceRepository,
        private readonly LoggerInterface         $logger,
    ) {
    }

    // ─────────────────────────────────────────────────────────────
    //  STATUS MAINTENANCE
    // ─────────────────────────────────────────────────────────────

    /**
     * Scan ALL pending/notified echeances and mark as overdue if past due.
     * Intended to be called from a console command or cron job.
     *
     * @return int  Number of echeances updated
     */
    public function updateOverdueStatuses(): int
    {
        $today     = new \DateTimeImmutable();
        $updated   = 0;

        $pending = $this->echeanceRepository->createQueryBuilder('e')
            ->where('e.statut IN (:statuts)')
            ->andWhere('e.dateEcheance < :today')
            ->setParameter('statuts', ['pending', 'notified'])
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        foreach ($pending as $echeance) {
            /** @var Echeance $echeance */
            $echeance->setStatut('overdue');
            $echeance->setUpdatedAt(new \DateTime());
            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
            $this->logger->info(sprintf('EcheanceService: marked %d echeance(s) as overdue.', $updated));
        }

        return $updated;
    }

    /**
     * Mark echeances whose dateRappel is today as "notified".
     *
     * @return int  Number of echeances updated
     */
    public function markTodayReminders(): int
    {
        $today   = (new \DateTimeImmutable())->setTime(0, 0);
        $updated = 0;

        $due = $this->echeanceRepository->createQueryBuilder('e')
            ->where('e.statut = :pending')
            ->andWhere('e.dateRappel IS NOT NULL')
            ->andWhere('e.dateRappel <= :today')
            ->setParameter('pending', 'pending')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        foreach ($due as $echeance) {
            /** @var Echeance $echeance */
            $echeance->setStatut('notified');
            $echeance->setUpdatedAt(new \DateTime());
            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
            $this->logger->info(sprintf('EcheanceService: notified %d echeance(s).', $updated));
        }

        return $updated;
    }

    // ─────────────────────────────────────────────────────────────
    //  SMART REMINDER CALCULATION
    // ─────────────────────────────────────────────────────────────

    /**
     * Auto-compute the reminder date for an echeance.
     *
     * Rules (applied in priority order):
     *   – Amounts ≥ 1 000 → 7 days before
     *   – Keyword "contrat" or "assurance" in title → 14 days before
     *   – Default → REMINDER_DEFAULT_DAYS_BEFORE days before
     *
     * @param Echeance $echeance  (must already have dateEcheance set)
     */
    public function autoSetReminder(Echeance $echeance): void
    {
        $due = $echeance->getDateEcheance();
        if ($due === null) {
            return;
        }

        $daysBefore = self::REMINDER_DEFAULT_DAYS_BEFORE;

        // High-value rule
        $montant = (float) ($echeance->getMontant() ?? '0');
        if ($montant >= 1000) {
            $daysBefore = 7;
        }

        // High-importance document type rule
        $titre = mb_strtolower($echeance->getTitre());
        if (preg_match('/contrat|assurance|fiscal|bail/u', $titre)) {
            $daysBefore = max($daysBefore, 14);
        }

        $reminderDate = \DateTime::createFromInterface($due);
        $reminderDate->modify(sprintf('-%d days', $daysBefore));

        // Only set if reminder is still in the future
        $now = new \DateTime();
        if ($reminderDate > $now) {
            $echeance->setDateRappel($reminderDate);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  OCR / DOCUMENT SYNCHRONISATION
    // ─────────────────────────────────────────────────────────────

    /**
     * If a Document has a dateEcheance, ensure a matching Echeance entity exists.
     * Creates one if missing, updates if already there.
     *
     * @return Echeance  The created or updated entity (NOT flushed — caller decides)
     */
    public function syncFromDocument(Document $document): ?Echeance
    {
        $due = $document->getDateEcheance();
        if ($due === null) {
            return null;
        }

        $user = $document->getUtilisateur();
        if ($user === null) {
            return null;
        }

        // Look for an existing echeance linked to this document
        $echeance = $this->echeanceRepository->findOneBy([
            'document'   => $document,
            'utilisateur' => $user,
        ]);

        if ($echeance === null) {
            $echeance = new Echeance();
            $echeance->setDocument($document);
            $echeance->setUtilisateur($user);
            $echeance->setStatut('pending');
            $this->entityManager->persist($echeance);
        }

        $echeance->setTitre('Échéance – ' . $document->getTitre());
        $echeance->setDateEcheance(\DateTime::createFromInterface($due));
        $echeance->setUpdatedAt(new \DateTime());
        $this->autoSetReminder($echeance);

        $this->logger->info(sprintf(
            'EcheanceService: synced echeance for document #%d (%s)',
            $document->getId() ?? 0,
            $document->getTitre()
        ));

        return $echeance;
    }

    // ─────────────────────────────────────────────────────────────
    //  STATISTICS
    // ─────────────────────────────────────────────────────────────

    /**
     * Returns rich stats for a user's echeance dashboard panel.
     */
    public function getUserStats(User $user): array
    {
        $today  = new \DateTimeImmutable();
        $urgent = $today->modify('+' . self::URGENT_THRESHOLD_DAYS . ' days');

        $all = $this->echeanceRepository->findBy(['utilisateur' => $user]);

        $total     = count($all);
        $pending   = 0;
        $completed = 0;
        $overdue   = 0;
        $notified  = 0;
        $urgentCount = 0;
        $totalAmount = 0.0;

        foreach ($all as $e) {
            /** @var Echeance $e */
            $status = $e->getStatut();
            match ($status) {
                'pending'   => ++$pending,
                'completed' => ++$completed,
                'overdue'   => ++$overdue,
                'notified'  => ++$notified,
                default     => null,
            };

            // Urgent: pending/notified and due within threshold
            $due = $e->getDateEcheance();
            if ($due !== null && in_array($status, ['pending', 'notified'], true)) {
                $dueImmutable = \DateTimeImmutable::createFromInterface($due);
                if ($dueImmutable >= $today && $dueImmutable <= $urgent) {
                    ++$urgentCount;
                }
            }

            $totalAmount += (float) ($e->getMontant() ?? '0');
        }

        return [
            'total'        => $total,
            'pending'      => $pending,
            'completed'    => $completed,
            'overdue'      => $overdue,
            'notified'     => $notified,
            'urgent'       => $urgentCount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Returns global admin stats for all echeances.
     */
    public function getAdminStats(): array
    {
        $conn = $this->entityManager->getConnection();

        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN statut = "pending"   THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN statut = "completed" THEN 1 ELSE 0 END), 0) AS completed,
                COALESCE(SUM(CASE WHEN statut = "overdue"   THEN 1 ELSE 0 END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN statut = "notified"  THEN 1 ELSE 0 END), 0) AS notified,
                COALESCE(SUM(CAST(COALESCE(montant, 0) AS DECIMAL(15,2))), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN date_echeance BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                               AND statut IN ("pending","notified") THEN 1 ELSE 0 END), 0) AS urgent,
                COUNT(DISTINCT user_id) AS nb_users
            FROM echeance'
        ) ?: [];

        $byMonth = $conn->fetchAllAssociative(
            'SELECT DATE_FORMAT(date_echeance, "%Y-%m") AS month_key,
                    DATE_FORMAT(date_echeance, "%b %Y") AS month_label,
                    COUNT(*) AS cnt
             FROM echeance
             WHERE date_echeance >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month_key, month_label ORDER BY month_key ASC'
        );

        return [
            'total'        => (int) ($row['total']        ?? 0),
            'pending'      => (int) ($row['pending']      ?? 0),
            'completed'    => (int) ($row['completed']    ?? 0),
            'overdue'      => (int) ($row['overdue']      ?? 0),
            'notified'     => (int) ($row['notified']     ?? 0),
            'urgent'       => (int) ($row['urgent']       ?? 0),
            'nb_users'     => (int) ($row['nb_users']     ?? 0),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'by_month'     => $byMonth,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Returns echeances that require attention (overdue or due within N days).
     *
     * @param User|null $user  Pass null to get system-wide results (admin use)
     */
    public function getUrgentEcheances(?User $user = null, int $withinDays = 7): array
    {
        $today  = new \DateTimeImmutable();
        $limit  = $today->modify("+{$withinDays} days");

        $qb = $this->echeanceRepository->createQueryBuilder('e')
            ->leftJoin('e.document', 'd')
            ->addSelect('d')
            ->where('e.statut IN (:statuts)')
            ->andWhere('e.dateEcheance <= :limit')
            ->setParameter('statuts', ['pending', 'notified', 'overdue'])
            ->setParameter('limit', $limit)
            ->orderBy('e.dateEcheance', 'ASC');

        if ($user !== null) {
            $qb->andWhere('e.utilisateur = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calculate the number of days remaining until an echeance's due date.
     * Negative = already overdue.
     */
    public function daysUntilDue(Echeance $echeance): ?int
    {
        $due = $echeance->getDateEcheance();
        if ($due === null) {
            return null;
        }

        $today = new \DateTimeImmutable('today midnight');
        $diff  = $today->diff(\DateTimeImmutable::createFromInterface($due));

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Convenience: returns a human-readable urgency label.
     */
    public function urgencyLabel(Echeance $echeance): string
    {
        $days = $this->daysUntilDue($echeance);

        if ($days === null) {
            return 'Aucune date';
        }
        if ($days < 0) {
            return sprintf('En retard de %d jour(s)', abs($days));
        }
        if ($days === 0) {
            return "Aujourd'hui !";
        }
        if ($days <= 3) {
            return sprintf('Urgent – J-%d', $days);
        }
        if ($days <= self::URGENT_THRESHOLD_DAYS) {
            return sprintf('Bientôt – J-%d', $days);
        }

        return sprintf('J-%d', $days);
    }
}
