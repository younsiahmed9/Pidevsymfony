<?php

namespace App\Controller\FrontOffice;

use App\Entity\CarteVirtuelle;
use App\Entity\Portefeuille;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/carte')]
final class CarteController extends AbstractController
{
    #[Route('/', name: 'front_carte_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $cartes = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_carte, c.type, c.devise, c.solde, c.plafond, c.is_active
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.user_id = :uid
             ORDER BY c.id DESC',
            ['uid' => $user->getId()]
        );

        return $this->render('frontoffice/carte/index.html.twig', [
            'cartes' => $cartes,
        ]);
    }

    #[Route('/new', name: 'front_carte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuilleRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom FROM portefeuille WHERE user_id = :uid ORDER BY nom ASC',
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

        $selectedPortefeuilleId = $request->query->has('portefeuille_id')
            ? (int) $request->query->get('portefeuille_id')
            : null;

        $form = $this->createFormBuilder([
            'portefeuille' => $selectedPortefeuilleId,
            'type' => null,
            'devise' => null,
            'plafond' => null,
        ])
            ->add('portefeuille', ChoiceType::class, [
                'choices' => $portefeuilleChoices,
                'placeholder' => 'Choisir un portefeuille',
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'NORMAL' => 'NORMAL',
                    'SILVER' => 'SILVER',
                    'GOLD' => 'GOLD',
                ],
                'placeholder' => 'Choisir un type',
                'required' => true,
            ])
            ->add('devise', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
                'placeholder' => 'Choisir une devise',
                'required' => true,
            ])
            ->add('plafond', NumberType::class, [
                'required' => true,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 1000',
                ],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $carte = $this->hydrateCarteFromData($form->getData(), $user, $entityManager, $portefeuilleChoices, null);
            $violations = $validator->validate($carte);

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
                $entityManager->getConnection()->insert('carte_virtuelle', [
                    'numero_carte' => $carte->getNumeroCarte(),
                    'cvv' => $carte->getCvv(),
                    'date_expiration' => $carte->getDateExpiration()?->format('Y-m-d'),
                    'solde' => $carte->getSolde(),
                    'plafond' => $carte->getPlafond(),
                    'type' => $carte->getType(),
                    'devise' => $carte->getDevise(),
                    'portefeuille_id' => $carte->getPortefeuille()?->getId(),
                    'is_active' => $carte->isIsActive() ? 1 : 0,
                    'created_at' => $carte->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $carte->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ]);

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => true,
                        'redirect' => $selectedPortefeuilleId > 0
                            ? $this->generateUrl('front_portefeuille_show', ['id' => $selectedPortefeuilleId])
                            : $this->generateUrl('front_carte_index'),
                    ]);
                }

                $this->addFlash('success', 'Carte créée avec succès.');
                return $selectedPortefeuilleId > 0
                    ? $this->redirectToRoute('front_portefeuille_show', ['id' => $selectedPortefeuilleId])
                    : $this->redirectToRoute('front_carte_index');
            }
        }

        return $this->render('frontoffice/carte/new.html.twig', [
            'form' => $form,
            'carteId' => null,
            'selectedPortefeuilleId' => $selectedPortefeuilleId,
            'isEditMode' => false,
        ]);
    }

    #[Route('/validate', name: 'front_carte_validate', methods: ['POST'])]
    public function validate(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['valid' => false, 'errors' => ['general' => ['Vous devez être connecté.']]], 401);
        }

        $portefeuilleChoices = $this->getPortefeuilleChoices($entityManager, $user);
        $existingCarte = null;
        $carteId = (int) $request->request->get('carte_id', 0);

        if ($carteId > 0) {
            $existingCarte = $entityManager->getConnection()->fetchAssociative(
                'SELECT c.id, c.numero_carte, c.cvv, c.date_expiration, c.solde, c.plafond, c.type, c.devise, c.is_active, c.portefeuille_id, c.created_at, c.updated_at
                 FROM carte_virtuelle c
                 INNER JOIN portefeuille p ON p.id = c.portefeuille_id
                 WHERE c.id = :id AND p.user_id = :uid',
                ['id' => $carteId, 'uid' => $user->getId()]
            );
        }

        $carte = $this->hydrateCarteFromData($this->extractSubmittedCarteData($request), $user, $entityManager, $portefeuilleChoices, $existingCarte);
        $violations = $validator->validate($carte);

        return $this->json([
            'valid' => count($violations) === 0,
            'errors' => $this->normalizeViolations($violations),
        ]);
    }

    #[Route('/{id}/edit', name: 'front_carte_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $carte = $entityManager->getConnection()->fetchAssociative(
            'SELECT c.id, c.numero_carte, c.cvv, c.date_expiration, c.solde, c.type, c.devise, c.plafond, c.is_active, c.portefeuille_id, c.created_at, c.updated_at
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE c.id = :id AND p.user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$carte) {
            throw $this->createNotFoundException('Carte introuvable.');
        }

        $portefeuilleRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom FROM portefeuille WHERE user_id = :uid ORDER BY nom ASC',
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

        if ($form->isSubmitted()) {
            $validatedCarte = $this->hydrateCarteFromData($form->getData(), $user, $entityManager, $portefeuilleChoices, $carte);
            $violations = $validator->validate($validatedCarte);

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
                $data = $form->getData();
                $portefeuilleId = (int) $data['portefeuille'];

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

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'valid' => true,
                        'redirect' => $this->generateUrl('front_carte_index'),
                    ]);
                }

                $this->addFlash('success', 'Carte mise à jour.');
                return $this->redirectToRoute('front_carte_index');
            }
        }

        return $this->render('frontoffice/carte/new.html.twig', [
            'form' => $form,
            'carteId' => $id,
            'selectedPortefeuilleId' => (int) $carte['portefeuille_id'],
            'isEditMode' => true,
        ]);
    }

    #[Route('/{id}', name: 'front_carte_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $carte = $entityManager->getConnection()->fetchAssociative(
            'SELECT c.id, c.numero_carte, c.cvv, c.date_expiration, c.solde, c.plafond, c.type, c.devise, c.is_active,
                    p.id AS portefeuille_id, p.nom AS portefeuille_nom
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE c.id = :id AND p.user_id = :uid',
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
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('toggle' . $id, $request->getPayload()->getString('_token'))) {
            $carte = $entityManager->getConnection()->fetchAssociative(
                'SELECT c.id, c.is_active, c.portefeuille_id
                 FROM carte_virtuelle c
                 INNER JOIN portefeuille p ON p.id = c.portefeuille_id
                 WHERE c.id = :id AND p.user_id = :uid',
                ['id' => $id, 'uid' => $user->getId()]
            );

            if ($carte) {
                $entityManager->getConnection()->update('carte_virtuelle', [
                    'is_active' => ((int) $carte['is_active']) ? 0 : 1,
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $id]);

                return $this->redirectToRoute('front_portefeuille_show', ['id' => (int) $carte['portefeuille_id']]);
            }
        }

        return $this->redirectToRoute('front_carte_index');
    }

    #[Route('/{id}', name: 'front_carte_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('front_carte_index');
        }

        $connection = $entityManager->getConnection();
        $carte = $connection->fetchAssociative(
            'SELECT c.id, c.portefeuille_id
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE c.id = :id AND p.user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$carte) {
            $this->addFlash('warning', 'Carte introuvable.');

            return $this->redirectToRoute('front_carte_index');
        }

        $transactionLinks = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM transaction WHERE carte_source_id = :id OR carte_dest_id = :id',
            ['id' => $id]
        );

        $scheduledLinks = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM virement_programme WHERE carte_source_id = :id OR carte_dest_id = :id',
            ['id' => $id]
        );

        if ($transactionLinks > 0 || $scheduledLinks > 0) {
            $this->addFlash('warning', 'Impossible de supprimer cette carte car elle est liée à des transactions ou des virements programmés.');

            return $this->redirectToRoute('front_portefeuille_show', ['id' => (int) $carte['portefeuille_id']]);
        }

        $connection->executeStatement(
            'DELETE FROM carte_virtuelle WHERE id = :id',
            ['id' => $id]
        );

        $this->addFlash('success', 'Carte supprimée avec succès.');

        return $this->redirectToRoute('front_portefeuille_show', ['id' => (int) $carte['portefeuille_id']]);
    }

    private function generateCardNumber(): string
    {
        $parts = [];

        for ($i = 0; $i < 4; ++$i) {
            $parts[] = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        return implode('', $parts);
    }

    private function getPortefeuilleChoices(EntityManagerInterface $entityManager, User $user): array
    {
        $portefeuilleRows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom FROM portefeuille WHERE user_id = :uid ORDER BY nom ASC',
            ['uid' => $user->getId()]
        );

        $portefeuilleChoices = [];
        foreach ($portefeuilleRows as $row) {
            $portefeuilleChoices[(string) $row['nom']] = (int) $row['id'];
        }

        return $portefeuilleChoices;
    }

    private function hydrateCarteFromData(array $data, User $user, EntityManagerInterface $entityManager, array $portefeuilleChoices, ?array $existingCarte = null): CarteVirtuelle
    {
        $carte = new CarteVirtuelle();

        $portefeuilleId = isset($data['portefeuille']) && $data['portefeuille'] !== '' ? (int) $data['portefeuille'] : null;
        $selectedPortefeuilleId = $portefeuilleId && in_array($portefeuilleId, array_values($portefeuilleChoices), true) ? $portefeuilleId : null;

        $carte->setNumeroCarte((string) ($existingCarte['numero_carte'] ?? $this->generateCardNumber()));
        $carte->setCvv((string) ($existingCarte['cvv'] ?? random_int(100, 999)));
        $carte->setDateExpiration(isset($existingCarte['date_expiration']) && $existingCarte['date_expiration'] ? new \DateTimeImmutable((string) $existingCarte['date_expiration']) : new \DateTimeImmutable('+3 years'));
        $carte->setSolde((string) ($existingCarte['solde'] ?? '0.00'));
        $carte->setPlafond(trim((string) ($data['plafond'] ?? ($existingCarte['plafond'] ?? ''))));
        $carte->setType(trim((string) ($data['type'] ?? ($existingCarte['type'] ?? ''))));
        $carte->setDevise(trim((string) ($data['devise'] ?? ($existingCarte['devise'] ?? ''))));
        $carte->setPortefeuille($selectedPortefeuilleId ? $entityManager->getReference(Portefeuille::class, $selectedPortefeuilleId) : null);
        $carte->setIsActive(isset($data['is_active']) ? (bool) $data['is_active'] : (bool) ($existingCarte['is_active'] ?? true));
        $carte->setCreatedAt(isset($existingCarte['created_at']) && $existingCarte['created_at'] ? new \DateTimeImmutable((string) $existingCarte['created_at']) : new \DateTimeImmutable());
        $carte->setUpdatedAt(new \DateTimeImmutable());

        return $carte;
    }

    private function extractSubmittedCarteData(Request $request): array
    {
        $submittedData = $request->request->all();

        if ($submittedData === []) {
            return [];
        }

        $firstValue = reset($submittedData);
        if (is_array($firstValue) && isset($firstValue['portefeuille'])) {
            return $firstValue;
        }

        if (isset($submittedData['form']) && is_array($submittedData['form'])) {
            return $submittedData['form'];
        }

        return $submittedData;
    }

    private function normalizeViolations(iterable $violations): array
    {
        $errors = [];
        $fieldMap = [
            'numero_carte' => 'numero_carte',
            'portefeuille' => 'portefeuille',
            'type' => 'type',
            'devise' => 'devise',
            'plafond' => 'plafond',
        ];

        foreach ($violations as $violation) {
            $rawPath = $violation->getPropertyPath();
            $propertyPath = $fieldMap[$rawPath] ?? ($rawPath !== '' ? $rawPath : 'general');
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return $errors;
    }
}
