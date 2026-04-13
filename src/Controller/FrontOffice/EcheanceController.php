<?php

namespace App\Controller\FrontOffice;

use App\Entity\Document;
use App\Entity\Echeance;
use App\Entity\User;
use App\Repository\EcheanceRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/echeance', name: 'echeance_')]
class EcheanceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EcheanceRepository $echeanceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $q = $request->query->get('q', '');
        $statut = $request->query->get('statut', '');

        $qb = $echeanceRepository->createQueryBuilder('e')
            ->leftJoin('e.document', 'd')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user);

        if ($q !== '') {
            $qb->andWhere('e.titre LIKE :q OR d.titre LIKE :q OR e.description LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($statut !== '') {
            $qb->andWhere('e.statut = :statut')
               ->setParameter('statut', $statut);
        }

        $echeances = $qb->orderBy('e.dateEcheance', 'ASC')->getQuery()->getResult();

        return $this->render('frontoffice/echeance/index.html.twig', [
            'echeances' => $echeances,
            'q' => $q,
            'statut' => $statut,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, DocumentRepository $documentRepository, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof User) {
            $this->addFlash('danger', 'Profil utilisateur introuvable.');
            return $this->redirectToRoute('echeance_index');
        }

        $echeance = new Echeance();
        $documents = $documentRepository->findBy(['utilisateur' => $user]);
        $echeanceFormData = $this->createEcheanceFormData();

        if ($request->isMethod('POST')) {
            $this->hydrateEcheanceFromRequest($echeance, $request, $user, $documentRepository);
            $echeanceFormData = $this->createEcheanceFormData(null, $request);

            $violations = $validator->validate($echeance);
            if (count($violations) > 0) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => false,
                        'errors' => $this->normalizeViolations($violations),
                    ], 422);
                }

                foreach ($violations as $violation) {
                    $this->addFlash('form_error', (string) $violation->getMessage());
                }
            } else {
                $entityManager->persist($echeance);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => true,
                        'redirect' => $this->generateUrl('echeance_index'),
                    ]);
                }

                $this->addFlash('success', 'Échéance planifiée avec succès.');
                return $this->redirectToRoute('echeance_index');
            }
        }

        return $this->render('frontoffice/echeance/new.html.twig', [
            'echeance' => $echeance,
            'documents' => $documents,
            'echeanceFormData' => $echeanceFormData,
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request, DocumentRepository $documentRepository, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Vous devez être connecté.']]], 401);
        }

        $echeance = new Echeance();
        $this->hydrateEcheanceFromRequest($echeance, $request, $user, $documentRepository);

        $violations = $validator->validate($echeance);

        return $this->json([
            'valid' => count($violations) === 0,
            'errors' => $this->normalizeViolations($violations),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EcheanceRepository $echeanceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $echeance = $echeanceRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$echeance) {
            throw $this->createNotFoundException('Échéance introuvable.');
        }

        return $this->render('frontoffice/echeance/show.html.twig', [
            'echeance' => $echeance,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EcheanceRepository $echeanceRepository, DocumentRepository $documentRepository, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $echeance = $echeanceRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if (!$echeance) {
            throw $this->createNotFoundException('Échéance introuvable.');
        }

        $documents = $documentRepository->findBy(['utilisateur' => $user]);
        $echeanceFormData = $this->createEcheanceFormData($echeance);

        if ($request->isMethod('POST')) {
            $this->hydrateEcheanceFromRequest($echeance, $request, $user, $documentRepository);
            $echeanceFormData = $this->createEcheanceFormData($echeance, $request);

            $violations = $validator->validate($echeance);
            if (count($violations) > 0) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => false,
                        'errors' => $this->normalizeViolations($violations),
                    ], 422);
                }

                foreach ($violations as $violation) {
                    $this->addFlash('form_error', (string) $violation->getMessage());
                }
            } else {
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => true,
                        'redirect' => $this->generateUrl('echeance_index'),
                    ]);
                }

                $this->addFlash('success', 'Échéance mise à jour.');
                return $this->redirectToRoute('echeance_index');
            }
        }

        return $this->render('frontoffice/echeance/edit.html.twig', [
            'echeance' => $echeance,
            'documents' => $documents,
            'echeanceFormData' => $echeanceFormData,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EcheanceRepository $echeanceRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $echeance = $echeanceRepository->findOneBy([
            'id' => $id,
            'utilisateur' => $user
        ]);

        if ($echeance && $this->isCsrfTokenValid('delete'.$echeance->getId(), $request->request->get('_token'))) {
            $entityManager->remove($echeance);
            $entityManager->flush();
            $this->addFlash('success', 'Échéance supprimée.');
        }

        return $this->redirectToRoute('echeance_index');
    }

    private function hydrateEcheanceFromRequest(Echeance $echeance, Request $request, User $user, DocumentRepository $documentRepository): void
    {
        $echeance->setUtilisateur($user);

        $documentId = $request->request->get('id_document');
        $document = $documentId !== null && $documentId !== '' ? $documentRepository->find($documentId) : null;
        if ($document instanceof Document && $document->getUtilisateur() === $user) {
            $echeance->setDocument($document);
        } else {
            $echeance->setDocument(null);
        }

        $echeance->setTitre(trim((string) $request->request->get('titre', '')));
        $echeance->setStatut(trim((string) $request->request->get('statut', 'pending')));

        $montant = trim((string) $request->request->get('montant', ''));
        $echeance->setMontant($montant !== '' ? $montant : null);

        $description = trim((string) $request->request->get('description', ''));
        $echeance->setDescription($description !== '' ? $description : null);

        $dateEcheance = trim((string) $request->request->get('date_echeance', ''));
        $echeance->setDateEcheance($dateEcheance !== '' ? new \DateTime($dateEcheance) : null);

        $dateRappel = trim((string) $request->request->get('date_rappel', ''));
        $echeance->setDateRappel($dateRappel !== '' ? new \DateTime($dateRappel) : null);

        $echeance->setUpdatedAt(new \DateTime());
    }

    private function createEcheanceFormData(?Echeance $echeance = null, ?Request $request = null): array
    {
        $document = $echeance?->getDocument();
        $documentId = $document?->getId() ? (string) $document->getId() : '';
        $echeanceId = $echeance?->getId() ? (string) $echeance->getId() : '';
        $titre = $echeance?->getTitre() ?? '';
        $dateEcheance = $echeance?->getDateEcheance()?->format('Y-m-d') ?? '';
        $dateRappel = $echeance?->getDateRappel()?->format('Y-m-d') ?? '';
        $montant = $echeance?->getMontant() ?? '';
        $statut = $echeance?->getStatut() ?? 'pending';
        $description = $echeance?->getDescription() ?? '';

        if ($request) {
            $documentId = (string) $request->request->get('id_document', $documentId);
            $echeanceId = (string) $request->request->get('echeance_id', $echeanceId);
            $titre = (string) $request->request->get('titre', $titre);
            $dateEcheance = (string) $request->request->get('date_echeance', $dateEcheance);
            $dateRappel = (string) $request->request->get('date_rappel', $dateRappel);
            $montant = (string) $request->request->get('montant', $montant);
            $statut = (string) $request->request->get('statut', $statut);
            $description = (string) $request->request->get('description', $description);
        }

        return [
            'id_echeance' => $echeanceId,
            'id_document' => $documentId,
            'titre' => $titre,
            'date_echeance' => $dateEcheance,
            'date_rappel' => $dateRappel,
            'montant' => $montant,
            'statut' => $statut,
            'description' => $description,
        ];
    }

    private function normalizeViolations(iterable $violations): array
    {
        $errors = [];
        $fieldMap = [
            'document' => 'id_document',
            'dateEcheance' => 'date_echeance',
            'dateRappel' => 'date_rappel',
            'utilisateur' => 'general',
        ];

        foreach ($violations as $violation) {
            $rawPath = $violation->getPropertyPath();
            $propertyPath = $fieldMap[$rawPath] ?? ($rawPath !== '' ? $rawPath : 'general');
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return $errors;
    }
}