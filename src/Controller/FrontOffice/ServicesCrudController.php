<?php
namespace App\Controller\FrontOffice;

use App\Entity\Produit;
use App\Entity\Service;
use App\Entity\Facture;
use App\Repository\ProduitRepository;
use App\Repository\ServiceRepository;
use App\Repository\FactureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ServicesCrudController extends AbstractController
{
    #[Route('/services-crud', name: 'app_services_crud')]
    public function index(
        ProduitRepository $produitRepo,
        ServiceRepository $serviceRepo,
        FactureRepository $factureRepo
    ): Response
    {
        $produits = $produitRepo->findBy(['isDeleted' => false]);
        $services = $serviceRepo->findBy(['isDeleted' => false]);
        $factures = $factureRepo->findBy(['isDeleted' => false]);
        
        return $this->render('frontoffice/services_crud/index.html.twig', [
            'produits' => $produits,
            'services' => $services,
            'factures' => $factures
        ]);
    }
}