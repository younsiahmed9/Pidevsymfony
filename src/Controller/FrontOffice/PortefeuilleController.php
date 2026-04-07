<?php

namespace App\Controller\FrontOffice;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portefeuille')]
final class PortefeuilleController extends AbstractController
{
    #[Route('/', name: 'front_portefeuille_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuilles = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, nom, devise_principale, solde_total FROM portefeuille WHERE utilisateur_id = :uid ORDER BY id DESC',
            ['uid' => $user->getId()]
        );

        return $this->render('frontoffice/portefeuille/index.html.twig', [
            'portefeuilles' => $portefeuilles,
        ]);
    }

    #[Route('/new', name: 'front_portefeuille_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder([
            'nom' => '',
            'devise_principale' => 'TND',
        ])
            ->add('nom', TextType::class)
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
                'solde_total' => '0.00',
                'devise_principale' => $data['devise_principale'],
                'utilisateur_id' => $user->getId(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            return $this->redirectToRoute('front_portefeuille_index');
        }

        return $this->render('frontoffice/portefeuille/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_portefeuille_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuille = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, nom, devise_principale FROM portefeuille WHERE id = :id AND utilisateur_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$portefeuille) {
            throw $this->createNotFoundException('Portefeuille introuvable.');
        }

        $form = $this->createFormBuilder([
            'nom' => (string) $portefeuille['nom'],
            'devise_principale' => (string) $portefeuille['devise_principale'],
        ])
            ->add('nom', TextType::class)
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
                'utilisateur_id' => $user->getId(),
            ]);

            return $this->redirectToRoute('front_portefeuille_index');
        }

        return $this->render('frontoffice/portefeuille/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'front_portefeuille_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuille = $entityManager->getConnection()->fetchAssociative(
            'SELECT id, nom, devise_principale, solde_total FROM portefeuille WHERE id = :id AND utilisateur_id = :uid',
            ['id' => $id, 'uid' => $user->getId()]
        );

        if (!$portefeuille) {
            throw $this->createNotFoundException('Portefeuille introuvable.');
        }

        $cartes = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, numero_carte, type, solde, devise, is_active FROM carte_virtuelle WHERE portefeuille_id = :pid ORDER BY id DESC',
            ['pid' => (int) $portefeuille['id']]
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

        return $this->render('frontoffice/portefeuille/show.html.twig', [
            'portefeuille' => $portefeuille,
            'cartes' => $cartes,
            'transactions' => $transactions,
        ]);
    }

    #[Route('/{id}', name: 'front_portefeuille_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM portefeuille WHERE id = :id AND utilisateur_id = :uid',
                ['id' => $id, 'uid' => $user->getId()]
            );
        }

        return $this->redirectToRoute('front_portefeuille_index');
    }
}
