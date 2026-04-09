<?php

namespace App\Controller\FrontOffice;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use App\Repository\ProduitRepository;
use App\Repository\FactureRepository;

class ServiceController extends AbstractController
{
    // ========== READ (LISTE) ==========
    #[Route('/service', name: 'front_service_index', methods: ['GET'])]
    public function index(
        Request $request,
        ServiceRepository $serviceRepository,
        ProduitRepository $produitRepository,
        FactureRepository $factureRepository
    ): Response {
        $tab = $request->query->get('tab', 'services');
        
        // Services Filters
        $searchS = $request->query->get('searchS');
        $typeS = $request->query->get('typeS');
        $statusS = $request->query->get('statusS');
        
        // Produits Filters
        $searchP = $request->query->get('searchP');
        $typeP = $request->query->get('typeP');
        $statusP = $request->query->get('statusP');
        
        // Factures Filters
        $searchF = $request->query->get('searchF');
        $statusF = $request->query->get('statusF');

        try {
            // Fetch Services
            $qbS = $serviceRepository->createQueryBuilder('s')
                ->where('s.isDeleted = :deleted')
                ->setParameter('deleted', false);
            if ($searchS) $qbS->andWhere('s.nomService LIKE :search')->setParameter('search', "%$searchS%");
            if ($typeS) $qbS->andWhere('s.typeService = :type')->setParameter('type', $typeS);
            if ($statusS) $qbS->andWhere('s.statut = :status')->setParameter('status', $statusS);
            $services = $qbS->orderBy('s.id', 'DESC')->getQuery()->getResult();

            // Fetch Produits
            $qbP = $produitRepository->createQueryBuilder('p')
                ->where('p.isDeleted = :deleted')
                ->setParameter('deleted', false);
            if ($searchP) $qbP->andWhere('p.nomProduit LIKE :search')->setParameter('search', "%$searchP%");
            if ($typeP) $qbP->andWhere('p.typeProduit = :type')->setParameter('type', $typeP);
            if ($statusP) $qbP->andWhere('p.statut = :status')->setParameter('status', $statusP);
            $produits = $qbP->orderBy('p.id', 'DESC')->getQuery()->getResult();

            // Fetch Factures
            $qbF = $factureRepository->createQueryBuilder('f')
                ->where('f.isDeleted = :deleted')
                ->setParameter('deleted', false);
            if ($searchF) $qbF->andWhere('f.numeroFacture LIKE :search OR f.montant LIKE :search')->setParameter('search', "%$searchF%");
            if ($statusF) $qbF->andWhere('f.statut = :status')->setParameter('status', $statusF);
            $factures = $qbF->orderBy('f.id', 'DESC')->getQuery()->getResult();

            return $this->render('frontoffice/service/index.html.twig', [
                'services' => $services,
                'produits' => $produits,
                'factures' => $factures,
                'activeTab' => $tab,
                'currentSearchS' => $searchS,
                'currentTypeS' => $typeS,
                'currentStatusS' => $statusS,
                'currentSearchP' => $searchP,
                'currentTypeP' => $typeP,
                'currentStatusP' => $statusP,
                'currentSearchF' => $searchF,
                'currentStatusF' => $statusF,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Impossible de charger les données : ' . $e->getMessage());
            return $this->render('frontoffice/service/index.html.twig', [
                'services' => [], 'produits' => [], 'factures' => [],
                'activeTab' => 'services',
                'currentSearchS' => '', 'currentTypeS' => '', 'currentStatusS' => '',
                'currentSearchP' => '', 'currentTypeP' => '', 'currentStatusP' => '',
                'currentSearchF' => '', 'currentStatusF' => '',
            ]);
        }
    }

    // ========== CREATE ==========
    #[Route('/service/new', name: 'front_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->persist($service);
                    $entityManager->flush();

                    $this->addFlash('success', '✅ Service ajouté avec succès !');
                    return $this->redirectToRoute('front_service_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('frontoffice/service/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    // ========== READ (DETAIL) ==========
    #[Route('/service/{id}', name: 'front_service_show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        try {
            if ($service->isDeleted()) {
                throw new \Exception("Service archivé");
            }
            return $this->render('frontoffice/service/show.html.twig', [
                'service' => $service,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : Service non trouvé');
            return $this->redirectToRoute('front_service_index');
        }
    }

    // ========== UPDATE ==========
    #[Route('/service/{id}/edit', name: 'front_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->flush();
                    $this->addFlash('success', '✏️ Service modifié avec succès !');
                    return $this->redirectToRoute('front_service_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('frontoffice/service/edit.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    // ========== DELETE (SOFT) ==========
    #[Route('/service/{id}/delete', name: 'front_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $service->getId(), $request->request->get('_token'))) {
            try {
                $service->setIsDeleted(true);
                $entityManager->flush();
                $this->addFlash('success', '🗑️ Service archivé avec succès !');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            }
        }
        
        return $this->redirectToRoute('front_service_index');
    }
}
