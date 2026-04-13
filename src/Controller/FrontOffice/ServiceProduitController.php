<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceProduitController extends AbstractController
{
    #[Route('/services-produits', name: 'service_produit_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT s.id_service AS id,
                    s.nom_service AS nomService,
                    s.tarif,
                    s.type_service AS typeService,
                    s.frequence,
                    s.date_debut AS dateDebut,
                    s.date_fin AS dateFin,
                    s.statut,
                    COUNT(f.id_facture) AS facturesCount
             FROM service s
             LEFT JOIN facture f ON f.id_service = s.id_service AND f.user_id = :user_id
             WHERE s.user_id = :user_id
             GROUP BY s.id_service, s.nom_service, s.tarif, s.type_service, s.frequence, s.date_debut, s.date_fin, s.statut
             ORDER BY s.id_service DESC
             LIMIT 5',
            ['user_id' => $user->getId()]
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT p.id_produit AS id,
                    p.nom_produit AS nomProduit,
                    p.montant,
                    p.code_unique AS codeUnique,
                    p.type_produit AS typeProduit,
                    p.statut,
                    p.date_creation AS dateCreation,
                    COUNT(f.id_facture) AS facturesCount
             FROM produit p
             LEFT JOIN facture f ON f.id_produit = p.id_produit AND f.user_id = :user_id
             WHERE p.user_id = :user_id
             GROUP BY p.id_produit, p.nom_produit, p.montant, p.code_unique, p.type_produit, p.statut, p.date_creation
             ORDER BY p.id_produit DESC
             LIMIT 5',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/service_produit/index.html.twig', [
            'services' => $services,
            'produits' => $produits,
        ]);
    }
}
