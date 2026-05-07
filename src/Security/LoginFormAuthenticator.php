<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): User {
                $user = $this->userRepository->findByEmail($userIdentifier);

                if (!$user instanceof User) {
                    throw new UserNotFoundException(sprintf('User "%s" not found.', $userIdentifier));
                }

                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Votre compte a ete bloque.');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $request->getSession()->set(AdminFaceAuthSession::SESSION_KEY, [
                'email' => strtolower((string) $user->getUserIdentifier()),
                'verified' => false,
                'startedAt' => time(),
            ]);

            return new RedirectResponse($this->urlGenerator->generate('app_admin_face_id_challenge'));
        }

        $request->getSession()->remove(AdminFaceAuthSession::SESSION_KEY);

        return new RedirectResponse($this->urlGenerator->generate('front_dashboard_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}