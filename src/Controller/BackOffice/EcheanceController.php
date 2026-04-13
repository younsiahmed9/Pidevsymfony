<?php

namespace App\Controller\BackOffice;

use App\Entity\Echeance;
use App\Repository\EcheanceRepository;
use App\Repository\UserRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/echeance', name: 'admin_echeance_')]
class EcheanceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EcheanceRepository $echeanceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $statut = $request->query->get('statut', '');
        $echeances = $echeanceRepository->search($q, $statut);

        return $this->render('backoffice/echeance/index.html.twig', [
            'echeances' => $echeances,
            'q' => $q,
            'statut' => $statut,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $echeance = new Echeance();
        $users = $userRepository->findAll();
        $documents = $documentRepository->findAll();

        if ($request->isMethod('POST')) {
            $user = $userRepository->find($request->request->get('id_utilisateur'));
            $doc = $documentRepository->find($request->request->get('id_document'));

            if ($user) $echeance->setUtilisateur($user);
            if ($doc) $echeance->setDocument($doc);

            $echeance->setTitre($request->request->get('titre'));
            $echeance->setStatut($request->request->get('statut'));
            $echeance->setMontant($request->request->get('montant'));
            $echeance->setDescription($request->request->get('description'));
            
            $dateEch = $request->request->get('date_echeance');
            if ($dateEch) $echeance->setDateEcheance(new \DateTime($dateEch));
            
            $dateRap = $request->request->get('date_rappel');
            if ($dateRap) $echeance->setDateRappel(new \DateTime($dateRap));

            $echeance->setUpdatedAt(new \DateTime());

            $entityManager->persist($echeance);
            $entityManager->flush();

            $this->addFlash('success', 'Échéance créée avec succès.');
            return $this->redirectToRoute('admin_echeance_index');
        }

        return $this->render('backoffice/echeance/new.html.twig', [
            'echeance' => $echeance,
            'users' => $users,
            'documents' => $documents,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Echeance $echeance): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('backoffice/echeance/show.html.twig', [
            'echeance' => $echeance,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Echeance $echeance, EntityManagerInterface $entityManager, UserRepository $userRepository, DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $userRepository->findAll();
        $documents = $documentRepository->findAll();

        if ($request->isMethod('POST')) {
            $user = $userRepository->find($request->request->get('id_utilisateur'));
            $doc = $documentRepository->find($request->request->get('id_document'));

            if ($user) $echeance->setUtilisateur($user);
            if ($doc) $echeance->setDocument($doc);

            $echeance->setTitre($request->request->get('titre'));
            $echeance->setStatut($request->request->get('statut'));
            $echeance->setMontant($request->request->get('montant'));
            $echeance->setDescription($request->request->get('description'));
            
            $dateEch = $request->request->get('date_echeance');
            if ($dateEch) $echeance->setDateEcheance(new \DateTime($dateEch));
            
            $dateRap = $request->request->get('date_rappel');
            if ($dateRap) $echeance->setDateRappel(new \DateTime($dateRap));

            $echeance->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Échéance mise à jour.');
            return $this->redirectToRoute('admin_echeance_index');
        }

        return $this->render('backoffice/echeance/edit.html.twig', [
            'echeance' => $echeance,
            'users' => $users,
            'documents' => $documents,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Echeance $echeance, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$echeance->getId(), $request->request->get('_token'))) {
            $entityManager->remove($echeance);
            $entityManager->flush();
            $this->addFlash('success', 'Échéance supprimée.');
        }

        return $this->redirectToRoute('admin_echeance_index');
    }
}