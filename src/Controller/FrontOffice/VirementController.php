<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/virement')]
final class VirementController extends AbstractController
{
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

    #[Route('/{id}', name: 'front_virement_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $virement = $entityManager->getConnection()->fetchAssociative(
            'SELECT v.id, v.destinataire, v.montant, v.devise, v.frequence, v.prochaine_execution,
                    v.statut, v.actif, v.description, v.created_at,
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

            $this->addFlash('success', 'Virement programmé avec succès.');

            return $this->redirectToRoute('front_virement_index');
        }

        return $this->render('frontoffice/virement/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_virement_edit', methods: ['GET', 'POST'])]
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

    #[Route('/{id}/toggle', name: 'front_virement_toggle', methods: ['POST'])]
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

    #[Route('/{id}', name: 'front_virement_delete', methods: ['POST'])]
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
            'prochaine_execution' => new \DateTimeImmutable('+1 day'),
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
                'required' => false,
                'placeholder' => 'Choisir une carte destination (optionnel)',
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
                'widget' => 'single_text',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->getForm();
    }
}
