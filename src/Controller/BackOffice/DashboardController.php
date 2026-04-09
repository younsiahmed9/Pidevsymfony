<?php

namespace App\Controller\BackOffice;

use App\Repository\FactureRepository;
use App\Repository\ProduitRepository;
use App\Repository\ServiceRepository;
use App\Service\FactureService;
use App\Service\ProduitService;
use App\Service\ServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        FactureService $factureService,
        ProduitService $produitService,
        ServiceService $serviceService,
        FactureRepository $factureRepository,
        ProduitRepository $produitRepository,
        ServiceRepository $serviceRepository
    ): Response
    {
        // Données des factures
        $totalCA = $factureService->getTotalMontantFactures();
        $facturesImpayees = $factureService->getFacturesImpayees();
        $countFacturesImpayees = count($facturesImpayees);
        $montantImpaye = $factureService->getTotalMontantImpaye();
        $facturesExpires = $factureService->getFacturesExpires();
        $countFacturesExpires = count($facturesExpires);
        $montantExpire = $factureService->getTotalMontantExpire();
        $tauxRecouvrement = $factureService->getTauxRecouvrement();
        $tauxImpaye = $factureService->getTauxImpaye();

        // Données des produits
        $produitVendus = $produitService->countProduitVendus();
        $produitDisponible = $produitService->countProduitDisponible();
        $produitExpire = $produitService->countProduitExpire();
        $totalMontantProduit = $produitService->getTotalMontantDisponible();

        // Données des services
        $servicesActifs = $serviceService->countServicesActifs();
        $servicesSuspendus = $serviceService->countServicesSuspendus();
        $servicesExpires = $serviceService->countServicesExpires();
        $totalTarifActif = $serviceService->getTotalTarifActif();
        $averageTarif = $serviceService->getAverageTarif();

        // Récupérer les derniers éléments pour l'affichage
        $recentFactures = $factureRepository->findBy(['isDeleted' => false], ['id' => 'DESC'], 5);
        $recentProduits = $produitRepository->findBy(['isDeleted' => false], ['id' => 'DESC'], 5);
        $recentServices = $serviceRepository->findBy(['isDeleted' => false], ['id' => 'DESC'], 5);

        return $this->render('backoffice/dashboard/index.html.twig', [
            // Factures
            'totalCA' => $totalCA,
            'countFacturesImpayees' => $countFacturesImpayees,
            'montantImpaye' => $montantImpaye,
            'countFacturesExpires' => $countFacturesExpires,
            'montantExpire' => $montantExpire,
            'tauxRecouvrement' => $tauxRecouvrement,
            'tauxImpaye' => $tauxImpaye,
            'recentFactures' => $recentFactures,

            // Produits
            'produitVendus' => $produitVendus,
            'produitDisponible' => $produitDisponible,
            'produitExpire' => $produitExpire,
            'totalMontantProduit' => $totalMontantProduit,
            'recentProduits' => $recentProduits,

            // Services
            'servicesActifs' => $servicesActifs,
            'servicesSuspendus' => $servicesSuspendus,
            'servicesExpires' => $servicesExpires,
            'totalTarifActif' => $totalTarifActif,
            'averageTarif' => $averageTarif,
            'recentServices' => $recentServices,
        ]);
    }
}
