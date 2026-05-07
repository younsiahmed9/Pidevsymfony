<?php

namespace App\Controller\Integration;

use App\Service\Transfer\ScheduledTransferEngineResolver;
use App\Service\Transfer\ScheduledTransferExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/internal/n8n/scheduled-transfers', name: 'internal_n8n_scheduled_transfer_')]
final class N8nScheduledTransferController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScheduledTransferExecutionService $executionService,
        private ScheduledTransferEngineResolver $engineResolver,
        private LoggerInterface $logger,
        private string $internalToken,
        private string $symfonyBaseUrl,
    ) {
    }

    #[Route('/due', name: 'due', methods: ['GET'])]
    public function due(Request $request): JsonResponse
    {
        if (($authError = $this->assertToken($request)) !== null) {
            return $authError;
        }

        if (!$this->engineResolver->isN8n()) {
            return $this->json([
                'ok' => false,
                'message' => 'Engine is not n8n. Switch SCHEDULED_TRANSFER_ENGINE=n8n first.',
            ], 409);
        }

        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, user_id, carte_source_id, carte_dest_id, montant, devise, frequence, prochaine_execution
             FROM virement_programme
             WHERE actif = 1
               AND statut IN ("PENDING", "ACTIVE", "FAILED")
               AND prochaine_execution IS NOT NULL
               AND prochaine_execution <= :now
             ORDER BY prochaine_execution ASC
             LIMIT ' . $limit,
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
        );

        $baseUrl = rtrim($this->symfonyBaseUrl, '/');
        
        foreach ($rows as &$row) {
            $row['execute_url'] = $baseUrl . '/internal/n8n/scheduled-transfers/' . $row['id'] . '/execute';
        }

        return $this->json([
            'ok' => true,
            'count' => count($rows),
            'rows' => $rows,
        ]);
    }

    #[Route('/{id}/execute', name: 'execute_one', methods: ['POST'])]
    public function executeOne(int $id, Request $request): JsonResponse
    {
        if (($authError = $this->assertToken($request)) !== null) {
            return $authError;
        }

        if (!$this->engineResolver->isN8n()) {
            return $this->json([
                'ok' => false,
                'message' => 'Engine is not n8n.',
            ], 409);
        }

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT id, user_id, carte_source_id, carte_dest_id, montant, devise, frequence, prochaine_execution
             FROM virement_programme
             WHERE id = :id
               AND actif = 1
               AND statut IN ("PENDING", "ACTIVE", "FAILED")',
            ['id' => $id]
        );

        if (!$row) {
            return $this->json([
                'ok' => false,
                'message' => 'Scheduled transfer not active or not found.',
            ], 404);
        }

        try {
            $this->executionService->executeSingleScheduledTransfer($row, 'n8n');

            return $this->json([
                'ok' => true,
                'scheduled_id' => $id,
                'engine' => 'n8n',
            ]);
        } catch (\Throwable $e) {
            $this->executionService->handleExecutionFailure($row, $e, 'n8n');

            $this->logger->error('n8n execute failed', [
                'scheduled_id' => $id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return $this->json([
                'ok' => false,
                'scheduled_id' => $id,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    #[Route('/execute-all', name: 'execute_all', methods: ['POST'])]
    public function executeAll(Request $request): JsonResponse
    {
        if (($authError = $this->assertToken($request)) !== null) {
            return $authError;
        }

        if (!$this->engineResolver->isN8n()) {
            return $this->json([
                'ok' => false,
                'message' => 'Engine is not n8n.',
            ], 409);
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, user_id, carte_source_id, carte_dest_id, montant, devise, frequence, prochaine_execution
             FROM virement_programme
             WHERE actif = 1
               AND statut IN ("PENDING", "ACTIVE", "FAILED")
               AND prochaine_execution IS NOT NULL
               AND prochaine_execution <= :now',
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
        );

        $success = [];
        $failed = [];

        foreach ($rows as $row) {
            try {
                $this->executionService->executeSingleScheduledTransfer($row, 'n8n');
                $success[] = $row['id'];
            } catch (\Throwable $e) {
                $this->executionService->handleExecutionFailure($row, $e, 'n8n');
                $failed[] = ['id' => $row['id'], 'error' => $e->getMessage()];
            }
        }

        return $this->json([
            'ok' => true,
            'processed' => count($rows),
            'success_ids' => $success,
            'failed' => $failed,
        ]);
    }

    private function assertToken(Request $request): ?JsonResponse
    {
        $provided = (string) ($request->headers->get('X-Scheduled-Transfer-Token') ?? $request->query->get('token', ''));

        if (trim($this->internalToken) === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Internal token is not configured.',
            ], 500);
        }

        if (!hash_equals($this->internalToken, $provided)) {
            return $this->json([
                'ok' => false,
                'message' => 'Unauthorized token.',
            ], 401);
        }

        return null;
    }
}
