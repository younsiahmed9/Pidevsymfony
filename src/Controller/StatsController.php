<?php

namespace App\Controller;

use App\Service\FactureService;
use App\Service\ProduitService;
use App\Service\ServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    #[Route('/stats/dashboard', name: 'stats_dashboard', methods: ['GET'])]
    public function dashboard(
        FactureService $factureService,
        ProduitService $produitService,
        ServiceService $serviceService,
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

        return $this->render('stats/dashboard.html.twig', [
            // Factures
            'totalCA' => $totalCA,
            'countFacturesImpayees' => $countFacturesImpayees,
            'montantImpaye' => $montantImpaye,
            'countFacturesExpires' => $countFacturesExpires,
            'montantExpire' => $montantExpire,
            'tauxRecouvrement' => $tauxRecouvrement,
            'tauxImpaye' => $tauxImpaye,

            // Produits
            'produitVendus' => $produitVendus,
            'produitDisponible' => $produitDisponible,
            'produitExpire' => $produitExpire,
            'totalMontantProduit' => $totalMontantProduit,

            // Services
            'servicesActifs' => $servicesActifs,
            'servicesSuspendus' => $servicesSuspendus,
            'servicesExpires' => $servicesExpires,
            'totalTarifActif' => $totalTarifActif,
            'averageTarif' => $averageTarif,
        ]);
    }
}
