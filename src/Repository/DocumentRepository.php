<?php
namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function search(string $query = '', string $type = '', string $statut = '', ?int $categorieId = null, ?int $dossierId = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.utilisateur', 'u')
            ->leftJoin('d.categorie', 'cat')
            ->leftJoin('d.dossier', 'dos')
            ->leftJoin('d.tags', 't')
            ->addSelect('u', 'cat', 'dos', 't')
            ->distinct()
            ->andWhere('d.deletedAt IS NULL');

        if ($query) {
            $normalizedQuery = mb_strtolower($query);

            $qb->andWhere('LOWER(d.titre) LIKE :q OR LOWER(t.nomTag) LIKE :q OR LOWER(u.fullName) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(d.typeDocument) LIKE :q')
               ->setParameter('q', '%'.$normalizedQuery.'%');
        }
        if ($type) {
            $qb->andWhere('d.typeDocument = :type')->setParameter('type', $type);
        }
        if ($statut) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $statut);
        }
        if ($categorieId) {
            $qb->andWhere('cat.id = :cat')->setParameter('cat', $categorieId);
        }
        if ($dossierId) {
            $qb->andWhere('dos.id = :dos')->setParameter('dos', $dossierId);
        }

        return $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }

    public function findExpiringDocuments(int $days = 30): array
    {
        $dateLimit = new \DateTime('+' . $days . ' days');

        return $this->createQueryBuilder('d')
            ->where('d.dateEcheance IS NOT NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.dateEcheance <= :dateLimit')
            ->andWhere('d.dateEcheance >= :today')
            ->andWhere('d.statut IN (:statuts)')
            ->setParameter('dateLimit', $dateLimit)
            ->setParameter('today', new \DateTime())
            ->setParameter('statuts', ['valide', 'a_renouveler'])
            ->orderBy('d.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Stats for a single user's document dashboard
     */
    public function getStats(\App\Entity\User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $userId = $user->getId();

        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN statut = "valide" THEN 1 ELSE 0 END), 0) AS valide,
                COALESCE(SUM(CASE WHEN statut = "expire" THEN 1 ELSE 0 END), 0) AS expire,
                COALESCE(SUM(CASE WHEN statut = "a_renouveler" THEN 1 ELSE 0 END), 0) AS a_renouveler,
                COALESCE(SUM(CASE WHEN statut = "archive" THEN 1 ELSE 0 END), 0) AS archive,
                COALESCE(SUM(taille_fichier), 0) AS total_size,
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS this_month,
                COALESCE(SUM(CASE WHEN date_echeance IS NOT NULL AND date_echeance BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND statut != "archive" THEN 1 ELSE 0 END), 0) AS expires_soon
            FROM document WHERE user_id = :uid AND deleted_at IS NULL',
            ['uid' => $userId]
        ) ?: [];

        $byType = $conn->fetchAllAssociative(
            'SELECT type_document, COUNT(*) AS cnt FROM document WHERE user_id = :uid AND deleted_at IS NULL GROUP BY type_document ORDER BY cnt DESC',
            ['uid' => $userId]
        );

        $byMonth = $conn->fetchAllAssociative(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month_key, DATE_FORMAT(created_at, "%b %Y") AS month_label, COUNT(*) AS cnt
             FROM document WHERE user_id = :uid AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month_key, month_label ORDER BY month_key ASC',
            ['uid' => $userId]
        );

        return [
            'total'       => (int)($row['total'] ?? 0),
            'valide'      => (int)($row['valide'] ?? 0),
            'expire'      => (int)($row['expire'] ?? 0),
            'a_renouveler'=> (int)($row['a_renouveler'] ?? 0),
            'archive'     => (int)($row['archive'] ?? 0),
            'total_size'  => (int)($row['total_size'] ?? 0),
            'this_month'  => (int)($row['this_month'] ?? 0),
            'expires_soon'=> (int)($row['expires_soon'] ?? 0),
            'by_type'     => $byType,
            'by_month'    => $byMonth,
        ];
    }

    /**
     * Global stats for the admin document dashboard
     */
    public function getAdminStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $row = $conn->fetchAssociative(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN statut = "valide" THEN 1 ELSE 0 END), 0) AS valide,
                COALESCE(SUM(CASE WHEN statut = "expire" THEN 1 ELSE 0 END), 0) AS expire,
                COALESCE(SUM(CASE WHEN statut = "a_renouveler" THEN 1 ELSE 0 END), 0) AS a_renouveler,
                COALESCE(SUM(CASE WHEN statut = "archive" THEN 1 ELSE 0 END), 0) AS archive,
                COALESCE(SUM(taille_fichier), 0) AS total_size,
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS this_month,
                COUNT(DISTINCT user_id) AS nb_users,
                COALESCE(SUM(CASE WHEN date_echeance IS NOT NULL AND date_echeance BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS expires_soon
            FROM document WHERE deleted_at IS NULL'
        ) ?: [];

        $byType = $conn->fetchAllAssociative(
            'SELECT type_document, COUNT(*) AS cnt FROM document WHERE deleted_at IS NULL GROUP BY type_document ORDER BY cnt DESC'
        );

        $byMonth = $conn->fetchAllAssociative(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month_key, DATE_FORMAT(created_at, "%b %Y") AS month_label, COUNT(*) AS cnt
             FROM document WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month_key, month_label ORDER BY month_key ASC'
        );

        $topUsers = $conn->fetchAllAssociative(
            'SELECT u.full_name, u.email, COUNT(d.id_document) AS nb
             FROM document d JOIN users u ON u.id = d.user_id
             WHERE d.deleted_at IS NULL
             GROUP BY d.user_id, u.full_name, u.email ORDER BY nb DESC LIMIT 5'
        );

        return [
            'total'       => (int)($row['total'] ?? 0),
            'valide'      => (int)($row['valide'] ?? 0),
            'expire'      => (int)($row['expire'] ?? 0),
            'a_renouveler'=> (int)($row['a_renouveler'] ?? 0),
            'archive'     => (int)($row['archive'] ?? 0),
            'total_size'  => (int)($row['total_size'] ?? 0),
            'this_month'  => (int)($row['this_month'] ?? 0),
            'nb_users'    => (int)($row['nb_users'] ?? 0),
            'expires_soon'=> (int)($row['expires_soon'] ?? 0),
            'by_type'     => $byType,
            'by_month'    => $byMonth,
            'top_users'   => $topUsers,
        ];
    }
}
