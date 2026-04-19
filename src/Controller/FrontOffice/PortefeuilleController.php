<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\Transfer\GeoLocateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

#[Route('/portefeuille')]
final class PortefeuilleController extends AbstractController
{
    #[Route('/', name: 'front_portefeuille_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager, \App\Service\Transfer\CurrencyRateService $currencyRateService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuilles = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom, devise_principale, solde_total FROM portefeuille WHERE user_id = :uid ORDER BY id DESC',
            ['uid' => $user->getId()]
        );

        // Recalculate solde_total dynamically based on cards
        foreach ($portefeuilles as $key => $p) {
            $total = 0.0;
            $walletId = (int) $p['id'];
            $mainCurrency = (string) ($p['devise_principale'] ?? 'TND');

            $cards = $entityManager->getConnection()->fetchAllAssociative(
                'SELECT solde, devise FROM carte_virtuelle WHERE portefeuille_id = :pid',
                ['pid' => $walletId]
            );

            foreach ($cards as $card) {
                $cardSolde = (float) ($card['solde'] ?? 0);
                $cardDevise = (string) ($card['devise'] ?? 'TND');

                try {
                    $total += $currencyRateService->convert($cardSolde, $cardDevise, $mainCurrency);
                } catch (\Throwable $e) {
                    // Fallback if conversion fails
                    if ($cardDevise === $mainCurrency) {
                        $total += $cardSolde;
                    }
                }
            }
            
            $formattedTotal = number_format($total, 2, '.', '');
            $portefeuilles[$key]['solde_total'] = $formattedTotal;

            // Sync with database to keep it updated
            $entityManager->getConnection()->update(
                'portefeuille', 
                ['solde_total' => $formattedTotal, 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], 
                ['id' => $walletId]
            );
        }

        return $this->render('frontoffice/portefeuille/index.html.twig', [
            'portefeuilles' => $portefeuilles,
        ]);
    }

    #[Route('/new', name: 'front_portefeuille_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder([
            'nom' => '',
            'devise_principale' => 'TND',
        ])
            ->add('nom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du portefeuille ne peut pas être vide.']),
                    new Length(['min' => 1, 'max' => 100, 'minMessage' => 'Le nom doit contenir au moins 1 caractère.', 'maxMessage' => 'Le nom ne peut pas dépasser 100 caractères.']),
                ],
            ])
            ->add('devise_principale', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $entityManager->getConnection()->insert('portefeuille', [
                'nom' => $data['nom'],
                'thumbnail' => null,
                'solde_total' => '0.00',
                'devise_principale' => $data['devise_principale'],
                'user_id' => $user->getId(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return $this->redirectToRoute('front_portefeuille_index');
        }

        return $this->render('frontoffice/portefeuille/new.html.twig', [
            'form' => $form,
            'isEditMode' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_portefeuille_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuille = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, nom, devise_principale FROM portefeuille WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$portefeuille) {
            throw $this->createNotFoundException('Portefeuille introuvable.');
        }

        $form = $this->createFormBuilder([
            'nom' => (string) $portefeuille['nom'],
            'devise_principale' => (string) $portefeuille['devise_principale'],
        ])
            ->add('nom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du portefeuille ne peut pas être vide.']),
                    new Length(['min' => 1, 'max' => 100, 'minMessage' => 'Le nom doit contenir au moins 1 caractère.', 'maxMessage' => 'Le nom ne peut pas dépasser 100 caractères.']),
                ],
            ])
            ->add('devise_principale', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $entityManager->getConnection()->update('portefeuille', [
                'nom' => $data['nom'],
                'devise_principale' => $data['devise_principale'],
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $id,
                'user_id' => $user->getId(),
            ]);

            return $this->redirectToRoute('front_portefeuille_index');
        }

        return $this->render('frontoffice/portefeuille/new.html.twig', [
            'form' => $form,
            'isEditMode' => true,
        ]);
    }

    #[Route('/{id}', name: 'front_portefeuille_show', methods: ['GET'])]
    public function show(int $id, Request $request, EntityManagerInterface $entityManager, GeoLocateService $geoLocateService, \App\Service\Transfer\CurrencyRateService $currencyRateService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuille = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, nom, devise_principale, solde_total FROM portefeuille WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$portefeuille) {
            throw $this->createNotFoundException('Portefeuille introuvable.');
        }

        $cartes = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, numero_carte, type, solde, devise, is_active FROM carte_virtuelle WHERE portefeuille_id = :pid ORDER BY id DESC',
            ['pid' => (int) $portefeuille['id']]
        );

        // Recalculate solde_total dynamically
        $total = 0.0;
        $mainCurrency = (string) ($portefeuille['devise_principale'] ?? 'TND');
        
        foreach ($cartes as $card) {
            $cardSolde = (float) ($card['solde'] ?? 0);
            $cardDevise = (string) ($card['devise'] ?? 'TND');
            
            try {
                $total += $currencyRateService->convert($cardSolde, $cardDevise, $mainCurrency);
            } catch (\Throwable $e) {
                if ($cardDevise === $mainCurrency) {
                    $total += $cardSolde;
                }
            }
        }
        
        $formattedTotal = number_format($total, 2, '.', '');
        $portefeuille['solde_total'] = $formattedTotal;
        
        $entityManager->getConnection()->update(
            'portefeuille', 
            ['solde_total' => $formattedTotal, 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], 
            ['id' => $id]
        );

        $transactions = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT t.id, t.date, t.type, t.statut, t.montant, t.devise, t.description,
                    cs.numero_carte AS source_numero,
                    cd.numero_carte AS dest_numero
             FROM transaction t
             LEFT JOIN carte_virtuelle cs ON cs.id = t.carte_source_id
             LEFT JOIN carte_virtuelle cd ON cd.id = t.carte_dest_id
             WHERE cs.portefeuille_id = :pid OR cd.portefeuille_id = :pid
             ORDER BY t.date DESC
             LIMIT 20',
            ['pid' => (int) $portefeuille['id']]
        );

        try {
            $profileCityRaw = $entityManager->getConnection()->fetchOne(
                'SELECT city FROM clients WHERE user_id = :uid',
                ['uid' => (int) $user->getId()]
            );
        } catch (\Throwable) {
            $profileCityRaw = null;
        }

        $profileCity = is_string($profileCityRaw) ? trim($profileCityRaw) : '';
        $profileCity = $profileCity !== '' ? $profileCity : null;

        $clientIp = $request->getClientIp();
        $location = $geoLocateService->locate($clientIp);

        return $this->render('frontoffice/portefeuille/show.html.twig', [
            'portefeuille' => $portefeuille,
            'cartes' => $cartes,
            'transactions' => $transactions,
            'detectedLocation' => $location,
            'profileCity' => $profileCity,
            'clientIp' => $clientIp,
        ]);
    }

    #[Route('/{id}', name: 'front_portefeuille_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('front_portefeuille_index');
        }

        $connection = $entityManager->getConnection();
        $wallet = $connection->fetchAssociative(
            'SELECT id, nom FROM portefeuille WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$wallet) {
            $this->addFlash('warning', 'Portefeuille introuvable.');

            return $this->redirectToRoute('front_portefeuille_index');
        }

        $cardsCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM carte_virtuelle WHERE portefeuille_id = :id',
            ['id' => $id]
        );

        if ($cardsCount > 0) {
            $this->addFlash('warning', 'Impossible de supprimer ce portefeuille tant qu il contient des cartes.');

            return $this->redirectToRoute('front_portefeuille_show', ['id' => $id]);
        }

        $deleted = $connection->executeStatement(
            'DELETE FROM portefeuille WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if ($deleted > 0) {
            $this->addFlash('success', sprintf('Portefeuille "%s" supprimé avec succès.', (string) $wallet['nom']));
        } else {
            $this->addFlash('warning', 'Aucun portefeuille supprimé.');
        }

        return $this->redirectToRoute('front_portefeuille_index');
    }
}
