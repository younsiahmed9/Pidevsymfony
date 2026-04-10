<?php
namespace App\Controller\Admin;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\CategorieRepository;
use App\Repository\DocumentRepository;
use App\Repository\DossierRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/document')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'admin_document_index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $repo, CategorieRepository $catRepo, DossierRepository $dosRepo): Response
    {
        $query       = $request->query->get('q', '');
        $type        = $request->query->get('type', '');
        $statut      = $request->query->get('statut', '');
        $categorieId = $request->query->getInt('categorie', 0) ?: null;
        $dossierId   = $request->query->getInt('dossier', 0) ?: null;

        return $this->render('admin/document/index.html.twig', [
            'documents'   => $repo->search($query, $type, $statut, $categorieId, $dossierId),
            'categories'  => $catRepo->findAll(),
            'dossiers'    => $dosRepo->findAll(),
            'q'           => $query,
            'type'        => $type,
            'statut'      => $statut,
            'categorieId' => $categorieId,
            'dossierId'   => $dossierId,
        ]);
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, UtilisateurRepository $userRepo): Response
    {
        $document = new Document();
        $document->setUtilisateur($userRepo->findOneBy([]));

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichier')->getData();
            if ($fichier) {
                $fileSize     = $fichier->getSize();
                $safeFilename = $slugger->slug(pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename  = $safeFilename.'-'.uniqid().'.'.$fichier->guessExtension();
                $fichier->move($this->getParameter('documents_directory'), $newFilename);
                $document->setCheminFichier($newFilename);
                $document->setTailleFichier($fileSize);
            } else {
                $document->setCheminFichier('no-file');
            }
            $em->persist($document);
            $em->flush();
            $this->addFlash('success', 'Document créé avec succès.');
            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('admin/document/new.html.twig', ['document' => $document, 'form' => $form]);
    }

    #[Route('/{id}', name: 'admin_document_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->render('admin/document/show.html.twig', ['document' => $document]);
    }

    #[Route('/{id}/edit', name: 'admin_document_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichier')->getData();
            if ($fichier) {
                $fileSize     = $fichier->getSize();
                $safeFilename = $slugger->slug(pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename  = $safeFilename.'-'.uniqid().'.'.$fichier->guessExtension();
                $fichier->move($this->getParameter('documents_directory'), $newFilename);
                $document->setCheminFichier($newFilename);
                $document->setTailleFichier($fileSize);
            }
            $document->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Document modifié avec succès.');
            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('admin/document/edit.html.twig', ['document' => $document, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $em->remove($document);
            $em->flush();
            $this->addFlash('success', 'Document supprimé avec succès.');
        }
        return $this->redirectToRoute('admin_document_index');
    }
}
