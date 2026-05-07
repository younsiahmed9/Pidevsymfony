<?php

namespace App\Controller\FrontOffice;

use App\Entity\Compte;
use App\Entity\Credit;
use App\Entity\Releve;
use App\Repository\CompteRepository;
use App\Repository\ReleveRepository;
use App\Service\PdfReleveService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(CompteRepository $compteRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer tous les comptes de l'utilisateur
        $comptes = $compteRepository->findBy(['utilisateur' => $user], ['dateCreation' => 'DESC']);

        // Calcul du solde total
        $soldeTotal = 0;
        $creditsApprouvesList = [];
        $creditsTotal = [];
        $creditsRemboursesCount = 0;

        foreach ($comptes as $compte) {
            $soldeTotal += (float) $compte->getSolde();
            
            // Récupérer les crédits du compte via la relation
            foreach ($compte->getCredits() as $credit) {
                $creditsTotal[] = $credit;
                if ($credit->getStatus() === 'approuve') {
                    $creditsApprouvesList[] = $credit;
                } elseif ($credit->getStatus() === 'rembourse') {
                    $creditsRemboursesCount++;
                }
            }
        }

        // Calcul des statistiques par compte et des scores améliorés
        $compteStats = [];
        $totalCreditsApprouves = 0;
        $totalCredit = 0;
        $scoresDetails = [];

        foreach ($comptes as $compte) {
            $compteId = $compte->getId();
            $compteCredits = $compte->getCredits()->toArray();
            $creditsApprouves = array_filter($compteCredits, static fn (Credit $c) => $c->getStatus() === 'approuve');
            
            $montantCreditTotal = 0;
            $montantCreditApprouve = 0;

            foreach ($compteCredits as $credit) {
                $montantCreditTotal += (float) $credit->getMontant();
            }

            foreach ($creditsApprouves as $credit) {
                $montantCreditApprouve += (float) $credit->getMontant();
            }

            // Score de confiance amélioré par compte (multifacteurs)
            $scoreSolde = min(100, ((float) $compte->getSolde() / 50000) * 100); // Jusqu'à 100 points
            $scoreTypeCompte = $compte->getTypeCompte() === 'epargne' ? 20 : 10; // Bonus épargne
            
            // Score basé sur la gestion des crédits
            $scoreCreditManagement = 0;
            if (count($compteCredits) > 0) {
                $scoreCreditManagement = ($creditsRemboursesCount / (count($compteCredits) + $creditsRemboursesCount)) * 40;
            } else {
                $scoreCreditManagement = 20; // Bonus pas de dettes
            }
            
            // Score de solvabilité (ratio dette/actif)
            $scoreInsolvency = min(50, ((float) $compte->getSolde() / max(1, $montantCreditApprouve + 1)) * 100);
            
            // Calcul du score final
            $scoreConfiance = ($scoreSolde * 0.40) + ($scoreTypeCompte) + ($scoreCreditManagement * 0.35) + ($scoreInsolvency * 0.25);
            $scoreConfiance = min(100, max(0, $scoreConfiance));

            $compteStats[$compteId] = [
                'compte' => $compte,
                'scoreConfiance' => $scoreConfiance,
                'creditsCount' => count($compteCredits),
                'creditsApprouvesCount' => count($creditsApprouves),
                'montantCreditTotal' => $montantCreditTotal,
                'montantCreditApprouve' => $montantCreditApprouve,
            ];

            $scoresDetails[] = [
                'scoreSolde' => $scoreSolde,
                'scoreTypeCompte' => $scoreTypeCompte,
                'scoreCreditManagement' => $scoreCreditManagement,
                'scoreInsolvency' => $scoreInsolvency,
            ];

            $totalCredit += $montantCreditTotal;
            $totalCreditsApprouves += $montantCreditApprouve;
        }

        // Score global amélioré
        $scoreGlobal = count($compteStats) > 0 
            ? array_sum(array_map(static fn ($stat) => $stat['scoreConfiance'], $compteStats)) / count($compteStats)
            : 100;

        // Utilisation crédit (pourcentage)
        $utilisationCredit = $soldeTotal > 0 ? min(100, ($totalCreditsApprouves / $soldeTotal) * 100) : 0;

        // Fiabilité paiement (basée sur le nombre de crédits remboursés vs total)
        $fiabilitePayment = count($creditsTotal) > 0 
            ? (($creditsRemboursesCount + count($creditsApprouvesList)) / count($creditsTotal)) * 100
            : 100;

        // Répartition des comptes par type
        $comptesCourants = count(array_filter($comptes, static fn (Compte $compte) => $compte->getTypeCompte() === 'courant'));
        $comptesEpargne = count(array_filter($comptes, static fn (Compte $compte) => $compte->getTypeCompte() === 'epargne'));

        // Montant maximum empruntable basé sur le score
        $borrowingPower = $soldeTotal * (1 + ($scoreGlobal / 100));
        $maxBorrowableAmount = min($borrowingPower, $soldeTotal * 3); // Max 3x le solde

        return $this->render('frontoffice/compte/dashboard.html.twig', [
            'soldeTotal' => $soldeTotal,
            'scoreGlobal' => $scoreGlobal,
            'compteStats' => $compteStats,
            'utilisationCredit' => $utilisationCredit,
            'fiabilitePayment' => $fiabilitePayment,
            'comptesCourants' => $comptesCourants,
            'comptesEpargne' => $comptesEpargne,
            'totalCreditsApprouves' => $totalCreditsApprouves,
            'totalCredits' => $totalCredit,
            'creditsRemboursesCount' => $creditsRemboursesCount,
            'maxBorrowableAmount' => $maxBorrowableAmount,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, CompteRepository $compteRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $compte = $compteRepository->find($id);
        if (!$compte) {
            throw $this->createNotFoundException('Le compte n\'existe pas.');
        }

        $user = $this->getUser();
        if ($compte->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        return $this->render('frontoffice/compte/show.html.twig', [
            'compte' => $compte,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, CompteRepository $compteRepository, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $compte = $compteRepository->find($id);
        if (!$compte) {
            throw $this->createNotFoundException('Le compte n\'existe pas.');
        }

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
    public function delete(int $id, Request $request, CompteRepository $compteRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $compte = $compteRepository->find($id);
        if (!$compte) {
            throw $this->createNotFoundException('Le compte n\'existe pas.');
        }

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

    #[Route('/{id}/releve/generer', name: 'releve_generer', methods: ['POST'])]
    public function genererReleve(
        int $id,
        PdfReleveService $pdfService,
        ReleveRepository $releveRepository,
        CompteRepository $compteRepository,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $compte = $compteRepository->find($id);
        if (!$compte) {
            throw $this->createNotFoundException('Le compte n\'existe pas.');
        }

        $user = $this->getUser();
        if ($compte->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        try {
            // Générer l'URL du compte pour le QR code
            $urlCompte = $urlGenerator->generate(
                'compte_show',
                ['id' => $compte->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Générer le PDF avec QR code
            $pdfPath = $pdfService->genererReleve($compte, $urlCompte);

            // Enregistrer le relevé en base de données
            $releve = new Releve();
            $releve->setCompte($compte);
            $releve->setDateGeneration(new \DateTimeImmutable());
            $releve->setCheminFichier($pdfPath);
            $releve->setSoldeAuMoment($compte->getSolde());
            $releve->setNombreCreditsAuMoment($compte->getCredits()->count());
            $releve->setTypeReleve('compte_complet');

            // Stocker les métadonnées
            $metadonnees = [
                'typeCompte' => $compte->getTypeCompte(),
                'etat' => $compte->getEtat(),
                'tauxInteret' => $compte->getTauxInteret(),
                'plafondDecouvert' => $compte->getPlafondDecouvert(),
                'creditsApprouves' => $compte->getCredits()->filter(fn(Credit $c) => $c->getStatus() === 'approuve')->count(),
            ];
            $releve->setMetadonneesArray($metadonnees);

            $entityManager->persist($releve);
            $entityManager->flush();

            $this->addFlash('success', 'Relevé généré et enregistré avec succès.');

            return $this->json([
                'success' => true,
                'message' => 'Relevé généré avec succès',
                'releveId' => $releve->getId(),
                'downloadUrl' => $this->generateUrl('compte_releve_telecharger', ['releveId' => $releve->getId()])
            ]);
        } catch (\Exception $e) {
            error_log('Erreur lors de la génération du relevé: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->addFlash('error', 'Erreur lors de la génération du relevé: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du relevé: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/releve/{releveId}/telecharger', name: 'releve_telecharger', methods: ['GET'])]
    public function telechargerReleve(
        int $releveId,
        ReleveRepository $releveRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $releve = $releveRepository->find($releveId);
        if (!$releve) {
            throw $this->createNotFoundException('Relevé non trouvé');
        }

        $user = $this->getUser();
        if ($releve->getCompte()->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce relevé ne vous appartient pas.');
        }

        $filepath = $releve->getCheminFichier();
        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Fichier de relevé non trouvé');
        }

        // Créer la réponse binaire
        $response = new BinaryFileResponse($filepath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'releve_' . $releve->getCompte()->getNumeroCompte() . '_' . $releve->getDateGeneration()->format('Y_m_d_H_i_s') . '.pdf'
        );

        return $response;
    }

    #[Route('/{id}/releves', name: 'releves_historique', methods: ['GET'])]
    public function historiquesReleves(
        int $id,
        ReleveRepository $releveRepository,
        CompteRepository $compteRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $compte = $compteRepository->find($id);
        if (!$compte) {
            throw $this->createNotFoundException('Le compte n\'existe pas.');
        }

        $user = $this->getUser();
        if ($compte->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        $releves = $releveRepository->findByCompteOrderedByDate($compte);

        return $this->render('frontoffice/compte/releves_historique.html.twig', [
            'compte' => $compte,
            'releves' => $releves,
        ]);
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
