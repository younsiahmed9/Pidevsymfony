<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Client;
use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Security\LoginFormAuthenticator;
use App\Security\AdminFaceAuthSession;

class AuthController extends AbstractController
{
    private const REGISTRATION_VERIFICATION_SESSION_KEY = 'pending_registration_verification';


    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    private function normalizeDescriptor(array $descriptor): array
    {
        $magnitude = 0.0;
        foreach ($descriptor as $value) {
            $magnitude += $value * $value;
        }
        $magnitude = sqrt($magnitude);
        if ($magnitude <= 0.0) {
            return $descriptor;
        }
        return array_map(static fn ($value) => $value / $magnitude, $descriptor);
    }

    #[Route('/legacy-home', name: 'app_home_legacy')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already authenticated, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, MailerInterface $mailer): Response
    {
        // If user is already authenticated, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        if ($form->isSubmitted() && !$form->isValid()) {
            if ($isAjax) {
                return $this->json([
                    'valid' => false,
                    'errors' => $this->normalizeRegistrationFormErrors($form),
                ], 422);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $normalizedEmail = strtolower(trim((string) $user->getEmail()));
            $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($normalizedEmail);
            if ($existingUser) {
                if ($isAjax) {
                    return $this->json([
                        'valid' => false,
                        'errors' => [
                            'email' => ['Email already registered.'],
                        ],
                    ], 422);
                }

                $this->addFlash('error', 'Email already registered.');
                return $this->redirectToRoute('app_register');
            }

            $plainPassword = $form->get('plainPassword')->getData();
            $roleChoice = (string) $form->get('roleChoice')->getData();
            $adminCode = trim((string) $form->get('adminCode')->getData());
            $faceDescriptorJson = trim((string) ($form->get('faceDescriptor')->getData() ?? ''));

            if ($roleChoice === 'ADMIN' && $faceDescriptorJson === '') {
                if ($isAjax) {
                    return $this->json([
                        'valid' => false,
                        'errors' => [
                            'faceDescriptor' => ['Face ID is required for administrators.'],
                        ],
                    ], 422);
                }

                $this->addFlash('error', 'Face ID is required for administrators.');
                return $this->redirectToRoute('app_register');
            }

            if ($roleChoice === 'ADMIN') {
                $expectedAdminCode = trim((string) ($_ENV['ADMIN_CODE'] ?? $_SERVER['ADMIN_CODE'] ?? ''));
                if ($expectedAdminCode === '' || !hash_equals($expectedAdminCode, $adminCode)) {
                    if ($isAjax) {
                        return $this->json([
                            'valid' => false,
                            'errors' => [
                                'adminCode' => ['Invalid admin code.'],
                            ],
                        ], 422);
                    }

                    $this->addFlash('error', 'Invalid admin code.');
                    return $this->redirectToRoute('app_register');
                }
            }

            $user->setEmail($normalizedEmail);
            $passwordHash = $this->passwordHasher->hashPassword($user, (string) $plainPassword);
            $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = (new \DateTimeImmutable('+10 minutes'))->getTimestamp();

            $cin = trim((string) $form->get('cin')->getData());
            $phone = trim((string) $form->get('phone')->getData());

            $request->getSession()->set(self::REGISTRATION_VERIFICATION_SESSION_KEY, [
                'email' => $normalizedEmail,
                'fullName' => $user->getFullName(),
                'passwordHash' => $passwordHash,
                'roleChoice' => $roleChoice,
                'adminCode' => $adminCode,
                'cin' => $cin,
                'phone' => $phone,
                'faceDescriptor' => $roleChoice === 'ADMIN' ? $faceDescriptorJson : '',
                'verificationCode' => $verificationCode,
                'expiresAt' => $expiresAt,
            ]);

            try {
                $mailerDsn = (string) ($_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? '');
                if ($mailerDsn === '' || str_starts_with($mailerDsn, 'null://')) {
                    throw new \RuntimeException('MAILER_DSN is not configured for real delivery.');
                }

                $mailFrom = (string) ($_ENV['MAIL_FROM'] ?? $_SERVER['MAIL_FROM'] ?? 'no-reply@fintrack.local');
                $emailMessage = (new Email())
                    ->from($mailFrom)
                    ->to($normalizedEmail)
                    ->subject('FinTrack - Code de verification')
                    ->text("Votre code de verification est: {$verificationCode}. Ce code expire dans 10 minutes.");

                $mailer->send($emailMessage);
            } catch (\Throwable $exception) {
                $request->getSession()->remove(self::REGISTRATION_VERIFICATION_SESSION_KEY);

                if ($isAjax) {
                    return $this->json([
                        'valid' => false,
                        'errors' => [
                            'general' => ['Impossible d envoyer le mail de verification. Verifiez la configuration mail.'],
                        ],
                    ], 500);
                }

                $this->addFlash('error', 'Impossible d envoyer le mail de verification. Verifiez la configuration mail.');
                return $this->redirectToRoute('app_register');
            }

            if ($isAjax) {
                return $this->json([
                    'valid' => true,
                    'redirect' => $this->generateUrl('app_register_verify_code'),
                ]);
            }

            $this->addFlash('success', 'Un code de verification a ete envoye a votre email.');
            return $this->redirectToRoute('app_register_verify_code');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/face-id/enroll', name: 'app_face_id_enroll', methods: ['POST'])]
    public function enrollFaceId(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('face_id_enroll', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Jeton Face ID invalide. Veuillez reessayer.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'User session is invalid. Please sign in again.');
            return $this->redirectToRoute('app_login');
        }

        $descriptorJson = trim((string) $request->request->get('face_descriptor', ''));
        if ($descriptorJson === '') {
            $this->addFlash('error', 'No face descriptor was captured. Please try again.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
        }

        $descriptor = json_decode($descriptorJson, true);
        if (!is_array($descriptor) || count($descriptor) !== 4096) {
            $this->addFlash('error', 'Invalid Face ID template. Please capture again.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
        }

        $floatDescriptor = array_values(array_map(static fn ($value) => (float) $value, $descriptor));
        $normalizedDescriptor = $this->normalizeDescriptor($floatDescriptor);
        $user->setFaceTemplate(json_encode($normalizedDescriptor, JSON_THROW_ON_ERROR));
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'Face ID has been enrolled successfully.');
        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }

    private function normalizeRegistrationFormErrors(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors(true, true) as $error) {
            $origin = $error->getOrigin();
            $field = 'general';

            if ($origin instanceof FormInterface) {
                $fieldName = $origin->getName();
                $parentName = $origin->getParent() instanceof FormInterface ? $origin->getParent()->getName() : null;

                if ($parentName === 'plainPassword') {
                    $field = 'plainPassword.' . $fieldName;
                } elseif ($fieldName === 'plainPassword') {
                    $field = 'plainPassword.second';
                } elseif (in_array($fieldName, ['email', 'fullName', 'cin', 'phone', 'adminCode', 'roleChoice', 'faceDescriptor'], true)) {
                    $field = $fieldName;
                }
            }

            $errors[$field][] = (string) $error->getMessage();
        }

        return $errors;
    }

    #[Route('/register/verify-code', name: 'app_register_verify_code', methods: ['GET', 'POST'])]
    public function verifyRegisterCode(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $pendingRegistration = $request->getSession()->get(self::REGISTRATION_VERIFICATION_SESSION_KEY);
        if (!is_array($pendingRegistration)) {
            $this->addFlash('error', 'Aucune inscription en attente.');
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $submittedCode = trim((string) $request->request->get('verification_code', ''));
            $expectedCode = (string) ($pendingRegistration['verificationCode'] ?? '');
            $expiresAt = (int) ($pendingRegistration['expiresAt'] ?? 0);

            if ($submittedCode === '' || strlen($submittedCode) !== 6) {
                $this->addFlash('error', 'Entrez un code valide a 6 chiffres.');
                return $this->redirectToRoute('app_register_verify_code');
            }

            if ($expiresAt < time()) {
                $request->getSession()->remove(self::REGISTRATION_VERIFICATION_SESSION_KEY);
                $this->addFlash('error', 'Le code a expire. Recommencez l inscription.');
                return $this->redirectToRoute('app_register');
            }

            if (!hash_equals($expectedCode, $submittedCode)) {
                $this->addFlash('error', 'Code de verification incorrect.');
                return $this->redirectToRoute('app_register_verify_code');
            }

            $email = strtolower(trim((string) ($pendingRegistration['email'] ?? '')));
            if ($email === '') {
                $request->getSession()->remove(self::REGISTRATION_VERIFICATION_SESSION_KEY);
                $this->addFlash('error', 'Donnees d inscription invalides.');
                return $this->redirectToRoute('app_register');
            }

            $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($email);
            if ($existingUser) {
                $request->getSession()->remove(self::REGISTRATION_VERIFICATION_SESSION_KEY);
                $this->addFlash('error', 'Cet email est deja utilise.');
                return $this->redirectToRoute('app_register');
            }

            $newUser = new User();
            $newUser->setEmail($email);
            $newUser->setFullName((string) ($pendingRegistration['fullName'] ?? ''));
            $newUser->setPasswordHash((string) ($pendingRegistration['passwordHash'] ?? ''));
            $newUser->setIsActive(true);

            $roleChoice = (string) ($pendingRegistration['roleChoice'] ?? 'CLIENT');

            $faceDescriptorJson = trim((string) ($pendingRegistration['faceDescriptor'] ?? ''));
            if ($roleChoice === 'ADMIN' && $faceDescriptorJson !== '') {
                $decodedDescriptor = json_decode($faceDescriptorJson, true);
                if (is_array($decodedDescriptor) && count($decodedDescriptor) === 4096) {
                    $floatDescriptor = array_values(array_map(static fn ($value) => (float) $value, $decodedDescriptor));
                    $normalizedDescriptor = $this->normalizeDescriptor($floatDescriptor);
                    $newUser->setFaceTemplate(json_encode($normalizedDescriptor));
                }
            }

            $newUser->setRole($roleChoice === 'ADMIN' ? 'ADMIN' : 'CLIENT');

            $this->entityManager->persist($newUser);
            $this->entityManager->flush();

            if ($roleChoice === 'ADMIN') {
                $admin = new Admin();
                $admin->setUser($newUser);
                $admin->setAdminCode((string) ($pendingRegistration['adminCode'] ?? ''));
                $this->entityManager->persist($admin);
            } else {
                $client = new Client();
                $client->setUser($newUser);

                $cin = trim((string) ($pendingRegistration['cin'] ?? ''));
                $phone = trim((string) ($pendingRegistration['phone'] ?? ''));
                if ($cin !== '') {
                    $client->setCin($cin);
                }
                if ($phone !== '') {
                    $client->setPhone($phone);
                }

                $this->entityManager->persist($client);
            }

            $this->entityManager->flush();
            $request->getSession()->remove(self::REGISTRATION_VERIFICATION_SESSION_KEY);

            $this->addFlash('success', 'Inscription validee. Connectez-vous maintenant.');
            return $this->redirectToRoute('app_login');
        }

        $expiresAt = (int) ($pendingRegistration['expiresAt'] ?? 0);
        $minutesLeft = max(0, (int) ceil(($expiresAt - time()) / 60));

        return $this->render('auth/verify_email_code.html.twig', [
            'pendingEmail' => (string) ($pendingRegistration['email'] ?? ''),
            'minutesLeft' => $minutesLeft,
        ]);
    }

    #[Route('/face-id/login', name: 'app_face_id_login', methods: ['POST'])]
    public function faceIdLogin(): Response
    {
        throw new \LogicException('This route is handled by the FaceIdAuthenticator.');
    }

    #[Route('/admin/face-id-challenge', name: 'app_admin_face_id_challenge', methods: ['GET'])]
    public function adminFaceIdChallenge(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'ADMIN') {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();
        $sessionData = $session->get(AdminFaceAuthSession::SESSION_KEY);
        if (is_array($sessionData) && ($sessionData['verified'] ?? false) === true) {
            return $this->redirectToRoute('admin_index');
        }

        $adminEmail = strtolower((string) $user->getUserIdentifier());
        if (is_array($sessionData) && (string) ($sessionData['email'] ?? '') !== '') {
            $adminEmail = strtolower((string) $sessionData['email']);
        }

        $session->set(AdminFaceAuthSession::SESSION_KEY, [
            'email' => $adminEmail,
            'verified' => false,
            'startedAt' => is_array($sessionData) && isset($sessionData['startedAt']) ? (int) $sessionData['startedAt'] : time(),
        ]);

        return $this->render('auth/admin_face_id_challenge.html.twig', [
            'adminEmail' => $adminEmail,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): Response
    {
        // This method can be blank - it will be handled by the logout in security.yaml
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/legacy/admin/dashboard', name: 'legacy_admin_dashboard')]
    #[Route('/admin/users-management', name: 'app_admin_dashboard')]
    public function adminDashboard(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->redirectToRoute('admin_index');

        $userRepository = $this->entityManager->getRepository(User::class);
        $searchTerm = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 8;

        $totalUsers = $userRepository->count([]);
        $activeUsers = $userRepository->count(['isActive' => true]);
        $activeClients = $userRepository->count(['role' => 'CLIENT', 'isActive' => true]);
        $administrators = $userRepository->count(['role' => 'ADMIN']);

        $queryBuilder = $userRepository->createQueryBuilder('u');

        if ($searchTerm !== '') {
            $normalizedTerm = mb_strtolower($searchTerm);
            $queryBuilder
                ->andWhere("LOWER(COALESCE(u.fullName, '')) LIKE :term OR LOWER(u.email) LIKE :term OR LOWER(u.role) LIKE :term")
                ->setParameter('term', '%' . $normalizedTerm . '%');
        }

        $countQueryBuilder = clone $queryBuilder;
        $filteredTotalUsers = (int) $countQueryBuilder
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($filteredTotalUsers / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $users = $queryBuilder
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'totalUsers' => $totalUsers,
                'activeUsers' => $activeUsers,
                'activeClients' => $activeClients,
                'administrators' => $administrators,
            ],
            'users' => $users,
            'searchTerm' => $searchTerm,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalItems' => $filteredTotalUsers,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/legacy/admin/users/{id}/update', name: 'legacy_admin_user_update', methods: ['POST'])]
    #[Route('/admin/users/{id}/update', name: 'app_admin_user_update', methods: ['POST'])]
    public function updateUser(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $isAjax = $request->isXmlHttpRequest();

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'User not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->addFlash('error', 'User not found.');
            return $this->redirectBackToAdmin($request);
        }

        if (!$this->isCsrfTokenValid('admin_edit_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Invalid edit token.'], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', 'Invalid edit token.');
            return $this->redirectBackToAdmin($request);
        }

        $fullName = trim((string) $request->request->get('full_name', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $isActive = (bool) $request->request->get('is_active', false);

        if ($email === '') {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Email is required.'], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', 'Email is required.');
            return $this->redirectBackToAdmin($request);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($email);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'This email is already used by another user.'], Response::HTTP_CONFLICT);
            }

            $this->addFlash('error', 'This email is already used by another user.');
            return $this->redirectBackToAdmin($request);
        }

        $user->setFullName($fullName !== '' ? $fullName : null);
        $user->setEmail($email);
        $user->setIsActive($isActive);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        if ($isAjax) {
            return $this->json(['success' => true]);
        }

        $this->addFlash('success', 'User updated successfully.');
        return $this->redirectBackToAdmin($request);
    }

    #[Route('/legacy/admin/users/{id}/delete', name: 'legacy_admin_user_delete', methods: ['POST'])]
    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $isAjax = $request->isXmlHttpRequest();

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'User not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->addFlash('error', 'User not found.');
            return $this->redirectBackToAdmin($request);
        }

        if (!$this->isCsrfTokenValid('admin_delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Invalid delete token.'], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', 'Invalid delete token.');
            return $this->redirectBackToAdmin($request);
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'You cannot delete your own account.'], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectBackToAdmin($request);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        if ($isAjax) {
            return $this->json(['success' => true]);
        }

        $this->addFlash('success', 'User deleted successfully.');
        return $this->redirectBackToAdmin($request);
    }

    private function getDashboardRedirectParams(Request $request): array
    {
        $params = [];

        $returnQuery = trim((string) $request->request->get('_return_q', ''));
        if ($returnQuery !== '') {
            $params['q'] = $returnQuery;
        }

        $returnPage = max(1, (int) $request->request->get('_return_page', 1));
        $params['page'] = $returnPage;

        return $params;
    }

    private function redirectBackToAdmin(Request $request): Response
    {
        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('admin_index', $this->getDashboardRedirectParams($request));
    }

    #[Route('/profile/update', name: 'app_profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('profile_edit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid profile form token. Please try again.');
            return $this->redirectToRoute('app_home');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'User session is invalid. Please sign in again.');
            return $this->redirectToRoute('app_login');
        }

        $fullName = trim((string) $request->request->get('full_name', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        /** @var UploadedFile|null $uploadedPhoto */
        $uploadedPhoto = $request->files->get('profile_photo_file');

        if ($fullName === '' || $email === '') {
            $this->addFlash('error', 'Full name and email are required.');
            return $this->redirectToRoute('app_home');
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($email);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'This email is already used by another account.');
            return $this->redirectToRoute('app_home');
        }

        $user->setFullName($fullName);
        $user->setEmail($email);

        if ($uploadedPhoto instanceof UploadedFile && $uploadedPhoto->isValid()) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array((string) $uploadedPhoto->getMimeType(), $allowedMimeTypes, true)) {
                $this->addFlash('error', 'Only JPG, PNG, WEBP, or GIF images are allowed.');
                return $this->redirectToRoute('app_home');
            }

            if ($uploadedPhoto->getSize() > 5 * 1024 * 1024) {
                $this->addFlash('error', 'Profile image must be 5MB or less.');
                return $this->redirectToRoute('app_home');
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                $this->addFlash('error', 'Could not prepare upload directory.');
                return $this->redirectToRoute('app_home');
            }

            $baseName = pathinfo((string) $uploadedPhoto->getClientOriginalName(), PATHINFO_FILENAME);
            $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $baseName) ?: 'profile';
            $extension = $uploadedPhoto->guessExtension() ?: 'jpg';
            $newFilename = sprintf('%s-%s.%s', $safeBaseName, bin2hex(random_bytes(6)), $extension);
            $uploadedPhoto->move($uploadDir, $newFilename);

            $user->setProfilePhoto('uploads/profiles/' . $newFilename);
        }

        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->addFlash('success', 'Your profile has been updated successfully.');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/legacy/api/admin/users', name: 'legacy_api_admin_users', methods: ['GET'])]
    #[Route('/api/admin/users', name: 'api_admin_users', methods: ['GET'])]
    public function apiAdminUsers(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $userRepository = $this->entityManager->getRepository(User::class);
        $searchTerm = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', 'all'));
        $role = trim((string) $request->query->get('role', 'all'));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 8;

        $queryBuilder = $userRepository->createQueryBuilder('u');

        if ($searchTerm !== '') {
            $normalizedTerm = mb_strtolower($searchTerm);
            $queryBuilder
                ->andWhere("LOWER(COALESCE(u.fullName, '')) LIKE :term OR LOWER(u.email) LIKE :term OR LOWER(u.role) LIKE :term")
                ->setParameter('term', '%' . $normalizedTerm . '%');
        }

        if ($status === 'active') {
            $queryBuilder->andWhere('u.isActive = :active')->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $queryBuilder->andWhere('u.isActive = :active')->setParameter('active', false);
        }

        if ($role === 'ADMIN' || $role === 'CLIENT') {
            $queryBuilder->andWhere('u.role = :role')->setParameter('role', $role);
        }

        $countQueryBuilder = clone $queryBuilder;
        $filteredTotalUsers = (int) $countQueryBuilder
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($filteredTotalUsers / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $users = $queryBuilder
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $usersData = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName() ?: 'No full name',
                'profilePhoto' => $user->getProfilePhoto(),
                'role' => $user->getRole(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i') ?: '-',
                'updatedAt' => $user->getUpdatedAt()?->format('Y-m-d H:i') ?: '-',
            ];
        }, $users);

        return $this->json([
            'success' => true,
            'users' => $usersData,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalItems' => $filteredTotalUsers,
                'totalPages' => $totalPages,
            ],
        ]);
    }
}
