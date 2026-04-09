<?php

namespace App\Controller\FrontOffice;

use App\Entity\CarteVirtuelle;
use App\Entity\Transaction;
use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/transaction')]
final class TransactionController extends AbstractController
{
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

    #[Route('/depot', name: 'front_transaction_depot', methods: ['GET', 'POST'])]
    public function depot(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleTransactionForm($request, $entityManager, 'DEPOT');
    }

    #[Route('/retrait', name: 'front_transaction_retrait', methods: ['GET', 'POST'])]
    public function retrait(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleTransactionForm($request, $entityManager, 'RETRAIT');
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

        $form = $this->createFormBuilder([
            'type' => $defaultType,
            'carte_source' => null,
            'carte_dest' => null,
            'montant' => null,
            'devise' => 'TND',
            'description' => null,
        ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Dépôt' => 'DEPOT',
                    'Retrait' => 'RETRAIT',
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

            $type = (string) $data['type'];
            $montant = (float) $data['montant'];

            if ($montant <= 0) {
                $this->addFlash('danger', 'Le montant doit être supérieur à 0.');

                return $this->redirectToRoute($this->routeNameForType($defaultType));
            }

            $sourceCard = isset($data['carte_source']) ? ($cardsById[(int) $data['carte_source']] ?? null) : null;
            $destCard = isset($data['carte_dest']) ? ($cardsById[(int) $data['carte_dest']] ?? null) : null;

            if ($type === 'DEPOT') {
                if (!$destCard instanceof CarteVirtuelle) {
                    $this->addFlash('danger', 'Une carte destination est obligatoire pour un dépôt.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $destCard->setSolde(number_format((float) $destCard->getSolde() + $montant, 2, '.', ''));
            }

            if ($type === 'RETRAIT') {
                if (!$sourceCard instanceof CarteVirtuelle) {
                    $this->addFlash('danger', 'Une carte source est obligatoire pour un retrait.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $sourceBalance = (float) $sourceCard->getSolde();
                if ($sourceBalance < $montant) {
                    $this->addFlash('danger', 'Solde insuffisant sur la carte source.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $sourceCard->setSolde(number_format($sourceBalance - $montant, 2, '.', ''));
            }

            if ($type === 'TRANSFERT') {
                if (!$sourceCard instanceof CarteVirtuelle || !$destCard instanceof CarteVirtuelle) {
                    $this->addFlash('danger', 'Les cartes source et destination sont obligatoires pour un transfert.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                if ($sourceCard->getId() === $destCard->getId()) {
                    $this->addFlash('danger', 'La carte source et la carte destination doivent être différentes.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $sourceBalance = (float) $sourceCard->getSolde();
                if ($sourceBalance < $montant) {
                    $this->addFlash('danger', 'Solde insuffisant sur la carte source.');

                    return $this->redirectToRoute($this->routeNameForType($defaultType));
                }

                $sourceCard->setSolde(number_format($sourceBalance - $montant, 2, '.', ''));
                $destCard->setSolde(number_format((float) $destCard->getSolde() + $montant, 2, '.', ''));
            }

            $transaction = new Transaction();
            $transaction->setType($type);
            $transaction->setMontant(number_format($montant, 2, '.', ''));
            $transaction->setDevise((string) $data['devise']);
            $transaction->setDescription($data['description']);
            $transaction->setStatut('COMPLETED');
            $transaction->setCarteSource($sourceCard);
            $transaction->setCarteDest($destCard);

            $entityManager->persist($transaction);
            $entityManager->flush();

            $this->addFlash('success', 'Transaction enregistrée.');

            return $this->redirectToRoute('front_transaction_index');
        }

        return $this->render('frontoffice/transaction/new.html.twig', [
            'form' => $form,
        ]);
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
