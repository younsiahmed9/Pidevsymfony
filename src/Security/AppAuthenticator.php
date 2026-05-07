<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AppAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;

    private const LOGIN_ROUTE = 'app_login';
    private const CSRF_TOKEN_ID = 'authenticate';

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Authenticate a request
     */
    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        if (!$email || !$password) {
            throw new CustomUserMessageAuthenticationException('Missing email or password');
        }

        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                $user = $this->entityManager->getRepository(User::class)->findByEmail($userIdentifier);

                if (!$user) {
                    throw new UserNotFoundException(sprintf('User "%s" not found.', $userIdentifier));
                }

                if (!$user->isActive()) {
                    throw new AuthenticationException('User account is inactive.');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [new CsrfTokenBadge(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token', ''))]
        );
    }

    /**
     * Handle successful authentication
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        // Redirect based on role
        if ($user->getRole() === 'ADMIN') {
            return new RedirectResponse($this->urlGenerator->generate('admin_index'));
        }

        // Default for CLIENT or other roles
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    /**
     * Handle authentication failure
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    /**
     * Entry point for missing authentication
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    /**
     * Supports form login
     */
    public function supports(Request $request): bool
    {
        return $request->getPathInfo() === '/login' && $request->isMethod('POST');
    }
}
