<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\Chatbot\RecommendationChatbotService;
use App\Service\Recommendation\HybridRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/service/recommendations')]
#[IsGranted('ROLE_USER')]
final class ServiceRecommendationController extends AbstractController
{
    public function __construct(
        private HybridRecommendationService $recommendationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Page principale des recommandations de services
     */
    #[Route('/', name: 'service_recommendations_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $limit = min(10, max(1, (int) $request->query->get('limit', 5)));
        
        try {
            $recommendations = $this->recommendationService->getRecommendations($user, $limit);
            
            return $this->render('frontoffice/service/recommendations.html.twig', [
                'recommendations' => $recommendations,
                'user' => $user,
                'limit' => $limit,
                'categories' => ['abonnement', 'facture']
            ]);
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du chargement des recommandations');
            
            return $this->render('frontoffice/service/recommendations.html.twig', [
                'recommendations' => [],
                'user' => $user,
                'limit' => $limit,
                'categories' => ['abonnement', 'facture']
            ]);
        }
    }

    /**
     * API pour obtenir les recommandations (AJAX)
     */
    #[Route('/api', name: 'service_recommendations_api', methods: ['GET'])]
    public function getRecommendationsApi(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $limit = min(10, max(1, (int) $request->query->get('limit', 5)));
        $category = $request->query->get('category');
        
        try {
            if ($category) {
                // Récupérer les recommandations par catégorie
                $services = $this->getDoctrine()->getRepository(\App\Entity\Service::class)
                    ->findBy(['typeService' => $category, 'statut' => 'actif'], ['nomService' => 'ASC'], $limit);
                
                $recommendations = [];
                foreach ($services as $service) {
                    $recommendations[] = [
                        'service' => $service,
                        'score' => 0.8,
                        'type' => 'category-based'
                    ];
                }
            } else {
                $recommendations = $this->recommendationService->getRecommendations($user, $limit);
            }
            
            $formattedRecommendations = array_map([$this, 'formatRecommendation'], $recommendations);
            
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'recommendations' => $formattedRecommendations,
                    'count' => count($formattedRecommendations),
                    'category' => $category
                ]
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enregistrer une interaction avec un service
     */
    #[Route('/interact/{serviceId}', name: 'service_recommendations_interact', methods: ['POST'])]
    public function recordInteraction(int $serviceId, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $interactionType = $data['interaction_type'] ?? 'view';
        $rating = (float) ($data['rating'] ?? 0.0);
        
        if (!in_array($interactionType, ['view', 'favorite', 'purchase', 'review'])) {
            return new JsonResponse(['error' => 'Invalid interaction type'], 400);
        }
        
        try {
            $service = $this->getDoctrine()->getRepository(\App\Entity\Service::class)->find($serviceId);
            
            if (!$service) {
                return new JsonResponse(['error' => 'Service not found'], 404);
            }
            
            // Créer l'interaction
            $interaction = new \App\Entity\UserServiceInteraction($user, $service, $interactionType);
            $interaction->setRating($rating);
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($interaction);
            $entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Interaction enregistrée avec succès'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to record interaction',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chat assistant de recommandation
     */
    #[Route('/chat', name: 'service_recommendations_chat', methods: ['POST'])]
    public function chat(Request $request, RecommendationChatbotService $chatbotService): JsonResponse
    {
        $this->logger->info('Chat endpoint called');
        
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            $this->logger->info('Chat: User not authenticated');
            return new JsonResponse([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $this->logger->info('Chat: User authenticated', ['user_id' => $user->getId()]);

        $payload = json_decode($request->getContent(), true);
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            $this->logger->info('Chat: Empty message');
            return new JsonResponse([
                'success' => false,
                'message' => 'Le message est vide'
            ], 400);
        }

        $this->logger->info('Chat: Processing message', ['message' => $message]);

        try {
            $result = $chatbotService->processMessage($user, $message);
            
            $this->logger->info('Chat: Message processed successfully', ['result_type' => $result['type'] ?? null]);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'type' => $result['type'] ?? 'response',
                    'message' => $result['message'] ?? '',
                    'services' => $result['data'] ?? []
                ]
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Chat: Exception caught', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Le chatbot est temporairement indisponible'
            ], 500);
        }
    }

    /**
     * Formater les recommandations pour l'affichage
     */
    private function formatRecommendation(array $recommendation): array
    {
        $service = $recommendation['service'];
        
        return [
            'service' => [
                'id' => $service->getId(),
                'name' => $service->getNomService(),
                'type' => $service->getTypeService(),
                'tarif' => $service->getTarif(),
                'statut' => $service->getStatut(),
                'frequence' => $service->getFrequence(),
                'date_debut' => $service->getDateDebut()?->format('d/m/Y'),
                'date_fin' => $service->getDateFin()?->format('d/m/Y')
            ],
            'recommendation' => [
                'score' => round($recommendation['score'], 2),
                'type' => $recommendation['type'],
                'content_score' => round($recommendation['content_score'] ?? 0, 2),
                'collaborative_score' => round($recommendation['collaborative_score'] ?? 0, 2)
            ]
        ];
    }
}
