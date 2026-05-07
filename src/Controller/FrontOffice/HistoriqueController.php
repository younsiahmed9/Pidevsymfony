<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/historique', name: 'front_historique_')]
final class HistoriqueController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $entityFilter = strtolower((string) $request->query->get('entity', 'all'));
        $allowedFilters = ['all', 'produit', 'service', 'facture'];
        if (!in_array($entityFilter, $allowedFilters, true)) {
            $entityFilter = 'all';
        }

        $connection = $entityManager->getConnection();
        $availableTables = $this->getAvailableAuditTables($connection);

        $sources = [
            'produit' => ['table' => 'produit_audit', 'label' => 'Produit'],
            'service' => ['table' => 'service_audit', 'label' => 'Service'],
            'facture' => ['table' => 'facture_audit', 'label' => 'Facture'],
        ];

        $selected = [];
        if ($entityFilter === 'all') {
            foreach ($sources as $key => $source) {
                if (in_array($source['table'], $availableTables, true)) {
                    $selected[$key] = $source;
                }
            }
        } elseif (isset($sources[$entityFilter]) && in_array($sources[$entityFilter]['table'], $availableTables, true)) {
            $selected[$entityFilter] = $sources[$entityFilter];
        }

        $limit = 120;
        $rows = [];

        if ($selected !== []) {
            $parts = [];
            foreach ($selected as $key => $source) {
                $parts[] = sprintf(
                    "SELECT '%s' AS entity_key, '%s' AS entity_label, id, type, object_id, blame_id, blame_user, created_at, diffs FROM %s WHERE (blame_id = :user_id OR blame_user = :user_email)",
                    $key,
                    $source['label'],
                    $source['table']
                );
            }

            $sql = implode(' UNION ALL ', $parts) . ' ORDER BY created_at DESC LIMIT ' . $limit;

            $rows = $connection->fetchAllAssociative($sql, [
                'user_id' => (string) $user->getId(),
                'user_email' => (string) $user->getEmail(),
            ]);

            $rows = $this->prepareRows($rows);
        }

        return $this->render('frontoffice/historique/index.html.twig', [
            'entityFilter' => $entityFilter,
            'historyRows' => $rows,
            'availableTables' => $availableTables,
        ]);
    }

    /**
     * @return list<string>
     */
    private function getAvailableAuditTables($connection): array
    {
        $rows = $connection->fetchFirstColumn("SHOW TABLES LIKE '%_audit'");

        return array_values(array_map(static fn ($value) => (string) $value, $rows));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function prepareRows(array $rows): array
    {
        $prepared = [];

        foreach ($rows as $row) {
            $action = strtolower((string) ($row['type'] ?? ''));
            $actionLabel = match ($action) {
                'insert' => 'Creation',
                'update' => 'Modification',
                'remove', 'delete' => 'Suppression',
                default => strtoupper((string) ($row['type'] ?? 'N/A')),
            };

            $actionClass = match ($action) {
                'insert' => 'success',
                'update' => 'warning',
                'remove', 'delete' => 'danger',
                default => 'secondary',
            };

            $diffsRaw = (string) ($row['diffs'] ?? '{}');
            $decoded = json_decode($diffsRaw, true);
            $changedFields = [];
            if (is_array($decoded)) {
                foreach ($decoded as $field => $value) {
                    if ($field === '@source') {
                        continue;
                    }
                    $changedFields[] = (string) $field;
                }
            }

            $changeCount = count($changedFields);
            $diffsPretty = is_array($decoded)
                ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $diffsRaw;

            $createdAt = (string) ($row['created_at'] ?? '');
            $createdAtFormatted = $createdAt !== ''
                ? (new \DateTimeImmutable($createdAt))->format('d/m/Y H:i:s')
                : '-';

            $prepared[] = array_merge($row, [
                'actionLabel' => $actionLabel,
                'actionClass' => $actionClass,
                'actor' => (string) (($row['blame_user'] ?? '') ?: ($row['blame_id'] ?? '-') ?: '-'),
                'diffsPretty' => $diffsPretty,
                'changeCount' => $changeCount,
                'changedFields' => $changedFields,
                'createdAtFormatted' => $createdAtFormatted,
            ]);
        }

        return $prepared;
    }
}
