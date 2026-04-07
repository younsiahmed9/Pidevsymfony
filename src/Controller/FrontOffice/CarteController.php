<?php

namespace App\Controller\FrontOffice;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carte')]
final class CarteController extends AbstractController
{
    #[Route('/', name: 'front_carte_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $cartes = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_carte, c.type, c.devise, c.solde, c.plafond, c.is_active
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.utilisateur_id = :uid
             ORDER BY c.id DESC',
            ['uid' => $user->getId()]
        );

        return $this->render('frontoffice/carte/index.html.twig', [
            'cartes' => $cartes,
        ]);
    }

    #[Route('/new', name: 'front_carte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuilleRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom FROM portefeuille WHERE utilisateur_id = :uid ORDER BY nom ASC',
            ['uid' => $user->getId()]
        );

        if ($portefeuilleRows === []) {
            $this->addFlash('warning', 'Créez d\'abord un portefeuille pour générer une carte.');

            return $this->redirectToRoute('front_portefeuille_new');
        }

        $portefeuilleChoices = [];
        foreach ($portefeuilleRows as $row) {
            $portefeuilleChoices[(string) $row['nom']] = (int) $row['id'];
        }

        $form = $this->createFormBuilder([
            'portefeuille' => (int) ($request->query->get('portefeuille_id') ?? array_values($portefeuilleChoices)[0]),
            'type' => 'NORMAL',
            'devise' => 'TND',
            'plafond' => 1000,
        ])
            ->add('portefeuille', ChoiceType::class, [
                'choices' => $portefeuilleChoices,
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'NORMAL' => 'NORMAL',
                    'SILVER' => 'SILVER',
                    'GOLD' => 'GOLD',
                ],
            ])
            ->add('devise', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
            ])
            ->add('plafond', NumberType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $portefeuilleId = (int) $data['portefeuille'];

            if (!in_array($portefeuilleId, array_values($portefeuilleChoices), true)) {
                throw $this->createAccessDeniedException();
            }

            $entityManager->getConnection()->insert('carte_virtuelle', [
                'numero_carte' => $this->generateCardNumber(),
                'cvv' => (string) random_int(100, 999),
                'date_expiration' => (new \DateTimeImmutable('+3 years'))->format('Y-m-d'),
                'solde' => '0.00',
                'plafond' => number_format((float) $data['plafond'], 2, '.', ''),
                'type' => $data['type'],
                'devise' => $data['devise'],
                'portefeuille_id' => $portefeuilleId,
                'is_active' => 1,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return $this->redirectToRoute('front_carte_index');
        }

        return $this->render('frontoffice/carte/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_carte_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $carte = $entityManager->getConnection()->fetchAssociative(
            'SELECT c.id, c.type, c.devise, c.plafond, c.is_active, c.portefeuille_id
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE c.id = :id AND p.utilisateur_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$carte) {
            throw $this->createNotFoundException('Carte introuvable.');
        }

        $portefeuilleRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom FROM portefeuille WHERE utilisateur_id = :uid ORDER BY nom ASC',
            ['uid' => $user->getId()]
        );

        $portefeuilleChoices = [];
        foreach ($portefeuilleRows as $row) {
            $portefeuilleChoices[(string) $row['nom']] = (int) $row['id'];
        }

        $form = $this->createFormBuilder([
            'portefeuille' => (int) $carte['portefeuille_id'],
            'type' => (string) $carte['type'],
            'devise' => (string) $carte['devise'],
            'plafond' => (float) $carte['plafond'],
            'is_active' => (bool) $carte['is_active'],
        ])
            ->add('portefeuille', ChoiceType::class, [
                'choices' => $portefeuilleChoices,
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'NORMAL' => 'NORMAL',
                    'SILVER' => 'SILVER',
                    'GOLD' => 'GOLD',
                ],
            ])
            ->add('devise', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
            ])
            ->add('plafond', NumberType::class)
            ->add('is_active', CheckboxType::class, [
                'required' => false,
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $portefeuilleId = (int) $data['portefeuille'];

            if (!in_array($portefeuilleId, array_values($portefeuilleChoices), true)) {
                throw $this->createAccessDeniedException();
            }

            $entityManager->getConnection()->update('carte_virtuelle', [
                'portefeuille_id' => $portefeuilleId,
                'type' => $data['type'],
                'devise' => $data['devise'],
                'plafond' => number_format((float) $data['plafond'], 2, '.', ''),
                'is_active' => $data['is_active'] ? 1 : 0,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $id,
            ]);

            return $this->redirectToRoute('front_carte_index');
        }

        return $this->render('frontoffice/carte/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'front_carte_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $carte = $entityManager->getConnection()->fetchAssociative(
            'SELECT c.id, c.numero_carte, c.cvv, c.date_expiration, c.solde, c.plafond, c.type, c.devise, c.is_active,
                    p.id AS portefeuille_id, p.nom AS portefeuille_nom
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE c.id = :id AND p.utilisateur_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$carte) {
            throw $this->createNotFoundException('Carte introuvable.');
        }

        $transactions = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, date, type, statut, montant, devise, description, carte_source_id
             FROM transaction
             WHERE carte_source_id = :id OR carte_dest_id = :id
             ORDER BY date DESC
             LIMIT 30',
            ['id' => $id]
        );

        return $this->render('frontoffice/carte/show.html.twig', [
            'carte' => $carte,
            'transactions' => $transactions,
        ]);
    }

    #[Route('/{id}/toggle', name: 'front_carte_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('toggle' . $id, $request->getPayload()->getString('_token'))) {
            $carte = $entityManager->getConnection()->fetchAssociative(
                'SELECT c.id, c.is_active
                 FROM carte_virtuelle c
                 INNER JOIN portefeuille p ON p.id = c.portefeuille_id
                 WHERE c.id = :id AND p.utilisateur_id = :uid',
                ['id' => $id, 'uid' => $user->getId()]
            );

            if ($carte) {
                $entityManager->getConnection()->update('carte_virtuelle', [
                    'is_active' => ((int) $carte['is_active']) ? 0 : 1,
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $id]);
            }
        }

        return $this->redirectToRoute('front_carte_index');
    }

    #[Route('/{id}', name: 'front_carte_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE c FROM carte_virtuelle c
                 INNER JOIN portefeuille p ON p.id = c.portefeuille_id
                 WHERE c.id = :id AND p.utilisateur_id = :uid',
                ['id' => $id, 'uid' => $user->getId()]
            );
        }

        return $this->redirectToRoute('front_carte_index');
    }

    private function generateCardNumber(): string
    {
        $parts = [];

        for ($i = 0; $i < 4; ++$i) {
            $parts[] = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        return implode('', $parts);
    }
}
