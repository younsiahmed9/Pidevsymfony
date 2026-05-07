<?php

namespace App\Controller\FrontOffice;

use App\Entity\Credit;
use App\Entity\User;
use App\Repository\CreditRepository;
use App\Repository\CompteRepository;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/credit', name: 'credit_')]
class CreditController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, CreditRepository $creditRepository, CompteRepository $compteRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $compteId = $request->query->getInt('compte_id', 0);
        $status = trim((string) $request->query->get('status', ''));
        $q = trim((string) $request->query->get('q', ''));
        $selectedCompte = null;
        if ($compteId > 0) {
            $candidateCompte = $compteRepository->find($compteId);
            if ($candidateCompte && $candidateCompte->getUtilisateur() === $user) {
                $selectedCompte = $candidateCompte;
            }
        }

        // Filtrage des crédits via les comptes de l'utilisateur (optionnellement par compte)
        $qb = $creditRepository->createQueryBuilder('cr')
            ->join('cr.compte', 'c')
            ->where('c.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('cr.dateDebut', 'DESC');

        if ($selectedCompte) {
            $qb
                ->andWhere('c.id = :compte_id')
                ->setParameter('compte_id', $selectedCompte->getId());
        }

        if (in_array($status, ['en_attente', 'approuve', 'refuse', 'rembourse'], true)) {
            $qb
                ->andWhere('cr.status = :status')
                ->setParameter('status', $status);
        }

        if ($q !== '') {
            $qb
                ->andWhere('LOWER(c.numeroCompte) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        $credits = $qb->getQuery()->getResult();

        return $this->render('frontoffice/credit/index.html.twig', [
            'credits' => $credits,
            'selectedCompte' => $selectedCompte,
            'filters' => [
                'status' => $status,
                'q' => $q,
                'compte_id' => $selectedCompte?->getId(),
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CompteRepository $compteRepository,
        SmsService $smsService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $credit = new Credit();
        $userComptes = $compteRepository->findBy(['utilisateur' => $user, 'etat' => 'actif', 'typeCompte' => 'courant']);
        $selectedCompte = null;
        $creditErrors = [
            'compte' => [],
            'montant' => [],
            'duree_mois' => [],
            'date_debut' => [],
            'taux_interet' => [],
        ];
        $creditFormData = [
            'compte' => '',
            'montant' => '',
            'duree_mois' => '',
            'date_debut' => (new \DateTime())->format('Y-m-d'),
            'taux_interet' => '5.50',
        ];

        $compteId = $request->query->getInt('compte_id', 0);
        if ($compteId > 0) {
            $preselectedCompte = $compteRepository->find($compteId);
            if ($preselectedCompte && $preselectedCompte->getUtilisateur() === $user && $preselectedCompte->getTypeCompte() === 'courant') {
                $credit->setCompte($preselectedCompte);
                $selectedCompte = $preselectedCompte;
                $creditFormData['compte'] = (string) $preselectedCompte->getId();
            } elseif ($preselectedCompte && $preselectedCompte->getUtilisateur() === $user) {
                $this->addFlash('danger', 'Un crédit ne peut être associé qu\'à un compte courant.');
                return $this->redirectToRoute('compte_index');
            }
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $creditFormData['compte'] = (string) ($data['compte'] ?? '');
            $creditFormData['montant'] = (string) ($data['montant'] ?? '');
            $creditFormData['duree_mois'] = (string) ($data['duree_mois'] ?? '');
            $creditFormData['date_debut'] = (string) ($data['date_debut'] ?? $creditFormData['date_debut']);
            $creditFormData['taux_interet'] = (string) ($data['taux_interet'] ?? $creditFormData['taux_interet']);

            $requestedCompteId = (int) ($data['compte'] ?? 0);
            $compte = $selectedCompte ?: $compteRepository->find($requestedCompteId);

            // Vérification du compte
            if (!$compte || $compte->getUtilisateur() !== $user) {
                $creditErrors['compte'][] = 'Compte invalide ou non autorisé.';
            } elseif ($compte->getTypeCompte() !== 'courant') {
                $creditErrors['compte'][] = 'Un crédit ne peut être demandé que sur un compte courant.';
            }

            // Vérification montant souhaité
            $rawMontant = trim((string) ($data['montant'] ?? ''));
            $normalizedMontant = str_replace(',', '.', $rawMontant);
            if ($rawMontant === '') {
                $creditErrors['montant'][] = 'Le montant souhaité est obligatoire.';
            } elseif (!is_numeric($normalizedMontant) || (float) $normalizedMontant <= 0) {
                $creditErrors['montant'][] = 'Le montant doit être un nombre positif supérieur à 0.';
            }

            // Capacité d'emprunt (règle simple et stable) : max = 3x le solde du compte courant
            if ($compte && $compte->getUtilisateur() === $user && is_numeric($normalizedMontant)) {
                $solde = (float) $compte->getSolde();
                $requested = (float) $normalizedMontant;
                $maxBorrowable = max(0.0, $solde) * 3;
                if ($solde <= 0) {
                    $creditErrors['montant'][] = 'Solde insuffisant : votre compte doit avoir un solde positif pour demander un crédit.';
                } elseif ($requested > $maxBorrowable) {
                    $creditErrors['montant'][] = sprintf(
                        'Montant trop élevé selon votre solde. Solde: %.2f DT, montant maximum autorisé: %.2f DT.',
                        $solde,
                        $maxBorrowable
                    );
                }
            }

            // Vérification durée
            $dureeMois = (int) ($data['duree_mois'] ?? 0);
            if ($dureeMois <= 0) {
                $creditErrors['duree_mois'][] = 'La durée doit être supérieure à 0 mois.';
            }

            // Vérification taux
            $rawTaux = trim((string) ($data['taux_interet'] ?? '0'));
            $normalizedTaux = str_replace(',', '.', $rawTaux);
            if (!is_numeric($normalizedTaux) || (float) $normalizedTaux <= 0) {
                $creditErrors['taux_interet'][] = 'Le taux doit être un nombre positif.';
            }

            // Vérification date
            $dateDebut = null;
            try {
                $dateDebut = new \DateTime((string) ($data['date_debut'] ?? 'now'));
            } catch (\Throwable) {
                $creditErrors['date_debut'][] = 'Date de début invalide.';
            }

            $hasErrors = false;
            foreach ($creditErrors as $fieldErrors) {
                if ($fieldErrors !== []) {
                    $hasErrors = true;
                    break;
                }
            }

            if (!$hasErrors && $compte) {
                $credit->setCompte($compte);
                $selectedCompte = $compte;
                $creditFormData['compte'] = (string) $compte->getId();
                $credit->setMontant(number_format((float) $normalizedMontant, 2, '.', ''));
                $credit->setTauxInteret(number_format((float) $normalizedTaux, 2, '.', ''));
                $credit->setDureeMois($dureeMois);
                $credit->setStatus('en_attente');
                $credit->setDateDebut($dateDebut ?? new \DateTime());

                // Calcul mensualité
                $montant = (float)$credit->getMontant();
                $tauxAnnuel = (float)$credit->getTauxInteret() / 100;
                $duree = $credit->getDureeMois();
                
                if ($tauxAnnuel > 0) {
                    $tauxMensuel = $tauxAnnuel / 12;
                    $mensualite = ($montant * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$duree));
                } else {
                    $mensualite = $montant / $duree;
                }
                
                $credit->setMensualite(number_format($mensualite, 2, '.', ''));

                $entityManager->persist($credit);
                $entityManager->flush();

                $phone = trim((string) ($user->getClient()?->getPhone() ?? ''));
                if ($phone !== '') {
                    $msg = sprintf(
                        'FinTrack: votre demande de crédit de %s DT est enregistrée (en attente). Nous vous informerons après traitement.',
                        $credit->getMontant()
                    );
                    // Crédit: envoi SMS via Twilio uniquement (comme demandé)
                    if ($smsService->sendSmsTwilioOnly($phone, $msg)) {
                        // SMS envoyé avec succès
                        $this->addFlash('success', '✅ Votre demande de crédit a été soumise. SMS de confirmation envoyé à ' . substr($phone, -4));
                    } else {
                        $hint = $smsService->getLastFailureHintFr();
                        $detail = ($hint !== null && $hint !== '') ? ' Erreur: ' . $hint : ' Vérifiez votre numéro et réessayez.';
                        $this->addFlash('danger', '❌ Demande enregistrée, mais le SMS n\'a pas pu être envoyé.' . $detail);
                    }
                } else {
                    $this->addFlash(
                        'warning',
                        '⚠️ Demande enregistrée. Ajoutez un numéro de téléphone dans votre profil pour recevoir les confirmations par SMS.'
                    );
                }
                
                return $this->redirectToRoute('credit_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire avant de soumettre.');
        }

        return $this->render('frontoffice/credit/new.html.twig', [
            'credit' => $credit,
            'comptes' => $userComptes,
            'selectedCompte' => $selectedCompte,
            'creditFormData' => $creditFormData,
            'creditErrors' => $creditErrors,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Credit $credit): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if ($credit->getCompte()->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Ce crédit ne vous appartient pas.');
        }

        return $this->render('frontoffice/credit/show.html.twig', [
            'credit' => $credit,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if ($credit->getCompte()->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }

        // On ne peut supprimer (annuler) que si c'est en attente
        if ($credit->getStatus() !== 'en_attente') {
            $this->addFlash('warning', 'Vous ne pouvez plus annuler cette demande car elle a déjà été traitée.');
            return $this->redirectToRoute('credit_index');
        }

        if ($this->isCsrfTokenValid('delete'.$credit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($credit);
            $entityManager->flush();
            $this->addFlash('success', 'Demande de crédit annulée.');
        }

        return $this->redirectToRoute('credit_index');
    }
}
