<?php

namespace App\Service\Recommendation;

use App\Entity\User;
use App\Entity\Service;
use App\Entity\UserServiceInteraction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Enhanced Hybrid Recommendation System with Similarity-based Recommendations
 * Combines Content-Based, Collaborative Filtering, and Similarity-based recommendations
 */
final class EnhancedHybridRecommendationService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private SimilarityRecommendationService $similarityService;
    private array $cache = [];
    private const CACHE_TTL = 3600; // 1 hour

    // Weight factors for enhanced hybrid scoring
    private const CONTENT_WEIGHT = 0.3;
    private const COLLABORATIVE_WEIGHT = 0.4;
    private const SIMILARITY_WEIGHT = 0.3;
    private const POPULARITY_BOOST = 0.1;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        SimilarityRecommendationService $similarityService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->similarityService = $similarityService;
    }

    /**
     * Get top 5 enhanced recommendations for a user
     */
    public function getRecommendations(User $user, int $limit = 5): array
    {
        $cacheKey = "enhanced_recommendations_{$user->getId()}_{$limit}";
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $this->logger->info('Generating enhanced recommendations', [
            'user_id' => $user->getId(),
            'limit' => $limit
        ]);

        // Get user's interaction history
        $userInteractions = $this->getUserInteractions($user);
        $userCategories = $this->getUserPreferredCategories($userInteractions);
        
        // Get content-based recommendations
        $contentBasedScores = $this->getContentBasedScores($user, $userCategories);
        
        // Get collaborative filtering recommendations
        $collaborativeScores = $this->getCollaborativeFilteringScores($user, $userInteractions);
        
        // Get similarity-based recommendations (NEW!)
        $similarityScores = $this->getSimilarityBasedScores($user);
        
        // Combine scores using enhanced hybrid approach
        $finalScores = $this->combineEnhancedScores($contentBasedScores, $collaborativeScores, $similarityScores);
        
        // Apply popularity boost and sort
        $recommendations = $this->applyPopularityBoost($finalScores);
        
        // Get top N recommendations
        $topRecommendations = array_slice($recommendations, 0, $limit, true);
        
        // Cache results
        $this->cache[$cacheKey] = $topRecommendations;
        
        $this->logger->info('Enhanced recommendations generated', [
            'user_id' => $user->getId(),
            'count' => count($topRecommendations)
        ]);

        return $topRecommendations;
    }

    /**
     * Get similarity-based recommendations
     */
    private function getSimilarityBasedScores(User $user): array
    {
        $scores = [];
        
        try {
            $similarityRecommendations = $this->similarityService->getSimilarityRecommendations($user, 10);
            
            foreach ($similarityRecommendations as $recommendation) {
                $service = $recommendation['service'];
                
                // Skip services the user already has
                if ($this->userHasService($user, $service)) {
                    continue;
                }
                
                $scores[$service->getId()] = [
                    'service' => $service,
                    'score' => $recommendation['score'],
                    'type' => 'similarity-based',
                    'reason' => $recommendation['reason'],
                    'similarity_to' => $recommendation['similarity_to']
                ];
            }
            
            $this->logger->info('Similarity-based scores calculated', [
                'user_id' => $user->getId(),
                'count' => count($scores)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error calculating similarity scores', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
        
        return $scores;
    }

    /**
     * Combine enhanced scores from all three approaches
     */
    private function combineEnhancedScores(array $contentScores, array $collaborativeScores, array $similarityScores): array
    {
        $combined = [];
        
        // All service IDs from all approaches
        $allServiceIds = array_unique(array_merge(
            array_keys($contentScores),
            array_keys($collaborativeScores),
            array_keys($similarityScores)
        ));
        
        foreach ($allServiceIds as $serviceId) {
            $contentScore = $contentScores[$serviceId]['score'] ?? 0.0;
            $collaborativeScore = $collaborativeScores[$serviceId]['score'] ?? 0.0;
            $similarityScore = $similarityScores[$serviceId]['score'] ?? 0.0;
            
            // Enhanced weighted combination
            $finalScore = (
                $contentScore * self::CONTENT_WEIGHT +
                $collaborativeScore * self::COLLABORATIVE_WEIGHT +
                $similarityScore * self::SIMILARITY_WEIGHT
            );
            
            // Prioritize similarity-based recommendations
            $type = 'hybrid';
            $reason = '';
            $similarityTo = null;
            
            if (isset($similarityScores[$serviceId])) {
                $type = 'similarity-enhanced';
                $reason = $similarityScores[$serviceId]['reason'];
                $similarityTo = $similarityScores[$serviceId]['similarity_to'];
                // Boost similarity-based recommendations
                $finalScore *= 1.2;
            }
            
            $service = $contentScores[$serviceId]['service'] ?? 
                      $collaborativeScores[$serviceId]['service'] ??
                      $similarityScores[$serviceId]['service'];
            
            $combined[$serviceId] = [
                'service' => $service,
                'score' => min(1.0, $finalScore), // Cap at 1.0
                'content_score' => $contentScore,
                'collaborative_score' => $collaborativeScore,
                'similarity_score' => $similarityScore,
                'type' => $type,
                'reason' => $reason,
                'similarity_to' => $similarityTo
            ];
        }
        
        return $combined;
    }

    /**
     * Content-Based Filtering: Score services based on category similarity
     */
    private function getContentBasedScores(User $user, array $preferredCategories): array
    {
        $scores = [];
        
        // Get all available services
        $services = $this->entityManager->getRepository(Service::class)
            ->findBy(['statut' => 'actif']);
        
        foreach ($services as $service) {
            // Skip services the user already has
            if ($this->userHasService($user, $service)) {
                continue;
            }
            
            $score = $this->calculateCategorySimilarity($service, $preferredCategories);
            $scores[$service->getId()] = [
                'service' => $service,
                'score' => $score,
                'type' => 'content-based'
            ];
        }
        
        return $scores;
    }

    /**
     * Collaborative Filtering: Find similar users and recommend their preferences
     */
    private function getCollaborativeFilteringScores(User $user, array $userInteractions): array
    {
        $scores = [];
        
        // Find similar users
        $similarUsers = $this->findSimilarUsers($user, $userInteractions);
        
        if (empty($similarUsers)) {
            return $scores;
        }
        
        // Get services interacted by similar users
        $similarUserServices = $this->getServicesFromSimilarUsers($similarUsers, $user);
        
        foreach ($similarUserServices as $serviceId => $data) {
            $score = $this->calculateCollaborativeScore($data['users'], $data['interactions']);
            $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
            
            if ($service && $service->getStatut() === 'actif' && !$this->userHasService($user, $service)) {
                $scores[$serviceId] = [
                    'service' => $service,
                    'score' => $score,
                    'type' => 'collaborative'
                ];
            }
        }
        
        return $scores;
    }

    /**
     * Apply popularity boost to final scores
     */
    private function applyPopularityBoost(array $scores): array
    {
        // Get service popularity metrics
        $popularityData = $this->getServicePopularity();
        
        foreach ($scores as $serviceId => &$data) {
            $popularity = $popularityData[$serviceId] ?? 0;
            $boost = $popularity * self::POPULARITY_BOOST;
            $data['score'] += $boost;
            $data['popularity_boost'] = $boost;
        }
        
        // Sort by score (descending)
        uasort($scores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $scores;
    }

    /**
     * Calculate category similarity score
     */
    private function calculateCategorySimilarity(Service $service, array $preferredCategories): float
    {
        $serviceCategory = $this->getServiceCategory($service);
        
        if (!$serviceCategory || empty($preferredCategories)) {
            return 0.1; // Base score for unknown categories
        }
        
        $maxScore = 0.0;
        foreach ($preferredCategories as $category => $preference) {
            if ($category === $serviceCategory) {
                $maxScore = max($maxScore, $preference);
            }
        }
        
        return $maxScore > 0 ? $maxScore : 0.1;
    }

    /**
     * Find similar users based on interaction patterns
     */
    private function findSimilarUsers(User $user, array $userInteractions): array
    {
        $similarUsers = [];
        $userInteractionVector = $this->createInteractionVector($userInteractions);
        
        // Get all users with interactions
        $allUsers = $this->getAllUsersWithInteractions();
        
        foreach ($allUsers as $otherUser) {
            if ($otherUser->getId() === $user->getId()) {
                continue;
            }
            
            $otherInteractions = $this->getUserInteractions($otherUser);
            $otherVector = $this->createInteractionVector($otherInteractions);
            
            $similarity = $this->calculateCosineSimilarity($userInteractionVector, $otherVector);
            
            if ($similarity > 0.3) { // Threshold for similarity
                $similarUsers[$otherUser->getId()] = [
                    'user' => $otherUser,
                    'similarity' => $similarity
                ];
            }
        }
        
        // Sort by similarity (descending)
        uasort($similarUsers, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($similarUsers, 0, 50, true); // Top 50 similar users
    }

    /**
     * Calculate cosine similarity between two interaction vectors
     */
    private function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;
        
        $allKeys = array_unique(array_merge(array_keys($vectorA), array_keys($vectorB)));
        
        foreach ($allKeys as $key) {
            $valueA = $vectorA[$key] ?? 0;
            $valueB = $vectorB[$key] ?? 0;
            
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }
        
        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }
        
        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }

    /**
     * Create interaction vector for similarity calculation
     */
    private function createInteractionVector(array $interactions): array
    {
        $vector = [];
        
        foreach ($interactions as $interaction) {
            $serviceId = $interaction->getService()->getId();
            $weight = $this->getInteractionWeight($interaction);
            
            if (!isset($vector[$serviceId])) {
                $vector[$serviceId] = 0;
            }
            
            $vector[$serviceId] += $weight;
        }
        
        return $vector;
    }

    /**
     * Get weight for different interaction types
     */
    private function getInteractionWeight(UserServiceInteraction $interaction): float
    {
        $weights = [
            'view' => 1.0,
            'favorite' => 2.0,
            'purchase' => 3.0,
            'review' => 2.5
        ];
        
        $baseWeight = $weights[$interaction->getInteractionType()] ?? 1.0;
        $ratingMultiplier = ($interaction->getRating() / 5.0); // 0.0 to 1.0
        $frequencyMultiplier = min(2.0, 1.0 + ($interaction->getFrequency() - 1) * 0.1);
        
        return $baseWeight * $ratingMultiplier * $frequencyMultiplier;
    }

    /**
     * Get services from similar users
     */
    private function getServicesFromSimilarUsers(array $similarUsers, User $currentUser): array
    {
        $services = [];
        
        foreach ($similarUsers as $userData) {
            $similarUser = $userData['user'];
            $similarity = $userData['similarity'];
            
            $interactions = $this->getUserInteractions($similarUser);
            
            foreach ($interactions as $interaction) {
                $serviceId = $interaction->getService()->getId();
                
                // Skip services current user already has
                if ($this->userHasService($currentUser, $interaction->getService())) {
                    continue;
                }
                
                if (!isset($services[$serviceId])) {
                    $services[$serviceId] = [
                        'users' => [],
                        'interactions' => []
                    ];
                }
                
                $services[$serviceId]['users'][] = $similarUser;
                $services[$serviceId]['interactions'][] = $interaction;
            }
        }
        
        return $services;
    }

    /**
     * Calculate collaborative score based on similar user interactions
     */
    private function calculateCollaborativeScore(array $users, array $interactions): float
    {
        $totalScore = 0.0;
        $totalWeight = 0.0;
        
        foreach ($interactions as $interaction) {
            $weight = $this->getInteractionWeight($interaction);
            $rating = $interaction->getRating() / 5.0; // Normalize to 0-1
            
            $totalScore += $weight * $rating;
            $totalWeight += $weight;
        }
        
        if ($totalWeight == 0) {
            return 0.0;
        }
        
        return $totalScore / $totalWeight;
    }

    /**
     * Get user's interaction history
     */
    private function getUserInteractions(User $user): array
    {
        return $this->entityManager->getRepository(UserServiceInteraction::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Get user's preferred categories
     */
    private function getUserPreferredCategories(array $interactions): array
    {
        $categories = [];
        
        foreach ($interactions as $interaction) {
            $category = $this->getServiceCategory($interaction->getService());
            $weight = $this->getInteractionWeight($interaction);
            
            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            
            $categories[$category] += $weight;
        }
        
        // Normalize to 0-1 range
        $maxScore = !empty($categories) ? max($categories) : 0;
        if ($maxScore > 0) {
            foreach ($categories as $category => &$score) {
                $score = $score / $maxScore;
            }
        }
        
        return $categories;
    }

    /**
     * Get service category (simplified - using service type as category)
     */
    private function getServiceCategory(Service $service): string
    {
        return $service->getTypeService() ?? 'unknown';
    }

    /**
     * Check if user has service
     */
    private function userHasService(User $user, Service $service): bool
    {
        $existingService = $this->entityManager->getRepository(Service::class)
            ->findOneBy([
                'user' => $user,
                'nomService' => $service->getNomService()
            ]);
        
        return $existingService !== null;
    }

    /**
     * Get all users with interactions
     */
    private function getAllUsersWithInteractions(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT u')
           ->from(User::class, 'u')
           ->join(UserServiceInteraction::class, 'i', 'WITH', 'i.user = u.id');
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Get service popularity metrics
     */
    private function getServicePopularity(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s.id as service_id, COUNT(i.id) as interaction_count, AVG(i.rating) as avg_rating')
           ->from(Service::class, 's')
           ->leftJoin(UserServiceInteraction::class, 'i', 'WITH', 'i.service = s.id')
           ->where('s.statut = :status')
           ->setParameter('status', 'actif')
           ->groupBy('s.id');
        
        $results = $qb->getQuery()->getResult();
        
        $popularity = [];
        $maxInteractions = 1;
        
        // Find max for normalization
        foreach ($results as $result) {
            $maxInteractions = max($maxInteractions, $result['interaction_count']);
        }
        
        // Normalize popularity scores
        foreach ($results as $result) {
            $interactionScore = $result['interaction_count'] / $maxInteractions;
            $ratingScore = ($result['avg_rating'] ?? 0) / 5.0;
            
            $popularity[$result['service_id']] = ($interactionScore + $ratingScore) / 2;
        }
        
        return $popularity;
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->logger->info('Enhanced recommendation cache cleared');
    }
}
