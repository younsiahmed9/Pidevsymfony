<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\Transfer\BrevoEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/virement')]
final class VirementController extends AbstractController
{
    private const APP_TIMEZONE = 'Africa/Tunis';

    public function __construct(
        private BrevoEmailService $brevoEmailService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'front_virement_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $virements = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, destinataire, montant, devise, frequence, prochaine_execution, actif, description
             FROM virement_programme
             WHERE user_id = :uid
             ORDER BY created_at DESC',
            ['uid' => $user->getId()]
        );

        return $this->render('frontoffice/virement/index.html.twig', [
            'virements' => $virements,
        ]);
    }

    #[Route('/{id}', name: 'front_virement_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $virement = $entityManager->getConnection()->fetchAssociative(
                'SELECT v.id, v.destinataire, v.montant, v.devise, v.frequence, v.prochaine_execution,
                    v.statut, v.actif, v.description, v.error_message, v.created_at,
                    cs.numero_carte AS source_numero,
                    cd.numero_carte AS dest_numero
             FROM virement_programme v
             LEFT JOIN carte_virtuelle cs ON cs.id = v.carte_source_id
             LEFT JOIN carte_virtuelle cd ON cd.id = v.carte_dest_id
             WHERE v.id = :id AND v.user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$virement) {
            throw $this->createNotFoundException('Virement introuvable.');
        }

        return $this->render('frontoffice/virement/show.html.twig', [
            'virement' => $virement,
        ]);
    }

    #[Route('/new', name: 'front_virement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $cardRows = $this->getOwnedCardRows($entityManager, $user);

        if ($cardRows === []) {
            $this->addFlash('warning', 'Vous devez créer au moins une carte pour programmer un virement.');

            return $this->redirectToRoute('front_carte_new');
        }

        $form = $this->buildVirementForm($cardRows);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $validationErrors = $this->validateVirementPayload($data);
            if ($validationErrors !== []) {
                foreach ($validationErrors as $error) {
                    $this->addFlash('danger', $error);
                }

                return $this->redirectToRoute('front_virement_new');
            }

            if (!$this->isCardOwnedByUser($cardRows, (int) $data['carte_source'])) {
                throw $this->createAccessDeniedException();
            }

            if ($data['carte_dest'] && !$this->isCardOwnedByUser($cardRows, (int) $data['carte_dest'])) {
                throw $this->createAccessDeniedException();
            }

            $entityManager->getConnection()->insert('virement_programme', [
                'user_id' => $user->getId(),
                'carte_source_id' => $data['carte_source'],
                'carte_dest_id' => $data['carte_dest'] ?: null,
                'montant' => $data['montant'],
                'devise' => $data['devise'],
                'destinataire' => $data['destinataire'],
                'frequence' => $data['frequence'],
                'prochaine_execution' => $data['prochaine_execution']?->format('Y-m-d H:i:s'),
                'statut' => 'PENDING',
                'attempts' => 0,
                'actif' => 1,
                'description' => $data['description'],
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $scheduledId = (int) $entityManager->getConnection()->lastInsertId();
            $sourceCardLabel = $this->resolveCardLabel($cardRows, (int) $data['carte_source']);
            $destCardLabel = $data['carte_dest'] ? $this->resolveCardLabel($cardRows, (int) $data['carte_dest']) : '-';

            try {
                $this->brevoEmailService->sendProgrammedTransferCreatedConfirmation([
                    'scheduled_id' => $scheduledId,
                    'amount' => number_format((float) $data['montant'], 2, '.', ''),
                    'currency' => (string) $data['devise'],
                    'source_card' => $sourceCardLabel,
                    'dest_card' => $destCardLabel,
                    'next_execution' => $data['prochaine_execution']?->format('Y-m-d H:i:s') ?? '-',
                    'frequency' => (string) $data['frequence'],
                ], (string) $user->getEmail());
                $this->logger->info('Programmed transfer creation email sent', [
                    'scheduled_id' => $scheduledId,
                    'recipient' => $user->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Programmed transfer creation email failed', [
                    'scheduled_id' => $scheduledId,
                    'recipient' => $user->getEmail(),
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
                $this->addFlash('warning', 'Virement créé, mais email indisponible: ' . mb_substr($e->getMessage(), 0, 100));
            }

            $this->addFlash('success', 'Virement programmé avec succès.');

            return $this->redirectToRoute('front_virement_index');
        }

        return $this->render('frontoffice/virement/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_virement_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $virement = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, destinataire, montant, devise, carte_source_id, carte_dest_id, frequence, prochaine_execution, description
             FROM virement_programme
             WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$virement) {
            throw $this->createNotFoundException('Virement introuvable.');
        }

        $cardRows = $this->getOwnedCardRows($entityManager, $user);
        $form = $this->buildVirementForm($cardRows, [
            'destinataire' => (string) $virement['destinataire'],
            'montant' => (float) $virement['montant'],
            'devise' => (string) $virement['devise'],
            'carte_source' => (int) $virement['carte_source_id'],
            'carte_dest' => $virement['carte_dest_id'] !== null ? (int) $virement['carte_dest_id'] : null,
            'frequence' => (string) $virement['frequence'],
            'prochaine_execution' => $virement['prochaine_execution'] ? new \DateTimeImmutable((string) $virement['prochaine_execution']) : null,
            'description' => (string) ($virement['description'] ?? ''),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $validationErrors = $this->validateVirementPayload($data);
            if ($validationErrors !== []) {
                foreach ($validationErrors as $error) {
                    $this->addFlash('danger', $error);
                }

                return $this->redirectToRoute('front_virement_edit', ['id' => $id]);
            }

            if (!$this->isCardOwnedByUser($cardRows, (int) $data['carte_source'])) {
                throw $this->createAccessDeniedException();
            }

            if ($data['carte_dest'] && !$this->isCardOwnedByUser($cardRows, (int) $data['carte_dest'])) {
                throw $this->createAccessDeniedException();
            }

            $entityManager->getConnection()->update('virement_programme', [
                'carte_source_id' => $data['carte_source'],
                'carte_dest_id' => $data['carte_dest'] ?: null,
                'montant' => $data['montant'],
                'devise' => $data['devise'],
                'destinataire' => $data['destinataire'],
                'frequence' => $data['frequence'],
                'prochaine_execution' => $data['prochaine_execution']?->format('Y-m-d H:i:s'),
                'description' => $data['description'],
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $id,
                'user_id' => $user->getId(),
            ]);

            $this->addFlash('success', 'Virement mis à jour avec succès.');

            return $this->redirectToRoute('front_virement_index');
        }

        return $this->render('frontoffice/virement/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'front_virement_toggle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('toggle' . $id, $request->getPayload()->getString('_token'))) {
            $virement = $entityManager->getConnection()->fetchAssociative(
                'SELECT id, actif FROM virement_programme WHERE id = :id AND user_id = :uid',
                ['id' => $id, 'uid' => $user->getId()]
            );

            if ($virement) {
                $entityManager->getConnection()->update('virement_programme', [
                    'actif' => ((int) $virement['actif']) ? 0 : 1,
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $id]);
            }
        }

        return $this->redirectToRoute('front_virement_index');
    }

    #[Route('/{id}', name: 'front_virement_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM virement_programme WHERE id = :id AND user_id = :uid',
                ['id' => $id, 'uid' => $user->getId()]
            );
        }

        return $this->redirectToRoute('front_virement_index');
    }

    private function getOwnedCardRows(EntityManagerInterface $entityManager, User $user): array
    {
        return $entityManager->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.numero_carte, p.nom AS portefeuille_nom
             FROM carte_virtuelle c
             INNER JOIN portefeuille p ON p.id = c.portefeuille_id
             WHERE p.user_id = :uid
             ORDER BY c.id DESC',
            ['uid' => $user->getId()]
        );
    }

    private function isCardOwnedByUser(array $cardRows, int $cardId): bool
    {
        foreach ($cardRows as $row) {
            if ((int) $row['id'] === $cardId) {
                return true;
            }
        }

        return false;
    }

    private function resolveCardLabel(array $cardRows, int $cardId): string
    {
        foreach ($cardRows as $row) {
            if ((int) ($row['id'] ?? 0) === $cardId) {
                return '**** ' . substr((string) ($row['numero_carte'] ?? ''), -4);
            }
        }

        return (string) $cardId;
    }

    private function buildVirementForm(array $cardRows, array $defaults = [])
    {
        $cardChoices = [];
        foreach ($cardRows as $row) {
            $cardChoices['**** ' . substr((string) $row['numero_carte'], -4) . ' - ' . (string) $row['portefeuille_nom']] = (int) $row['id'];
        }

        $initialData = array_merge([
            'destinataire' => '',
            'montant' => null,
            'devise' => 'TND',
            'carte_source' => null,
            'carte_dest' => null,
            'frequence' => 'UNE_FOIS',
            'prochaine_execution' => new \DateTimeImmutable('+2 minutes', new \DateTimeZone(self::APP_TIMEZONE)),
            'description' => '',
        ], $defaults);

        return $this->createFormBuilder($initialData)
            ->add('destinataire', TextType::class)
            ->add('montant', MoneyType::class, ['currency' => false])
            ->add('devise', ChoiceType::class, [
                'choices' => ['TND' => 'TND', 'EUR' => 'EUR', 'USD' => 'USD'],
            ])
            ->add('carte_source', ChoiceType::class, [
                'choices' => $cardChoices,
                'placeholder' => 'Choisir une carte source',
            ])
            ->add('carte_dest', ChoiceType::class, [
                'choices' => $cardChoices,
                'required' => true,
                'placeholder' => 'Choisir une carte destination',
            ])
            ->add('frequence', ChoiceType::class, [
                'choices' => [
                    'Une fois' => 'UNE_FOIS',
                    'Quotidien' => 'QUOTIDIEN',
                    'Hebdomadaire' => 'HEBDOMADAIRE',
                    'Mensuel' => 'MENSUEL',
                ],
            ])
            ->add('prochaine_execution', DateTimeType::class, [
                'widget' => 'choice',
                'input' => 'datetime_immutable',
                'model_timezone' => self::APP_TIMEZONE,
                'view_timezone' => self::APP_TIMEZONE,
                'hours' => range(0, 23),
                'minutes' => range(0, 59),
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->getForm();
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<int,string>
     */
    private function validateVirementPayload(array $data): array
    {
        $errors = [];

        $destinataire = trim((string) ($data['destinataire'] ?? ''));
        if ($destinataire === '') {
            $errors[] = 'Le destinataire est obligatoire.';
        } elseif (mb_strlen($destinataire) > 255) {
            $errors[] = 'Le destinataire ne doit pas depasser 255 caracteres.';
        }

        if (!isset($data['montant']) || !is_numeric((string) $data['montant']) || (float) $data['montant'] <= 0) {
            $errors[] = 'Le montant doit etre un nombre superieur a 0.';
        }

        $devise = strtoupper(trim((string) ($data['devise'] ?? '')));
        if (!in_array($devise, ['TND', 'EUR', 'USD'], true)) {
            $errors[] = 'Devise invalide.';
        }

        $sourceCardId = isset($data['carte_source']) ? (int) $data['carte_source'] : 0;
        $destCardId = isset($data['carte_dest']) ? (int) $data['carte_dest'] : 0;

        if ($sourceCardId <= 0) {
            $errors[] = 'Carte source obligatoire.';
        }

        // Programmed transfer is local only: destination card is mandatory and must be different.
        if ($destCardId <= 0) {
            $errors[] = 'Carte destination obligatoire pour un virement programme.';
        }

        if ($sourceCardId > 0 && $destCardId > 0 && $sourceCardId === $destCardId) {
            $errors[] = 'La carte source et la carte destination doivent etre differentes.';
        }

        $frequence = strtoupper(trim((string) ($data['frequence'] ?? '')));
        if (!in_array($frequence, ['UNE_FOIS', 'QUOTIDIEN', 'HEBDOMADAIRE', 'MENSUEL'], true)) {
            $errors[] = 'Frequence de virement invalide.';
        }

        $prochaineExecution = $data['prochaine_execution'] ?? null;
        if (!$prochaineExecution instanceof \DateTimeInterface) {
            $errors[] = 'Date de prochaine execution invalide.';
        } else {
            $tz = new \DateTimeZone(self::APP_TIMEZONE);
            $selected = \DateTimeImmutable::createFromInterface($prochaineExecution)->setTimezone($tz);
            $now = new \DateTimeImmutable('now', $tz);

            if ($selected->getTimestamp() <= $now->getTimestamp()) {
                $errors[] = 'La date de prochaine execution peut etre aujourd\'hui, mais doit etre dans le futur.';
            }
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            $errors[] = 'La description ne doit pas depasser 1000 caracteres.';
        }

        return $errors;
    }
}
