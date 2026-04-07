<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Client;
use App\Entity\User;
use App\Form\RegistrationType;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('home/index.html.twig');
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
    public function register(Request $request, UserAuthenticatorInterface $userAuthenticator, AppAuthenticator $appAuthenticator): Response
    {
        // If user is already authenticated, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email already exists
            $existingUser = $this->entityManager->getRepository(User::class)->findByEmail($user->getEmail());
            if ($existingUser) {
                $this->addFlash('error', 'Email already registered.');
                return $this->redirectToRoute('app_register');
            }

            // Get form data
            $plainPassword = $form->get('plainPassword')->getData();
            $roleChoice = $form->get('roleChoice')->getData();
            $adminCode = (string) $form->get('adminCode')->getData();

            // Validate ADMIN signup
            if ($roleChoice === 'ADMIN') {
                $expectedAdminCode = (string) ($_ENV['ADMIN_CODE'] ?? $_SERVER['ADMIN_CODE'] ?? '');
                if ($expectedAdminCode === '' || !hash_equals(trim($expectedAdminCode), trim($adminCode))) {
                    $this->addFlash('error', 'Invalid admin code.');
                    return $this->redirectToRoute('app_register');
                }
                $user->setRole('ADMIN');
            } else {
                $user->setRole('CLIENT');
            }

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPasswordHash($hashedPassword);
            $user->setIsActive(true);

            // Persist user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create role-specific entity
            if ($roleChoice === 'ADMIN') {
                $admin = new Admin();
                $admin->setUser($user);
                $admin->setAdminCode($adminCode);
                $this->entityManager->persist($admin);
            } else {
                $client = new Client();
                $client->setUser($user);
                $cin = $form->get('cin')->getData();
                $phone = $form->get('phone')->getData();
                if ($cin) {
                    $client->setCin($cin);
                }
                if ($phone) {
                    $client->setPhone($phone);
                }
                $this->entityManager->persist($client);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Registration successful! You are now signed in.');
            return $userAuthenticator->authenticateUser($user, $appAuthenticator, $request);
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): Response
    {
        // This method can be blank - it will be handled by the logout in security.yaml
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
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
