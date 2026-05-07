<?php

namespace App\Service\Recommendation;

use App\Entity\User;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de recommandation basé sur la similarité des plateformes
 * Suggère des alternatives similaires (ex: Netflix -> Shahed, Spotify -> Anghami)
 */
final class SimilarityRecommendationService
{
    // Base de données des similarités entre plateformes
    private const SIMILARITY_MAP = [
        // Streaming vidéo international -> tunisien/arabe
        'netflix' => [
            'shahed' => 0.9,
            'starzplay' => 0.8,
            'osn' => 0.7,
            'icflix' => 0.6
        ],
        'disney+' => [
            'shahed' => 0.8,
            'starzplay' => 0.7,
            'osn' => 0.6
        ],
        'amazon prime' => [
            'shahed' => 0.7,
            'starzplay' => 0.6,
            'osn' => 0.5
        ],
        'hbo max' => [
            'osn' => 0.8,
            'starzplay' => 0.7,
            'shahed' => 0.6
        ],
        
        // Streaming musique international -> arabe
        'spotify' => [
            'anghami' => 0.9,
            'apple music' => 0.7,
            'youtube music' => 0.6,
            'deezer' => 0.5
        ],
        'apple music' => [
            'anghami' => 0.8,
            'youtube music' => 0.7,
            'deezer' => 0.6
        ],
        
        // Productivité cloud
        'microsoft 365' => [
            'google workspace' => 0.8,
            'zoho' => 0.6,
            'onlyoffice' => 0.5
        ],
        'google workspace' => [
            'microsoft 365' => 0.8,
            'zoho' => 0.6,
            'onlyoffice' => 0.5
        ],
        
        // Stockage cloud
        'dropbox' => [
            'google drive' => 0.8,
            'onedrive' => 0.7,
            'mega' => 0.6,
            'pcloud' => 0.5
        ],
        'google drive' => [
            'onedrive' => 0.8,
            'dropbox' => 0.7,
            'mega' => 0.6
        ],
        
        // VPN
        'nordvpn' => [
            'expressvpn' => 0.8,
            'surfshark' => 0.7,
            'cyberghost' => 0.6,
            'private internet access' => 0.5
        ],
        'expressvpn' => [
            'nordvpn' => 0.8,
            'surfshark' => 0.7,
            'cyberghost' => 0.6
        ],
        
        // Design et créativité
        'adobe creative cloud' => [
            'canva pro' => 0.7,
            'figma' => 0.6,
            'affinity' => 0.5,
            'grafx' => 0.4
        ],
        'canva pro' => [
            'adobe creative cloud' => 0.7,
            'figma' => 0.6,
            'grafx' => 0.5
        ]
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Obtenir des recommandations basées sur la similarité des services existants
     */
    public function getSimilarityRecommendations(User $user, int $limit = 5): array
    {
        try {
            // Obtenir les services actuels de l'utilisateur
            $userServices = $this->getUserServices($user);
            
            if (empty($userServices)) {
                return $this->getPopularAlternatives($limit);
            }

            $recommendations = [];
            $processedServices = [];

            foreach ($userServices as $userService) {
                $serviceName = strtolower($userService->getNomService());
                
                // Trouver des alternatives similaires
                $similarServices = $this->findSimilarServices($serviceName, $user, $processedServices);
                
                foreach ($similarServices as $similarService) {
                    $recommendations[] = $similarService;
                    $processedServices[] = $similarService['service']->getId();
                    
                    if (count($recommendations) >= $limit) {
                        break 2;
                    }
                }
            }

            // Compléter avec des alternatives populaires si nécessaire
            if (count($recommendations) < $limit) {
                $additional = $this->getPopularAlternatives($limit - count($recommendations), $processedServices);
                $recommendations = array_merge($recommendations, $additional);
            }

            return array_slice($recommendations, 0, $limit);

        } catch (\Exception $e) {
            $this->logger->error('Error getting similarity recommendations', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Trouver des services similaires basés sur le nom du service
     */
    private function findSimilarServices(string $serviceName, User $user, array $excludeIds = []): array
    {
        $similarServices = [];
        
        // Normaliser le nom du service pour la recherche
        $normalizedServiceName = $this->normalizeServiceName($serviceName);
        
        // Chercher dans la carte de similarité
        foreach (self::SIMILARITY_MAP as $key => $alternatives) {
            if ($this->isServiceMatch($normalizedServiceName, $key)) {
                foreach ($alternatives as $alternativeName => $similarity) {
                    $service = $this->findServiceByName($alternativeName, $excludeIds);
                    
                    if ($service && !$this->userHasService($user, $service)) {
                        $similarServices[] = [
                            'service' => $service,
                            'score' => $similarity,
                            'type' => 'similarity-based',
                            'reason' => "Alternative similaire à {$serviceName}",
                            'similarity_to' => $serviceName
                        ];
                    }
                }
                break;
            }
        }
        
        return $similarServices;
    }

    /**
     * Normaliser le nom du service pour la recherche
     */
    private function normalizeServiceName(string $serviceName): string
    {
        return strtolower(trim($serviceName));
    }

    /**
     * Vérifier si le service correspond à la clé de recherche
     */
    private function isServiceMatch(string $serviceName, string $key): bool
    {
        // Correspondance exacte
        if ($serviceName === $key) {
            return true;
        }
        
        // Correspondance partielle (contient le mot clé)
        if (str_contains($serviceName, $key) || str_contains($key, $serviceName)) {
            return true;
        }
        
        // Correspondance avec variations communes
        $variations = [
            'netflix' => ['netflix'],
            'disney+' => ['disney', 'disney plus', 'disney+'],
            'amazon prime' => ['amazon', 'prime video'],
            'spotify' => ['spotify'],
            'apple music' => ['apple', 'itunes'],
            'microsoft 365' => ['microsoft', 'office 365', 'office'],
            'google workspace' => ['google', 'g suite'],
            'dropbox' => ['dropbox'],
            'google drive' => ['google drive', 'drive'],
            'nordvpn' => ['nordvpn'],
            'expressvpn' => ['expressvpn'],
            'adobe creative cloud' => ['adobe', 'creative cloud', 'adobe cc'],
            'canva pro' => ['canva']
        ];
        
        foreach ($variations as $serviceKey => $keywords) {
            if ($key === $serviceKey) {
                foreach ($keywords as $keyword) {
                    if (str_contains($serviceName, $keyword)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Trouver un service par son nom
     */
    private function findServiceByName(string $serviceName, array $excludeIds = []): ?Service
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('s')
           ->from(Service::class, 's')
           ->where('s.nomService LIKE :name')
           ->andWhere('s.statut = :status')
           ->setParameter('name', '%' . $serviceName . '%')
           ->setParameter('status', 'actif')
           ->setMaxResults(1);
        
        if (!empty($excludeIds)) {
            $qb->andWhere('s.id NOT IN (:excludeIds)')
               ->setParameter('excludeIds', $excludeIds);
        }
        
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Vérifier si l'utilisateur a déjà ce service
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
     * Obtenir les services de l'utilisateur
     */
    private function getUserServices(User $user): array
    {
        return $this->entityManager->getRepository(Service::class)
            ->findBy(['user' => $user, 'statut' => 'actif']);
    }

    /**
     * Obtenir des alternatives populaires
     */
    private function getPopularAlternatives(int $limit = 5, array $excludeIds = []): array
    {
        $alternatives = [
            'shahed' => ['Platforme streaming arabe', 0.8],
            'anghami' => ['Musique streaming arabe', 0.7],
            'starzplay' => ['Streaming MENA', 0.6],
            'osn' => ['Streaming moyen-orient', 0.5],
            'canva pro' => ['Design tunisien', 0.6]
        ];
        
        $recommendations = [];
        
        foreach ($alternatives as $serviceName => $data) {
            if (count($recommendations) >= $limit) {
                break;
            }
            
            $service = $this->findServiceByName($serviceName, $excludeIds);
            
            if ($service) {
                $recommendations[] = [
                    'service' => $service,
                    'score' => $data[1],
                    'type' => 'popular-alternative',
                    'reason' => $data[0],
                    'similarity_to' => null
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Obtenir des statistiques sur les similarités
     */
    public function getSimilarityStats(): array
    {
        return [
            'total_similarities' => count(self::SIMILARITY_MAP),
            'categories' => [
                'streaming_video' => 4,
                'streaming_music' => 2,
                'productivity' => 2,
                'storage' => 2,
                'vpn' => 2,
                'design' => 2
            ],
            'popular_alternatives' => ['shahed', 'anghami', 'starzplay', 'osn', 'canva pro']
        ];
    }
}
