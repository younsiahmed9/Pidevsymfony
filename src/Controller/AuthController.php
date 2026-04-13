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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Security\LoginFormAuthenticator;

class AuthController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

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
    public function register(Request $request): Response
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
            // Check if email already exists
            $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($user->getEmail());
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

            // Get form data
            $plainPassword = $form->get('plainPassword')->getData();
            $normalizedEmail = strtolower(trim((string) $user->getEmail()));
            $user->setEmail($normalizedEmail);
            $user->setRole('CLIENT');
            $user->setIsActive(true);

            $passwordHash = $this->passwordHasher->hashPassword($user, (string) $plainPassword);
            $user->setPasswordHash($passwordHash);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $client = new Client();
            $client->setUser($user);

            $cin = trim((string) $form->get('cin')->getData());
            $phone = trim((string) $form->get('phone')->getData());
            if ($cin !== '') {
                $client->setCin($cin);
            }
            if ($phone !== '') {
                $client->setPhone($phone);
            }

            $this->entityManager->persist($client);
            $this->entityManager->flush();

            if ($isAjax) {
                return $this->json([
                    'valid' => true,
                    'redirect' => $this->generateUrl('front_dashboard_index'),
                ]);
            }

            $this->addFlash('success', 'Compte cree avec succes. Connectez-vous pour continuer.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/register/validate', name: 'app_register_validate', methods: ['POST'])]
    public function registerValidate(Request $request): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json([
                'valid' => false,
                'errors' => ['general' => ['Requete invalide.']],
            ], 400);
        }

        if ($this->getUser()) {
            return $this->json([
                'valid' => false,
                'errors' => ['general' => ['Vous etes deja connecte.']],
            ], 400);
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->json([
                'valid' => false,
                'errors' => ['general' => ['Aucune donnee recue.']],
            ], 400);
        }

        if (!$form->isValid()) {
            return $this->json([
                'valid' => false,
                'errors' => $this->normalizeRegistrationFormErrors($form),
            ], 422);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($user->getEmail());
        if ($existingUser) {
            return $this->json([
                'valid' => false,
                'errors' => [
                    'email' => ['Email already registered.'],
                ],
            ], 422);
        }

        return $this->json(['valid' => true, 'errors' => []]);
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
                } elseif (in_array($fieldName, ['email', 'fullName', 'cin', 'phone'], true)) {
                    $field = $fieldName;
                }
            }

            $errors[$field][] = (string) $error->getMessage();
        }

        return $errors;
    }

    #[Route('/register/verify-code', name: 'app_register_verify_code', methods: ['GET', 'POST'])]
    public function verifyRegisterCode(): Response
    {
        $this->addFlash('info', 'La verification par code email est desactivee. Connectez-vous directement.');

        return $this->redirectToRoute('app_login');
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

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        if (!$this->isCsrfTokenValid('admin_edit_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid edit token.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        $fullName = trim((string) $request->request->get('full_name', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $isActive = (bool) $request->request->get('is_active', false);

        if ($email === '') {
            $this->addFlash('error', 'Email is required.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($email);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'This email is already used by another user.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        $user->setFullName($fullName !== '' ? $fullName : null);
        $user->setEmail($email);
        $user->setIsActive($isActive);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->addFlash('success', 'User updated successfully.');
        return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
    }

    #[Route('/legacy/admin/users/{id}/delete', name: 'legacy_admin_user_delete', methods: ['POST'])]
    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        if (!$this->isCsrfTokenValid('admin_delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');
        return $this->redirectToRoute('app_admin_dashboard', $this->getDashboardRedirectParams($request));
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
