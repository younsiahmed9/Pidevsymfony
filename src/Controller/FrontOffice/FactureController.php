<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Entity\Facture;
use App\Entity\Produit;
use App\Entity\Service as ServiceEntity;
use App\Service\Invoice\InvoiceEmailService;
use App\Service\Admin\PdfExportService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Form\Exception\TransformationFailedException;

#[Route('/facture', name: 'facture_')]
final class FactureController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        // Paramètres de recherche, filtre et tri
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sortBy', 'id_facture');
        $sortOrder = $request->query->get('sortOrder', 'DESC');
        $dateFrom = $request->query->get('dateFrom', '');
        $dateTo = $request->query->get('dateTo', '');

        // Construction de la requête SQL
        $sql = 'SELECT f.id_facture AS id,
                       f.montant,
                       f.date_facture AS dateFacture,
                       f.date_echeance AS dateEcheance,
                       f.id_service AS service,
                       f.id_produit AS produit,
                       f.statut,
                       f.numero_facture AS numeroFacture,
                       f.user_id,
                       s.nom_service AS serviceNom,
                       p.nom_produit AS produitNom
                FROM facture f
                LEFT JOIN service s ON s.id_service = f.id_service
                LEFT JOIN produit p ON p.id_produit = f.id_produit
                WHERE f.user_id = :user_id';
        
        $params = ['user_id' => $user->getId()];

        // Filtre de recherche
        if (!empty($search)) {
            $sql .= ' AND (f.numero_facture LIKE :search OR s.nom_service LIKE :search OR p.nom_produit LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filtre par statut
        if (!empty($statut)) {
            $sql .= ' AND f.statut = :statut';
            $params['statut'] = $statut;
        }

        // Filtre par période
        if (!empty($dateFrom)) {
            $sql .= ' AND f.date_facture >= :dateFrom';
            $params['dateFrom'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $sql .= ' AND f.date_facture <= :dateTo';
            $params['dateTo'] = $dateTo;
        }

        // Tri
        $allowedSortFields = ['id_facture', 'numero_facture', 'montant', 'date_facture', 'date_echeance', 'statut'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id_facture';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? strtoupper($sortOrder) : 'DESC';
        
        $sql .= ' ORDER BY f.' . $sortBy . ' ' . $sortOrder;

        $factures = $entityManager->getConnection()->fetchAllAssociative($sql, $params);

        // Statistiques pour l'utilisateur
        $statsSql = 'SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN statut = \'payee\' THEN 1 END) as payees,
                        COUNT(CASE WHEN statut = \'non_payee\' THEN 1 END) as non_payees,
                        COUNT(CASE WHEN statut = \'en_retard\' THEN 1 END) as en_retard,
                        COUNT(CASE WHEN statut = \'annulee\' THEN 1 END) as annulees,
                        SUM(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE 0 END) as montant_total,
                        AVG(CASE WHEN montant > 0 THEN CAST(montant AS DECIMAL(10,2)) ELSE NULL END) as montant_moyen,
                        COUNT(CASE WHEN id_service IS NOT NULL THEN 1 END) as factures_service,
                        COUNT(CASE WHEN id_produit IS NOT NULL THEN 1 END) as factures_produit
                     FROM facture 
                     WHERE user_id = :user_id';
        
        $statsParams = ['user_id' => $user->getId()];
        if (!empty($dateFrom) || !empty($dateTo)) {
            $statsSql .= ' AND 1=1';
            if (!empty($dateFrom)) {
                $statsSql .= ' AND date_facture >= :dateFrom';
                $statsParams['dateFrom'] = $dateFrom;
            }
            if (!empty($dateTo)) {
                $statsSql .= ' AND date_facture <= :dateTo';
                $statsParams['dateTo'] = $dateTo;
            }
        }
        
        $stats = $entityManager->getConnection()->fetchAllAssociative($statsSql, $statsParams)[0];

        return $this->render('frontoffice/facture/index.html.twig', [
            'factures' => $factures,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, nom_service AS nomService, tarif
             FROM service
             WHERE user_id = :user_id
             ORDER BY nom_service ASC',
            ['user_id' => $user->getId()]
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant
             FROM produit
             WHERE user_id = :user_id
             ORDER BY nom_produit ASC',
            ['user_id' => $user->getId()]
        );

        $factureFormData = [
            'numeroFacture' => '',
            'montant' => '',
            'dateFacture' => (new \DateTime())->format('Y-m-d'),
            'dateEcheance' => '',
            'service' => '',
            'produit' => '',
            'statut' => 'non_payee',
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            $factureFormData = [
                'numeroFacture' => trim((string) $request->request->get('numeroFacture', '')),
                'montant' => trim((string) $request->request->get('montant', '')),
                'dateFacture' => trim((string) $request->request->get('dateFacture', (new \DateTime())->format('Y-m-d'))),
                'dateEcheance' => trim((string) $request->request->get('dateEcheance', '')),
                'service' => trim((string) $request->request->get('service', '')),
                'produit' => trim((string) $request->request->get('produit', '')),
                'statut' => trim((string) $request->request->get('statut', 'non_payee')),
            ];

            $formErrors = $this->validateFactureInput($factureFormData, $entityManager, (int) $user->getId());

            if ($formErrors === []) {
                $serviceEntity = null;
                if ($factureFormData['service'] !== '') {
                    $serviceEntity = $entityManager->getRepository(ServiceEntity::class)->findOneBy([
                        'id' => (int) $factureFormData['service'],
                        'user' => $user,
                    ]);
                }

                $produitEntity = null;
                if ($factureFormData['produit'] !== '') {
                    $produitEntity = $entityManager->getRepository(Produit::class)->findOneBy([
                        'id' => (int) $factureFormData['produit'],
                        'user' => $user,
                    ]);
                }

                $factureEntity = new Facture();
                $factureEntity->setUser($user);
                $factureEntity->setMontant(number_format((float) str_replace(',', '.', $factureFormData['montant']), 2, '.', ''));
                $factureEntity->setDateFacture(new \DateTime($factureFormData['dateFacture']));
                $factureEntity->setDateEcheance(new \DateTime($factureFormData['dateEcheance'] !== '' ? $factureFormData['dateEcheance'] : $factureFormData['dateFacture']));
                $factureEntity->setService($serviceEntity instanceof ServiceEntity ? $serviceEntity : null);
                $factureEntity->setProduit($produitEntity instanceof Produit ? $produitEntity : null);
                $factureEntity->setStatut($factureFormData['statut']);
                $factureEntity->setNumeroFacture($factureFormData['numeroFacture'] !== '' ? $factureFormData['numeroFacture'] : 'FAC-' . strtoupper(bin2hex(random_bytes(4))));

                $entityManager->persist($factureEntity);
                $entityManager->flush();

                $this->addFlash('success', 'Facture créée avec succès.');
                return $this->redirectToRoute('facture_index');
            }

            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('frontoffice/facture/new.html.twig', [
            'facture' => $factureFormData,
            'services' => $services,
            'produits' => $produits,
            'formErrors' => $formErrors,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $services = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_service AS id, nom_service AS nomService, tarif
             FROM service
             WHERE user_id = :user_id
             ORDER BY nom_service ASC',
            ['user_id' => $user->getId()]
        );

        $produits = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id_produit AS id, nom_produit AS nomProduit, montant
             FROM produit
             WHERE user_id = :user_id
             ORDER BY nom_produit ASC',
            ['user_id' => $user->getId()]
        );

        $factureEntity = $entityManager->getRepository(Facture::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$factureEntity instanceof Facture) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        $factureFormData = [
            'id' => (string) ($factureEntity->getId() ?? $id),
            'numeroFacture' => (string) ($factureEntity->getNumeroFacture() ?? ''),
            'montant' => (string) ($factureEntity->getMontant() ?? ''),
            'dateFacture' => $factureEntity->getDateFacture() ? $factureEntity->getDateFacture()->format('Y-m-d') : (new \DateTime())->format('Y-m-d'),
            'dateEcheance' => $factureEntity->getDateEcheance() ? $factureEntity->getDateEcheance()->format('Y-m-d') : '',
            'service' => (string) ($factureEntity->getService()?->getId() ?? ''),
            'produit' => (string) ($factureEntity->getProduit()?->getId() ?? ''),
            'statut' => (string) ($factureEntity->getStatut() ?? 'non_payee'),
        ];
        $formErrors = [];

        if ($request->isMethod('POST')) {
            try {
                $factureFormData = [
                    'id' => (string) ($factureEntity->getId() ?? $id),
                    'numeroFacture' => trim((string) $request->request->get('numeroFacture', $factureFormData['numeroFacture'])),
                    'montant' => trim((string) $request->request->get('montant', '')),
                    'dateFacture' => trim((string) $request->request->get('dateFacture', $factureFormData['dateFacture'])),
                    'dateEcheance' => trim((string) $request->request->get('dateEcheance', '')),
                    'service' => trim((string) $request->request->get('service', '')),
                    'produit' => trim((string) $request->request->get('produit', '')),
                    'statut' => trim((string) $request->request->get('statut', 'non_payee')),
                ];

                $formErrors = $this->validateFactureInput($factureFormData, $entityManager, (int) $user->getId(), $id);

                if ($formErrors === []) {
                    $serviceEntity = null;
                    if ($factureFormData['service'] !== '') {
                        $serviceEntity = $entityManager->getRepository(ServiceEntity::class)->findOneBy([
                            'id' => (int) $factureFormData['service'],
                            'user' => $user,
                        ]);
                    }

                    $produitEntity = null;
                    if ($factureFormData['produit'] !== '') {
                        $produitEntity = $entityManager->getRepository(Produit::class)->findOneBy([
                            'id' => (int) $factureFormData['produit'],
                            'user' => $user,
                        ]);
                    }

                    $factureEntity->setMontant(number_format((float) str_replace(',', '.', $factureFormData['montant']), 2, '.', ''));
                    $factureEntity->setDateFacture(new \DateTime($factureFormData['dateFacture']));
                    $factureEntity->setDateEcheance(new \DateTime($factureFormData['dateEcheance'] !== '' ? $factureFormData['dateEcheance'] : $factureFormData['dateFacture']));
                    $factureEntity->setService($serviceEntity instanceof ServiceEntity ? $serviceEntity : null);
                    $factureEntity->setProduit($produitEntity instanceof Produit ? $produitEntity : null);
                    $factureEntity->setStatut($factureFormData['statut']);
                    $factureEntity->setNumeroFacture($factureFormData['numeroFacture']);

                    // Valider l'entité
                    $validator = $this->container->get('validator');
                    $violations = $validator->validate($factureEntity);
                    
                    if (count($violations) > 0) {
                        throw new ValidationFailedException($factureEntity, $violations);
                    }

                    $entityManager->flush();

                    $this->addFlash('success', 'Facture mise à jour avec succès.');
                    return $this->redirectToRoute('facture_index');
                }

                $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');

            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Erreur : Le numéro de facture existe déjà');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Erreur : Contrainte de clé étrangère violée');
            } catch (EntityNotFoundException $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            } catch (ValidationFailedException $e) {
                $errors = [];
                foreach ($e->getViolations() as $violation) {
                    $errors[] = $violation->getMessage();
                }
                $this->addFlash('error', implode(', ', $errors));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            }
        }

        return $this->render('frontoffice/facture/edit.html.twig', [
            'facture' => $factureFormData,
            'services' => $services,
            'produits' => $produits,
            'formErrors' => $formErrors,
        ]);
    }

    private function validateFactureInput(array $data, EntityManagerInterface $entityManager, int $userId, ?int $currentFactureId = null): array
    {
        $errors = [];

        $montant = str_replace(',', '.', (string) $data['montant']);
        if ($montant === '' || !is_numeric($montant) || (float) $montant <= 0) {
            $errors['montant'][] = 'Le montant doit être un nombre supérieur à 0.';
        }

        $statutsValid = ['non_payee', 'payee', 'en_retard'];
        if (!in_array($data['statut'], $statutsValid)) {
            $errors['statut'][] = 'Le statut sélectionné est invalide.';
        }

        if ($data['numeroFacture'] !== '' && !preg_match('/^[A-Za-z0-9\-]{3,40}$/', $data['numeroFacture'])) {
            $errors['numeroFacture'][] = 'Le numéro de facture est invalide.';
        }

        if ($data['numeroFacture'] !== '' && !isset($errors['numeroFacture'])) {
            $query = 'SELECT id_facture FROM facture WHERE numero_facture = :numero';
            $params = ['numero' => $data['numeroFacture']];

            if ($currentFactureId !== null) {
                $query .= ' AND id_facture <> :current_id';
                $params['current_id'] = $currentFactureId;
            }

            $existingFactureId = $entityManager->getConnection()->fetchOne($query, $params);
            if ($existingFactureId) {
                $errors['numeroFacture'][] = 'Ce numéro de facture existe déjà. Veuillez choisir une référence unique.';
            }
        }

        $dateFacture = \DateTime::createFromFormat('Y-m-d', (string) $data['dateFacture']);
        if (!$dateFacture || $dateFacture->format('Y-m-d') !== $data['dateFacture']) {
            $errors['dateFacture'][] = 'La date de facture est invalide.';
        }

        if ($data['dateEcheance'] !== '') {
            $dateEcheance = \DateTime::createFromFormat('Y-m-d', (string) $data['dateEcheance']);
            if (!$dateEcheance || $dateEcheance->format('Y-m-d') !== $data['dateEcheance']) {
                $errors['dateEcheance'][] = 'La date d\'échéance est invalide.';
            } elseif (!isset($errors['dateFacture']) && $dateEcheance < $dateFacture) {
                $errors['dateEcheance'][] = 'La date d\'échéance doit être postérieure à la date de facture.';
            }
        }

        if ($data['service'] !== '') {
            if (!ctype_digit($data['service'])) {
                $errors['service'][] = 'Le service sélectionné est invalide.';
            } else {
                $serviceExists = $entityManager->getConnection()->fetchOne(
                    'SELECT id_service FROM service WHERE id_service = :id AND user_id = :user_id',
                    ['id' => (int) $data['service'], 'user_id' => $userId]
                );
                if (!$serviceExists) {
                    $errors['service'][] = 'Le service sélectionné ne vous appartient pas.';
                }
            }
        }

        if ($data['produit'] !== '') {
            if (!ctype_digit($data['produit'])) {
                $errors['produit'][] = 'Le produit sélectionné est invalide.';
            } else {
                $produitExists = $entityManager->getConnection()->fetchOne(
                    'SELECT id_produit FROM produit WHERE id_produit = :id AND user_id = :user_id',
                    ['id' => (int) $data['produit'], 'user_id' => $userId]
                );
                if (!$produitExists) {
                    $errors['produit'][] = 'Le produit sélectionné ne vous appartient pas.';
                }
            }
        }

        return $errors;
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $facture = $entityManager->getConnection()->fetchAssociative(
            'SELECT id_facture AS id, montant, date_facture AS dateFacture, date_echeance AS dateEcheance, id_service AS service, id_produit AS produit, statut, numero_facture AS numeroFacture, user_id
             FROM facture
             WHERE id_facture = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $user->getId()]
        );

        if (!$facture) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('frontoffice/facture/show.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}/send-email', name: 'send_email', methods: ['POST'])]
    public function sendEmail(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        InvoiceEmailService $invoiceEmailService,
        PdfExportService $pdfExportService
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('send_email' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('facture_show', ['id' => $id]);
        }

        $facture = $entityManager->getConnection()->fetchAssociative(
            'SELECT f.id_facture AS id,
                    f.montant,
                    f.date_facture AS dateFacture,
                    f.date_echeance AS dateEcheance,
                    f.statut,
                    f.numero_facture AS numeroFacture,
                    s.nom_service AS serviceNom,
                    p.nom_produit AS produitNom
             FROM facture f
             LEFT JOIN service s ON s.id_service = f.id_service
             LEFT JOIN produit p ON p.id_produit = f.id_produit
             WHERE f.id_facture = :id AND f.user_id = :user_id',
            [
                'id' => $id,
                'user_id' => $user->getId(),
            ]
        );

        if (!is_array($facture)) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        $recipient = trim((string) $request->request->get('recipientEmail', ''));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Veuillez saisir une adresse email valide.');

            return $this->redirectToRoute('facture_show', ['id' => $id]);
        }

        try {
            $html = $this->renderView('frontoffice/facture/email_invoice_pdf.html.twig', [
                'facture' => $facture,
                'user' => $user,
                'generatedAt' => new \DateTimeImmutable(),
            ]);

            $filename = sprintf(
                'facture-%s.pdf',
                preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) ($facture['numeroFacture'] ?? ('F-' . $id)))
            );

            $pdfResponse = $pdfExportService->renderPdfResponse($html, $filename);
            $pdfContent = (string) $pdfResponse->getContent();

            $invoiceNumber = (string) ($facture['numeroFacture'] ?? ('#' . $id));
            $amount = (string) ($facture['montant'] ?? '');
            $htmlContent = sprintf(
                '<html><body><h2>Votre facture %s</h2><p>Veuillez trouver ci-joint votre facture au format PDF.</p><ul><li><strong>Montant:</strong> %s</li><li><strong>Statut:</strong> %s</li></ul><p>Cordialement,<br>FinTrack</p></body></html>',
                htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($facture['statut'] ?? ''), ENT_QUOTES, 'UTF-8')
            );

            $invoiceEmailService->sendInvoicePdf(
                $recipient,
                'Votre facture ' . $invoiceNumber,
                $htmlContent,
                $pdfContent,
                $filename,
                [
                    'invoice_id' => $facture['id'] ?? $id,
                    'invoice_number' => $invoiceNumber,
                    'amount' => $amount,
                    'status' => (string) ($facture['statut'] ?? ''),
                    'recipient' => $recipient,
                ],
                $user
            );

            $this->addFlash('success', 'Facture envoyee par email avec succes a ' . $recipient . '.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Echec de l\'envoi email: ' . $e->getMessage());
        }

        return $this->redirectToRoute('facture_show', ['id' => $id]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->isCsrfTokenValid('delete' . $id, $request->getPayload()->getString('_token'))) {
            $factureEntity = $entityManager->getRepository(Facture::class)->findOneBy([
                'id' => $id,
                'user' => $user,
            ]);

            if ($factureEntity instanceof Facture) {
                $entityManager->remove($factureEntity);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('facture_index');
    }
}
