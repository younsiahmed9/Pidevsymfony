<?php

namespace App\Controller\BackOffice;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Repository\CategorieRepository;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/document', name: 'admin_document_')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $documents = $documentRepository->search($q);

        return $this->render('backoffice/document/index.html.twig', [
            'documents' => $documents,
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $document = new Document();
        $selectedUserId = null;
        $users = $userRepository->findAll();
        $categories = $categorieRepository->findAll();
        $dossiers = $dossierRepository->findAll();

        if ($request->isMethod('POST')) {
            $selectedUserId = $request->request->get('id_utilisateur');
            $user = $userRepository->find($selectedUserId);
            $categorie = $categorieRepository->find($request->request->get('id_categorie'));
            $dossier = $dossierRepository->find($request->request->get('id_dossier'));

            if ($user) $document->setUtilisateur($user);
            if ($categorie) $document->setCategorie($categorie);
            if ($dossier) $document->setDossier($dossier);

            $document->setTitre($request->request->get('titre'));
            $document->setTypeDocument($request->request->get('type_document'));
            $document->setStatut($request->request->get('statut'));
            $document->setDescription($request->request->get('description'));
            $document->setTags($request->request->get('tags'));
            
            $dateDoc = $request->request->get('date_document');
            if ($dateDoc) $document->setDateDocument(new \DateTime($dateDoc));
            
            $dateEch = $request->request->get('date_echeance');
            if ($dateEch) $document->setDateEcheance(new \DateTime($dateEch));

            $file = $request->files->get('fichier');
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                $file->move(
                    $this->getParameter('documents_directory'),
                    $newFilename
                );
                $document->setCheminFichier($newFilename);
                $document->setTailleFichier($file->getSize());
            }

            $document->setUpdatedAt(new \DateTime());

            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', 'Document ajouté avec succès.');
            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('backoffice/document/new.html.twig', [
            'document' => $document,
            'users' => $users,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('backoffice/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $entityManager, UserRepository $userRepository, CategorieRepository $categorieRepository, DossierRepository $dossierRepository, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $selectedUserId = $document->getUtilisateur()?->getId();
        $users = $userRepository->findAll();
        $categories = $categorieRepository->findAll();
        $dossiers = $dossierRepository->findAll();

        if ($request->isMethod('POST')) {
            $selectedUserId = $request->request->get('id_utilisateur');
            $user = $userRepository->find($selectedUserId);
            $categorie = $categorieRepository->find($request->request->get('id_categorie'));
            $dossier = $dossierRepository->find($request->request->get('id_dossier'));

            if ($user) $document->setUtilisateur($user);
            if ($categorie) $document->setCategorie($categorie);
            if ($dossier) $document->setDossier($dossier);

            $document->setTitre($request->request->get('titre'));
            $document->setTypeDocument($request->request->get('type_document'));
            $document->setStatut($request->request->get('statut'));
            $document->setDescription($request->request->get('description'));
            $document->setTags($request->request->get('tags'));
            
            $dateDoc = $request->request->get('date_document');
            if ($dateDoc) $document->setDateDocument(new \DateTime($dateDoc));
            
            $dateEch = $request->request->get('date_echeance');
            if ($dateEch) $document->setDateEcheance(new \DateTime($dateEch));

            $file = $request->files->get('fichier');
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                $file->move(
                    $this->getParameter('documents_directory'),
                    $newFilename
                );
                $document->setCheminFichier($newFilename);
                $document->setTailleFichier($file->getSize());
            }

            $document->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Document mis à jour.');
            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('backoffice/document/edit.html.twig', [
            'document' => $document,
            'users' => $users,
            'categories' => $categories,
            'dossiers' => $dossiers,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $entityManager->remove($document);
            $entityManager->flush();
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('admin_document_index');
    }
}