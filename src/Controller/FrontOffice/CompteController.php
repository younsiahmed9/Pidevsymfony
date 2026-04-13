<?php

namespace App\Controller\FrontOffice;

use App\Entity\Compte;
use App\Repository\CompteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/compte', name: 'compte_')]
class CompteController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, CompteRepository $compteRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $typeFilter = trim((string) $request->query->get('type_compte', ''));
        $searchFilter = trim((string) $request->query->get('q', ''));

        $qb = $compteRepository->createQueryBuilder('c')
            ->where('c.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCreation', 'DESC');

        if (in_array($typeFilter, ['courant', 'epargne'], true)) {
            $qb->andWhere('c.typeCompte = :type_compte')
                ->setParameter('type_compte', $typeFilter);
        }

        if ($searchFilter !== '') {
            $qb->andWhere('LOWER(c.numeroCompte) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($searchFilter) . '%');
        }

        $comptes = $qb->getQuery()->getResult();
        $comptesCourants = array_values(array_filter($comptes, static fn (Compte $compte) => $compte->getTypeCompte() === 'courant'));
        $comptesEpargne = array_values(array_filter($comptes, static fn (Compte $compte) => $compte->getTypeCompte() === 'epargne'));

        $cartesSync = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id,
                    c.numero_carte AS numeroCarte,
                    c.type,
                    c.solde,
                    c.devise,
                    c.is_active AS isActive,
                    p.nom AS portefeuilleNom
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.user_id = :user_id
             ORDER BY c.id DESC',
            ['user_id' => $user->getId()]
        );

        return $this->render('frontoffice/compte/index.html.twig', [
            'comptes' => $comptes,
            'comptesCourants' => $comptesCourants,
            'comptesEpargne' => $comptesEpargne,
            'filters' => [
                'type_compte' => $typeFilter,
                'q' => $searchFilter,
            ],
            'cartesSync' => $cartesSync,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $compte = new Compte();
        
        if ($request->isMethod('POST')) {
            $this->hydrateCompteFromRequest($compte, $request, $user);

            if ($this->isCompteFormCompletelyEmpty($request)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => false,
                        'errors' => [
                            'general' => ['Tous les champs sont vides. Veuillez remplir au moins les informations obligatoires.'],
                        ],
                    ], 422);
                }

                $this->addFlash('form_error', 'Tous les champs sont vides. Veuillez remplir au moins les informations obligatoires.');
                return $this->render('frontoffice/compte/new.html.twig', [
                    'compte' => $compte,
                ]);
            }

            $violations = $validator->validate($compte);
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
                $entityManager->persist($compte);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => true,
                        'redirect' => $this->generateUrl('compte_index'),
                    ]);
                }

                $this->addFlash('success', 'Votre compte a ete ouvert avec succes.');
                return $this->redirectToRoute('compte_index');
            }
        }

        return $this->render('frontoffice/compte/new.html.twig', [
            'compte' => $compte,
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Vous devez être connecté.']]], 401);
        }

        $compte = new Compte();
        $this->hydrateCompteFromRequest($compte, $request, $user);

        if ($this->isCompteFormCompletelyEmpty($request)) {
            return $this->json([
                'valid' => false,
                'errors' => [
                    'general' => ['Tous les champs sont vides. Veuillez remplir au moins les informations obligatoires.'],
                ],
            ], 422);
        }

        $violations = $validator->validate($compte);

        return $this->json([
            'valid' => count($violations) === 0,
            'errors' => $this->normalizeViolations($violations),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Compte $compte): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if ($compte->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        return $this->render('frontoffice/compte/show.html.twig', [
            'compte' => $compte,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Compte $compte, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if ($compte->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        if ($request->isMethod('POST')) {
            $this->hydrateCompteFromRequest($compte, $request, $user);
            $violations = $validator->validate($compte);

            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('form_error', (string) $violation->getMessage());
                }
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'Informations du compte mises à jour.');
                return $this->redirectToRoute('compte_index');
            }
        }

        return $this->render('frontoffice/compte/edit.html.twig', [
            'compte' => $compte,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Compte $compte, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if ($compte->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        if ($this->isCsrfTokenValid('delete'.$compte->getId(), $request->request->get('_token'))) {
            $entityManager->remove($compte);
            $entityManager->flush();
            $this->addFlash('success', 'Compte fermé.');
        }

        return $this->redirectToRoute('compte_index');
    }

    private function hydrateCompteFromRequest(Compte $compte, Request $request, object $user): void
    {
        $data = $request->request->all();

        $compte->setUtilisateur($user);
        $compte->setNumeroCompte(trim((string) ($data['numero_compte'] ?? '')));
        $typeCompte = trim((string) ($data['type_compte'] ?? ''));
        $compte->setTypeCompte($typeCompte);
        $compte->setSolde(trim((string) ($data['solde'] ?? '')));

        if ($typeCompte === 'courant') {
            $compte->setPlafondDecouvert(($data['plafond_decouvert'] ?? '') !== '' ? trim((string) $data['plafond_decouvert']) : null);
            $compte->setTauxInteret(null);
        } elseif ($typeCompte === 'epargne') {
            $compte->setTauxInteret(($data['taux_interet'] ?? '') !== '' ? trim((string) $data['taux_interet']) : null);
            $compte->setPlafondDecouvert(null);
        } else {
            $compte->setTauxInteret(($data['taux_interet'] ?? '') !== '' ? trim((string) $data['taux_interet']) : null);
            $compte->setPlafondDecouvert(($data['plafond_decouvert'] ?? '') !== '' ? trim((string) $data['plafond_decouvert']) : null);
        }

        if ($compte->getId() === null) {
            $compte->setEtat('actif');
            $compte->setDateCreation(new \DateTime());
        }
    }

    private function isCompteFormCompletelyEmpty(Request $request): bool
    {
        $data = $request->request->all();

        $fields = [
            trim((string) ($data['numero_compte'] ?? '')),
            trim((string) ($data['type_compte'] ?? '')),
            trim((string) ($data['solde'] ?? '')),
            trim((string) ($data['taux_interet'] ?? '')),
            trim((string) ($data['plafond_decouvert'] ?? '')),
        ];

        foreach ($fields as $fieldValue) {
            if ($fieldValue !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeViolations(iterable $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = (string) $violation->getPropertyPath();
            $field = match ($propertyPath) {
                'numeroCompte' => 'numero_compte',
                'typeCompte' => 'type_compte',
                'solde' => 'solde',
                'tauxInteret' => 'taux_interet',
                'plafondDecouvert' => 'plafond_decouvert',
                default => 'general',
            };

            $errors[$field][] = (string) $violation->getMessage();
        }

        return $errors;
    }
}
