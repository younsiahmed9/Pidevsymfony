<?php

namespace App\Controller\FrontOffice;

use App\Entity\CarteVirtuelle;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\Transfer\BrevoEmailService;
use App\Service\Transfer\CurrencyRateService;
use App\Service\Transfer\GeoLocateService;
use App\Service\Transfer\TransferFeeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

#[Route('/transaction')]
final class TransactionController extends AbstractController
{
    public function __construct(
        private CurrencyRateService $currencyRateService,
        private GeoLocateService $geoLocateService,
        private BrevoEmailService $brevoEmailService,
        private TransferFeeService $transferFeeService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'front_transaction_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $filters = $this->buildTransactionFilters($request);
        $transactions = $this->fetchFilteredTransactions($entityManager, $user, $filters);

        return $this->render('frontoffice/transaction/index.html.twig', [
            'transactions' => $transactions,
            'filters' => $filters,
        ]);
    }

    #[Route('/select-card', name: 'front_transfer_select_card', methods: ['GET'])]
    public function selectCard(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $cardRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_carte, c.devise, c.solde, c.is_active, c.type,
                    p.nom AS portefeuille_nom, p.solde_total
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.user_id = :uid AND c.is_active = 1
             ORDER BY c.created_at DESC',
            ['uid' => $user->getId()]
        );

        $apiStatus = $this->buildApiStatus();
        $activeApis = count(array_filter($apiStatus, static fn (array $api): bool => $api['status'] === 'active'));
        $totalApis = count($apiStatus);

        $apiSummary = [
            'active' => $activeApis,
            'total' => $totalApis,
            'allActive' => $activeApis === $totalApis,
        ];

        return $this->render('frontoffice/transaction/select_card.html.twig', [
            'cards' => $cardRows,
            'apiStatus' => $apiStatus,
            'apiSummary' => $apiSummary,
        ]);
    }

    /**
     * @return array<string, array{name:string,description:string,status:string,icon:string,message:string}>
     */
    private function buildApiStatus(): array
    {
        $status = [
            'geolocation' => [
                'name' => 'Géolocalisation (IPLocate)',
                'description' => 'Détecte la localisation et le risque pour chaque transfert',
                'status' => 'inactive',
                'icon' => 'fa-globe',
                'message' => 'Vérification en attente',
            ],
            'currency_conversion' => [
                'name' => 'Conversion de Devises (FCS API)',
                'description' => 'Convertit les montants entre TND, EUR et USD avec taux en temps réel',
                'status' => 'inactive',
                'icon' => 'fa-exchange-alt',
                'message' => 'Vérification en attente',
            ],
            'fees' => [
                'name' => 'Calcul des Frais Intelligents',
                'description' => '2% de frais par défaut, réduits à 1% après 5 transferts',
                'status' => 'active',
                'icon' => 'fa-percent',
                'message' => 'Moteur local disponible',
            ],
            'email' => [
                'name' => 'Confirmations Email (Brevo)',
                'description' => 'Reçoit les confirmations de transfert à ton adresse email',
                'status' => 'inactive',
                'icon' => 'fa-envelope',
                'message' => 'Vérification en attente',
            ],
        ];

        try {
            $geo = $this->geoLocateService->locate('8.8.8.8');
            $isOk = $geo['country_code'] !== null || $geo['country_name'] !== null;
            $status['geolocation']['status'] = $isOk ? 'active' : 'inactive';
            $status['geolocation']['message'] = $isOk
                ? sprintf('OK - %s', (string) ($geo['provider'] ?? 'IPLOCATE'))
                : sprintf('Indisponible - %s', (string) ($geo['provider'] ?? 'Aucune réponse'));
        } catch (\Throwable $e) {
            $status['geolocation']['status'] = 'inactive';
            $status['geolocation']['message'] = 'Erreur - ' . mb_substr($e->getMessage(), 0, 80);
        }

        try {
            $rate = $this->currencyRateService->getRate('USD', 'EUR');
            $isOk = $rate > 0;
            $status['currency_conversion']['status'] = $isOk ? 'active' : 'inactive';
            $status['currency_conversion']['message'] = $isOk
                ? sprintf('OK - Taux USD/EUR temps réel: %.4f', $rate)
                : 'Indisponible - Taux invalide';
        } catch (\Throwable $e) {
            $status['currency_conversion']['status'] = 'inactive';
            $status['currency_conversion']['message'] = 'Erreur - ' . mb_substr($e->getMessage(), 0, 80);
        }

        $brevoHealth = $this->brevoEmailService->verifyConnection();
        $status['email']['status'] = $brevoHealth['ok'] ? 'active' : 'inactive';
        $status['email']['message'] = $brevoHealth['ok']
            ? 'OK - ' . $brevoHealth['message']
            : 'Indisponible - ' . mb_substr($brevoHealth['message'], 0, 80);

        return $status;
    }

    #[Route('/verify-apis', name: 'front_transaction_verify_apis', methods: ['GET'])]
    public function verifyApis(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $results = [
            'geolocation' => $this->verifyGeolocation($request),
            'currency' => $this->verifyCurrency(),
            'email' => $this->verifyEmail(),
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        return $this->json($results);
    }

    #[Route('/exchange-snapshot', name: 'front_transaction_exchange_snapshot', methods: ['GET'])]
    public function exchangeSnapshot(): JsonResponse
    {
        try {
            $snapshot = $this->currencyRateService->getExchangeSnapshot();

            return $this->json([
                'ok' => true,
                'updatedAt' => $snapshot['updatedAt'],
                'rows' => $snapshot['rows'],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'message' => mb_substr($e->getMessage(), 0, 120),
            ], 500);
        }
    }

    private function verifyGeolocation(Request $request): array
    {
        try {
            $clientIp = $this->resolveClientIp($request);

            if ($clientIp === null) {
                return [
                    'status' => 'warning',
                    'message' => 'Géolocalisation indisponible',
                    'details' => 'Impossible de détecter l\'IP client',
                ];
            }

            if ($this->isLocalOrPrivateIp($clientIp)) {
                return [
                    'status' => 'success',
                    'message' => '✓ Géolocalisation OK (mode local)',
                    'details' => sprintf('Environnement local détecté (IP: %s).', $clientIp),
                ];
            }

            $result = $this->geoLocateService->locate($clientIp);
            $isOk = $result['country_code'] !== null || $result['country_name'] !== null;

            return [
                'status' => $isOk ? 'success' : 'warning',
                'message' => $isOk
                    ? sprintf('✓ Géolocalisation OK - %s', $result['country_name'] ?? $result['country_code'] ?? 'Unknown')
                    : 'Géolocalisation indisponible',
                'details' => $isOk
                    ? sprintf('%s (%s) - IP: %s', $result['country_name'] ?? '', $result['city'] ?? '', $clientIp)
                    : sprintf('IP non localisable (%s). Si tu es en local, c\'est souvent une IP privée.', $clientIp),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur géolocalisation',
                'details' => mb_substr($e->getMessage(), 0, 100),
            ];
        }
    }

    private function verifyCurrency(): array
    {
        try {
            $rate = $this->currencyRateService->getRate('USD', 'EUR');
            $isOk = $rate > 0;

            return [
                'status' => $isOk ? 'success' : 'warning',
                'message' => $isOk
                    ? '✓ Conversion de devises OK'
                    : 'Conversion indisponible',
                'details' => $isOk
                    ? sprintf('Taux USD→EUR temps réel: %.4f - mis à jour à %s', $rate, (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
                    : 'Service non disponible',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur conversion',
                'details' => mb_substr($e->getMessage(), 0, 100),
            ];
        }
    }

    private function verifyEmail(): array
    {
        try {
            $result = $this->brevoEmailService->verifyConnection();

            return [
                'status' => $result['ok'] ? 'success' : 'warning',
                'message' => $result['ok']
                    ? '✓ Email confirmations OK'
                    : 'Email indisponible',
                'details' => $result['ok']
                    ? 'Brevo prêt à envoyer les confirmations'
                    : 'Service non disponible',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur email',
                'details' => mb_substr($e->getMessage(), 0, 100),
            ];
        }
    }

    #[Route('/export/excel', name: 'front_transaction_export_excel', methods: ['GET'])]
    public function exportExcel(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $filters = $this->buildTransactionFilters($request);
        $transactions = $this->fetchFilteredTransactions($entityManager, $user, $filters);
        $filename = sprintf('fintrack-transactions-%s.csv', (new \DateTimeImmutable())->format('Ymd-His'));

        $response = new StreamedResponse(function () use ($transactions): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Date', 'Type', 'Statut', 'Montant', 'Devise', 'Description', 'Carte Source', 'Carte Destination'], ';');

            foreach ($transactions as $trx) {
                fputcsv($output, [
                    (string) ($trx['date'] ?? ''),
                    (string) ($trx['type'] ?? ''),
                    (string) ($trx['statut'] ?? ''),
                    (string) ($trx['montant'] ?? ''),
                    (string) ($trx['devise'] ?? ''),
                    (string) ($trx['description'] ?? ''),
                    (string) ($trx['source_numero'] ?? ''),
                    (string) ($trx['dest_numero'] ?? ''),
                ], ';');
            }

            fclose($output);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    #[Route('/export/pdf', name: 'front_transaction_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $filters = $this->buildTransactionFilters($request);
        $transactions = $this->fetchFilteredTransactions($entityManager, $user, $filters);

        $html = $this->renderView('frontoffice/transaction/export_pdf.html.twig', [
            'user' => $user,
            'transactions' => $transactions,
            'filters' => $filters,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('fintrack-transactions-%s.pdf', (new \DateTimeImmutable())->format('Ymd-His'));

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    #[Route('/new', name: 'front_transaction_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->redirectToRoute('front_transaction_transfert');
    }

    #[Route('/transfert', name: 'front_transaction_transfert', methods: ['GET', 'POST'])]
    public function transfert(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleTransactionForm($request, $entityManager, 'TRANSFERT');
    }

    private function handleTransactionForm(Request $request, EntityManagerInterface $entityManager, string $defaultType): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $cardRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_carte, p.nom AS portefeuille_nom
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.user_id = :uid
             ORDER BY c.id DESC',
            ['uid' => $user->getId()]
        );

        if ($cardRows === []) {
            $this->addFlash('warning', 'Vous devez créer au moins une carte pour effectuer une transaction.');

            return $this->redirectToRoute('front_carte_new');
        }

        $cardIds = array_map(static fn (array $row): int => (int) $row['id'], $cardRows);
        $cardEntities = empty($cardIds)
            ? []
            : $entityManager->getRepository(CarteVirtuelle::class)->findBy(['id' => $cardIds]);

        $cardsById = [];
        foreach ($cardEntities as $entity) {
            if ($entity instanceof CarteVirtuelle) {
                $cardsById[$entity->getId()] = $entity;
            }
        }

        $cards = [];
        foreach ($cardRows as $row) {
            $cardId = (int) $row['id'];
            if (!isset($cardsById[$cardId])) {
                continue;
            }

            $cards['**** ' . substr((string) $row['numero_carte'], -4) . ' - ' . (string) $row['portefeuille_nom']] = $cardId;
        }

        // Get external portfolios and cards for destination selection
        $externalCardsRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_carte, c.devise, 
                    p.nom AS portefeuille_nom, p.id AS portefeuille_id
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.user_id != :uid AND c.is_active = 1
             ORDER BY p.nom, c.numero_carte',
            ['uid' => $user->getId()]
        );

        $externalCards = [];
        $externalCardMap = [];
        foreach ($externalCardsRows as $row) {
            $cardId = (int) $row['id'];
            $portfolioId = (int) $row['portefeuille_id'];
            $displayName = $row['portefeuille_nom'] . ' - **** ' . substr((string) $row['numero_carte'], -4);
            $externalCards[$displayName] = $cardId;
            $externalCardMap[$cardId] = ['portfolio_id' => $portfolioId, 'carte_id' => $cardId];
        }

        $form = $this->createFormBuilder([
            'type' => $defaultType,
            'carte_source' => null,
            'carte_dest' => null,
            'carte_dest_externe' => null,
            'montant' => null,
            'devise' => 'TND',
            'description' => null,
        ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Transfert' => 'TRANSFERT',
                ],
            ])
            ->add('carte_source', ChoiceType::class, [
                'choices' => $cards,
                'required' => false,
                'placeholder' => 'Choisir une carte source',
            ])
            ->add('carte_dest', ChoiceType::class, [
                'choices' => $cards,
                'required' => false,
                'placeholder' => 'Choisir une carte destination',
            ])
            ->add('carte_dest_externe', ChoiceType::class, [
                'choices' => $externalCards,
                'required' => false,
                'placeholder' => 'Choisir une carte externe',
            ])
            ->add('montant', MoneyType::class, [
                'currency' => false,
            ])
            ->add('devise', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $validationErrors = $this->validateTransactionPayload($data);
            if ($validationErrors !== []) {
                foreach ($validationErrors as $error) {
                    $this->addFlash('danger', $error);
                }

                return $this->redirectToRoute($this->routeNameForType($defaultType));
            }

            $type = (string) $data['type'];
            $montant = (float) $data['montant'];

            if ($montant <= 0) {
                $this->addFlash('danger', 'Le montant doit être supérieur à 0.');

                return $this->redirectToRoute($this->routeNameForType($defaultType));
            }

            $sourceCard = isset($data['carte_source']) ? ($cardsById[(int) $data['carte_source']] ?? null) : null;
            $destCard = isset($data['carte_dest']) ? ($cardsById[(int) $data['carte_dest']] ?? null) : null;
            $destWalletExternalId = null;
            $destCardExternalId = null;

            // Handle external card selection
            if (isset($data['carte_dest_externe']) && !empty($data['carte_dest_externe'])) {
                $externalCardId = (int) $data['carte_dest_externe'];
                if (isset($externalCardMap[$externalCardId])) {
                    $destWalletExternalId = $externalCardMap[$externalCardId]['portfolio_id'];
                    $destCardExternalId = $externalCardMap[$externalCardId]['carte_id'];
                }
            }
            
            $transferFeeData = null;
            $geo = null;

            if ($type === 'TRANSFERT') {
                if (!$sourceCard instanceof CarteVirtuelle) {
                    $this->addFlash('danger', 'La carte source est obligatoire pour un transfert.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                if (!$destCard instanceof CarteVirtuelle && $destWalletExternalId > 0 && $destCardExternalId > 0) {
                    $externalDestCard = $entityManager->getRepository(CarteVirtuelle::class)->find($destCardExternalId);
                    if (!$externalDestCard instanceof CarteVirtuelle) {
                        $this->addFlash('danger', 'Carte destination externe introuvable.');

                        return $this->redirectToRoute($this->routeNameForType($defaultType));
                    }

                    $externalWallet = $externalDestCard->getPortefeuille();
                    if (!$externalWallet || $externalWallet->getId() !== $destWalletExternalId) {
                        $this->addFlash('danger', 'La carte destination ne correspond pas au portefeuille fourni.');

                        return $this->redirectToRoute($this->routeNameForType($defaultType));
                    }

                    $destCard = $externalDestCard;
                }

                if (!$destCard instanceof CarteVirtuelle) {
                    $this->addFlash('danger', 'Carte destination manquante. Sélectionnez une carte locale ou renseignez portefeuille/carte externe.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                if ($sourceCard->getId() === $destCard->getId()) {
                    $this->addFlash('danger', 'La carte source et la carte destination doivent être différentes.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $clientIp = $this->resolveClientIp($request);
                $mustEnforceLocationCheck = !$this->isLocalOrPrivateIp($clientIp);

                if ($mustEnforceLocationCheck) {
                    $profileCity = $this->getClientCity($entityManager, (int) $user->getId());
                    if ($profileCity === null || trim($profileCity) === '') {
                        $this->addFlash('danger', 'Ville du profil client manquante. Renseignez clients.city avant de transférer.');

                        return $this->redirectToRoute($this->routeNameForType($defaultType));
                    }

                    $geo = $this->geoLocateService->locate($clientIp);
                    $detectedCity = (string) ($geo['city'] ?? '');

                    if ($detectedCity === '') {
                        $this->addFlash('danger', 'Vérification de localisation impossible: ville détectée indisponible.');

                        return $this->redirectToRoute($this->routeNameForType($defaultType));
                    }

                    if (!$this->isSameCity($profileCity, $detectedCity)) {
                        $this->addFlash('danger', sprintf(
                            'Transfert refusé: ville profil (%s) différente de la ville détectée (%s).',
                            $profileCity,
                            $detectedCity
                        ));

                        return $this->redirectToRoute($this->routeNameForType($defaultType));
                    }
                } else {
                    $geo = $this->geoLocateService->locate($clientIp);
                }

                try {
                    $sourceDebitAmount = $this->currencyRateService->convert(
                        $montant,
                        (string) $data['devise'],
                        (string) $sourceCard->getDevise()
                    );

                    $destCreditAmount = $this->currencyRateService->convert(
                        $montant,
                        (string) $data['devise'],
                        (string) $destCard->getDevise()
                    );
                } catch (\RuntimeException $e) {
                    $this->addFlash('danger', 'Conversion devise indisponible pour le moment. Réessayez plus tard.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $feeData = $this->transferFeeService->calculateFeeForSourceCard((int) $sourceCard->getId(), $sourceDebitAmount);
                $transferFeeData = $feeData;
                $feeAmount = (float) $feeData['feeAmount'];
                $totalSourceDebit = $sourceDebitAmount + $feeAmount;

                $sourceBalance = (float) $sourceCard->getSolde();
                if ($sourceBalance < $totalSourceDebit) {
                    $this->addFlash('danger', 'Solde insuffisant sur la carte source.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $sourceCard->setSolde(number_format($sourceBalance - $totalSourceDebit, 2, '.', ''));
                $destCard->setSolde(number_format((float) $destCard->getSolde() + $destCreditAmount, 2, '.', ''));

                $this->addFlash('info', sprintf(
                    'Conversion appliquée: %.2f %s + %.2f frais (%.0f%%), %.2f %s crédités.',
                    $sourceDebitAmount,
                    (string) $sourceCard->getDevise(),
                    $feeAmount,
                    ((float) $feeData['appliedRate']) * 100,
                    $destCreditAmount,
                    (string) $destCard->getDevise()
                ));
            }

            $transaction = new Transaction();
            $transaction->setType($type);
            $transaction->setMontant(number_format($montant, 2, '.', ''));
            $transaction->setDevise((string) $data['devise']);
            $transaction->setDescription($data['description']);
            $transaction->setStatut('SUCCESS');
            $transaction->setCarteSource($sourceCard);
            $transaction->setCarteDest($destCard);

            $entityManager->persist($transaction);
            $entityManager->flush();

            if ($type === 'TRANSFERT') {
                if (!is_array($geo)) {
                    $geo = $this->geoLocateService->locate($this->resolveClientIp($request));
                }
                $entityManager->getConnection()->insert('transfer_risk_log', [
                    'transaction_id' => $transaction->getId(),
                    'virement_programme_id' => null,
                    'transfer_kind' => 'NORMAL',
                    'ip_address' => $this->resolveClientIp($request),
                    'country_code' => $geo['country_code'],
                    'country_name' => $geo['country_name'],
                    'city' => $geo['city'],
                    'latitude' => $geo['latitude'],
                    'longitude' => $geo['longitude'],
                    'risk_score' => $geo['risk_score'],
                    'decision' => $geo['decision'],
                    'reason' => null,
                    'provider' => $geo['provider'],
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                $feeSnapshot = $transferFeeData ?? [
                    'baseRate' => 0.02,
                    'appliedRate' => 0.02,
                    'feeAmount' => 0.0,
                    'previousTransfers' => 0,
                ];

                $entityManager->getConnection()->insert('transfer_fee_event', [
                    'transaction_id' => $transaction->getId(),
                    'virement_programme_id' => null,
                    'source_card_id' => (int) ($sourceCard?->getId() ?? 0),
                    'dest_card_id' => (int) ($destCard?->getId() ?? 0),
                    'amount' => number_format($montant, 2, '.', ''),
                    'currency' => (string) $data['devise'],
                    'base_fee_rate' => (float) $feeSnapshot['baseRate'],
                    'applied_fee_rate' => (float) $feeSnapshot['appliedRate'],
                    'fixed_fee' => '0.00',
                    'fee_amount' => number_format((float) $feeSnapshot['feeAmount'], 2, '.', ''),
                    'transfer_count_in_window' => (int) $feeSnapshot['previousTransfers'],
                    'window_days' => 0,
                    'rule_name' => '2pct_then_1pct_after_5',
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);

                try {
                    $this->brevoEmailService->sendNormalTransferConfirmation([
                        'transaction_id' => $transaction->getId(),
                        'amount' => number_format($montant, 2, '.', ''),
                        'currency' => (string) $data['devise'],
                        'source_card' => (string) ($sourceCard?->getNumeroCarte() ?? ''),
                        'dest_card' => (string) ($destCard?->getNumeroCarte() ?? ''),
                        'executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ], (string) $user->getEmail());
                    $this->logger->info('Transfer confirmation email sent', [
                        'transaction_id' => $transaction->getId(),
                        'recipient' => $user->getEmail(),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Email sending failed', [
                        'transaction_id' => $transaction->getId(),
                        'recipient' => $user->getEmail(),
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]);
                    $this->addFlash('warning', 'Transfert valide, mais envoi email indisponible: ' . mb_substr($e->getMessage(), 0, 100));
                }
            }

            $this->addFlash('success', 'Transaction enregistrée.');

            return $this->redirectToRoute('front_transaction_index');
        }

        return $this->render('frontoffice/transaction/new.html.twig', [
            'form' => $form,
        ]);
    }

    private function getClientCity(EntityManagerInterface $entityManager, int $userId): ?string
    {
        try {
            $city = $entityManager->getConnection()->fetchOne(
                'SELECT city FROM clients WHERE user_id = :uid',
                ['uid' => $userId]
            );
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($city)) {
            return null;
        }

        $city = trim($city);

        return $city === '' ? null : $city;
    }

    private function isSameCity(string $expectedCity, string $detectedCity): bool
    {
        return $this->normalizeCity($expectedCity) === $this->normalizeCity($detectedCity);
    }

    private function normalizeCity(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function resolveClientIp(Request $request): ?string
    {
        $forwardedFor = $request->headers->get('X-Forwarded-For', '');
        if (is_string($forwardedFor) && trim($forwardedFor) !== '') {
            $parts = array_map('trim', explode(',', $forwardedFor));
            foreach ($parts as $ip) {
                if ($this->isPublicIp($ip)) {
                    return $ip;
                }
            }
        }

        $realIp = $request->headers->get('X-Real-IP', '');
        if (is_string($realIp) && $this->isPublicIp(trim($realIp))) {
            return trim($realIp);
        }

        $clientIp = $request->getClientIp();

        return is_string($clientIp) && trim($clientIp) !== '' ? trim($clientIp) : null;
    }

    private function isPublicIp(?string $ip): bool
    {
        if ($ip === null || trim($ip) === '') {
            return false;
        }

        return filter_var(
            trim($ip),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function isLocalOrPrivateIp(?string $ip): bool
    {
        if ($ip === null || trim($ip) === '') {
            return true;
        }

        return !$this->isPublicIp($ip);
    }

    private function routeNameForType(string $type): string
    {
        return match ($type) {
            'DEPOT' => 'front_transaction_depot',
            'RETRAIT' => 'front_transaction_retrait',
            default => 'front_transaction_transfert',
        };
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<int,string>
     */
    private function validateTransactionPayload(array $data): array
    {
        $errors = [];

        $type = strtoupper(trim((string) ($data['type'] ?? '')));
        $allowedTypes = ['TRANSFERT'];
        if (!in_array($type, $allowedTypes, true)) {
            $errors[] = 'Type de transaction invalide.';
        }

        $devise = strtoupper(trim((string) ($data['devise'] ?? '')));
        if (!in_array($devise, ['TND', 'EUR', 'USD'], true)) {
            $errors[] = 'Devise invalide.';
        }

        if (!isset($data['montant']) || !is_numeric((string) $data['montant']) || (float) $data['montant'] <= 0) {
            $errors[] = 'Le montant doit etre un nombre superieur a 0.';
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            $errors[] = 'La description ne doit pas depasser 1000 caracteres.';
        }

        $sourceCardId = isset($data['carte_source']) ? (int) $data['carte_source'] : 0;
        $destCardId = isset($data['carte_dest']) ? (int) $data['carte_dest'] : 0;
        $externalCardId = isset($data['carte_dest_externe']) ? (int) $data['carte_dest_externe'] : 0;

        $hasInternalDest = $destCardId > 0;
        $hasExternalDest = $externalCardId > 0;

        if ($type === 'TRANSFERT') {
            if ($sourceCardId <= 0) {
                $errors[] = 'Carte source obligatoire.';
            }

            if (!$hasInternalDest && !$hasExternalDest) {
                $errors[] = 'Une carte de destination est obligatoire (interne ou externe).';
            }

            if ($hasInternalDest && $hasExternalDest) {
                $errors[] = 'Veuillez choisir soit une carte interne, soit une carte externe, pas les deux.';
            }
        }

        return $errors;
    }

    /**
     * @return array{type:string,date_start:string,date_end:string,q:string,sort:string,direction:string}
     */
    private function buildTransactionFilters(Request $request): array
    {
        $sort = strtolower(trim((string) $request->query->get('sort', 'date')));
        $direction = strtolower(trim((string) $request->query->get('direction', 'desc')));

        return [
            'type' => strtoupper(trim((string) $request->query->get('type', ''))),
            'date_start' => trim((string) $request->query->get('date_start', '')),
            'date_end' => trim((string) $request->query->get('date_end', '')),
            'q' => trim((string) $request->query->get('q', '')),
            'sort' => in_array($sort, ['date', 'montant', 'type', 'statut'], true) ? $sort : 'date',
            'direction' => $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @param array{type:string,date_start:string,date_end:string,q:string,sort:string,direction:string} $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFilteredTransactions(EntityManagerInterface $entityManager, User $user, array $filters): array
    {
        $sql = 'SELECT t.id, t.date, t.type, t.statut, t.montant, t.devise, t.description,
                       cs.numero_carte AS source_numero,
                       cd.numero_carte AS dest_numero
                FROM transaction t
                LEFT JOIN carte_virtuelle cs ON cs.id = t.carte_source_id
                LEFT JOIN carte_virtuelle cd ON cd.id = t.carte_dest_id
                WHERE (
                    (cs.id IS NOT NULL AND cs.portefeuille_id IN (SELECT id FROM portefeuille WHERE user_id = :uid))
                    OR (cd.id IS NOT NULL AND cd.portefeuille_id IN (SELECT id FROM portefeuille WHERE user_id = :uid))
                )';

        $params = ['uid' => $user->getId()];

        if ($filters['type'] !== '') {
            $sql .= ' AND t.type = :type';
            $params['type'] = $filters['type'];
        }

        if ($filters['date_start'] !== '') {
            $sql .= ' AND t.date >= :date_start';
            $params['date_start'] = $filters['date_start'] . ' 00:00:00';
        }

        if ($filters['date_end'] !== '') {
            $sql .= ' AND t.date <= :date_end';
            $params['date_end'] = $filters['date_end'] . ' 23:59:59';
        }

        if ($filters['q'] !== '') {
            $sql .= ' AND (
                t.description LIKE :q
                OR t.type LIKE :q
                OR t.statut LIKE :q
                OR t.devise LIKE :q
                OR cs.numero_carte LIKE :q
                OR cd.numero_carte LIKE :q
            )';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sortMap = [
            'date' => 't.date',
            'montant' => 't.montant',
            'type' => 't.type',
            'statut' => 't.statut',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? 't.date';
        $direction = strtoupper($filters['direction']) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= sprintf(' ORDER BY %s %s, t.id DESC', $sortColumn, $direction);

        return $entityManager->getConnection()->fetchAllAssociative($sql, $params);
    }
}
