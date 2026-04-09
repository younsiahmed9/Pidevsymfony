<?php

namespace App\Controller\BackOffice;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use App\Service\ServiceService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[Route('/admin/service')]
class ServiceController extends AbstractController
{
    #[Route('/', name: 'admin_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository, ServiceService $serviceService): Response
    {
        try {
            $services = $serviceRepository->findBy(['isDeleted' => false]);
            $servicesActifs = $serviceService->getServicesActifs();
            $servicesSuspendus = $serviceService->getServicesByStatut('suspendu');
            $servicesExpires = $serviceService->getServicesByStatut('expire');

            return $this->render('backoffice/service/index.html.twig', [
                'services' => $services,
                'servicesActifs' => $servicesActifs,
                'servicesSuspendus' => $servicesSuspendus,
                'servicesExpires' => $servicesExpires,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            return $this->render('backoffice/service/index.html.twig', [
                'services' => [],
                'servicesActifs' => [],
                'servicesSuspendus' => [],
                'servicesExpires' => [],
            ]);
        }
    }

    #[Route('/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
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
                    return $this->redirectToRoute('admin_service_index');
                } catch (ValidationFailedException $e) {
                    $this->addFlash('error', '❌ Erreur de validation : ' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('backoffice/service/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_service_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, ServiceRepository $serviceRepository): Response
    {
        try {
            $service = $serviceRepository->find($id);
            
            if (!$service || $service->getIsDeleted()) {
                $this->addFlash('error', '❌ Erreur : Service non trouvé');
                return $this->redirectToRoute('admin_service_index');
            }

            return $this->render('backoffice/service/show.html.twig', [
                'service' => $service,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('admin_service_index');
        }
    }

    #[Route('/{id}/edit', name: 'admin_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $entityManager->flush();

                    $this->addFlash('success', '✏️ Service modifié avec succès !');
                    return $this->redirectToRoute('admin_service_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', '⚠️ Attention : Le formulaire contient des erreurs');
            }
        }

        return $this->render('backoffice/service/edit.html.twig', [
            'form' => $form,
            'service' => $service,
        ]);
    }

    #[Route('/{id}', name: 'admin_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $service->getId(), $request->request->get('_token'))) {
            try {
                // Soft delete
                $service->setIsDeleted(true);
                $entityManager->flush();

                $this->addFlash('success', '🗑️ Service archivé avec succès !');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', '❌ Erreur : Suppression impossible car ce service est utilisé ailleurs');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_service_index');
    }

    #[Route('/statistics/dashboard', name: 'admin_service_stats', methods: ['GET'])]
    public function statistics(ServiceRepository $serviceRepository, ServiceService $serviceService): Response
    {
        $allServices = $serviceRepository->findAll();
        $servicesActifs = $serviceService->getServicesActifs();
        $servicesSuspendus = $serviceService->getServicesByStatut('suspendu');
        $servicesExpires = $serviceService->getServicesByStatut('expire');

        // Statistiques avancées
        $countActifs = count($servicesActifs);
        $countSuspendus = count($servicesSuspendus);
        $countExpires = count($servicesExpires);
        $totalServices = count($allServices);

        $totalTarif = $serviceService->getTotalTarifActif();
        $averageTarif = $serviceService->getAverageTarif();

        // Statistiques par type
        $abonnements = $serviceService->getServicesByType('abonnement');
        $factures = $serviceService->getServicesByType('facture');

        // Statistiques par fréquence
        $mensuel = array_filter($allServices, fn($s) => $s->getFrequence() === 'mensuel');
        $annuel = array_filter($allServices, fn($s) => $s->getFrequence() === 'annuel');

        return $this->render('backoffice/service/statistics.html.twig', [
            // Comptages généraux
            'totalServices' => $totalServices,
            'countActifs' => $countActifs,
            'countSuspendus' => $countSuspendus,
            'countExpires' => $countExpires,
            
            // Tarifs
            'totalTarif' => $totalTarif,
            'averageTarif' => $averageTarif,

            // Par type
            'countAbonnements' => count($abonnements),
            'countFactures' => count($factures),

            // Par fréquence
            'countMensuel' => count($mensuel),
            'countAnnuel' => count($annuel),

            // Pourcentages
            'percentActifs' => $totalServices > 0 ? ($countActifs / $totalServices) * 100 : 0,
            'percentSuspendus' => $totalServices > 0 ? ($countSuspendus / $totalServices) * 100 : 0,
            'percentExpires' => $totalServices > 0 ? ($countExpires / $totalServices) * 100 : 0,

            // Données pour graphiques
            'services' => $allServices,
            'servicesActifs' => $servicesActifs,
        ]);
    }

    #[Route('/type/{type}', name: 'admin_service_by_type', methods: ['GET'])]
    public function showByType(string $type, ServiceService $serviceService): Response
    {
        $services = $serviceService->getServicesByType($type);

        return $this->render('backoffice/service/list.html.twig', [
            'services' => $services,
            'filterName' => 'Type: ' . $type,
        ]);
    }

    #[Route('/statut/{statut}', name: 'admin_service_by_statut', methods: ['GET'])]
    public function showByStatut(string $statut, ServiceService $serviceService): Response
    {
        $services = $serviceService->getServicesByStatut($statut);

        return $this->render('backoffice/service/list.html.twig', [
            'services' => $services,
            'filterName' => 'Statut: ' . $statut,
        ]);
    }
}
