<?php

namespace App\DataFixtures\ORM;

use App\Entity\User;
use App\Entity\Service;
use App\Entity\UserServiceInteraction;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RecommendationFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create sample users
        $users = $this->createSampleUsers($manager);
        
        // Create sample services
        $services = $this->createSampleServices($manager);
        
        // Create sample interactions
        $this->createSampleInteractions($manager, $users, $services);
        
        $manager->flush();
    }

    private function createSampleUsers(ObjectManager $manager): array
    {
        $users = [];
        $userData = [
            ['email' => 'user1@example.com', 'name' => 'Alice'],
            ['email' => 'user2@example.com', 'name' => 'Bob'],
            ['email' => 'user3@example.com', 'name' => 'Charlie'],
            ['email' => 'user4@example.com', 'name' => 'Diana'],
            ['email' => 'user5@example.com', 'name' => 'Eve']
        ];

        foreach ($userData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFullName($data['name']);
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, 'password123')
            );
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTime());
            
            $manager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    private function createSampleServices(ObjectManager $manager): array
    {
        $services = [];
        $serviceData = [
            ['name' => 'Netflix Premium', 'type' => 'abonnement', 'tarif' => '15.99'],
            ['name' => 'Spotify Family', 'type' => 'abonnement', 'tarif' => '14.99'],
            ['name' => 'Adobe Creative Cloud', 'type' => 'abonnement', 'tarif' => '29.99'],
            ['name' => 'Microsoft 365', 'type' => 'abonnement', 'tarif' => '9.99'],
            ['name' => 'Amazon Prime', 'type' => 'abonnement', 'tarif' => '12.99'],
            ['name' => 'Disney+', 'type' => 'abonnement', 'tarif' => '7.99'],
            ['name' => 'YouTube Premium', 'type' => 'abonnement', 'tarif' => '11.99'],
            ['name' => 'Apple Music', 'type' => 'abonnement', 'tarif' => '9.99'],
            ['name' => 'Cloud Storage Pro', 'type' => 'abonnement', 'tarif' => '19.99'],
            ['name' => 'VPN Service', 'type' => 'abonnement', 'tarif' => '6.99']
        ];

        foreach ($serviceData as $i => $data) {
            $service = new Service();
            $service->setUser($users[0]); // Set first user as owner
            $service->setNomService($data['name']);
            $service->setTypeService($data['type']);
            $service->setTarif($data['tarif']);
            $service->setStatut('actif');
            $service->setDateDebut(new \DateTime());
            $service->setFrequence('monthly');
            
            $manager->persist($service);
            $services[] = $service;
        }

        return $services;
    }

    private function createSampleInteractions(ObjectManager $manager, array $users, array $services): void
    {
        $interactionTypes = ['view', 'favorite', 'purchase', 'review'];
        
        foreach ($users as $userIndex => $user) {
            // Each user interacts with different services
            $userServices = array_slice($services, $userIndex, 6);
            
            foreach ($userServices as $serviceIndex => $service) {
                // Create multiple interactions per service
                $interactionCount = rand(1, 3);
                
                for ($i = 0; $i < $interactionCount; $i++) {
                    $interaction = new UserServiceInteraction(
                        $user,
                        $service,
                        $interactionTypes[array_rand($interactionTypes)]
                    );
                    
                    $interaction->setRating(rand(3, 5));
                    $interaction->setFrequency($interactionCount);
                    
                    // Vary the creation time
                    $daysAgo = rand(1, 30);
                    $interaction->setCreatedAt(
                        (new \DateTime())->modify("-{$daysAgo} days")
                    );
                    
                    $manager->persist($interaction);
                }
            }
        }
    }
}
