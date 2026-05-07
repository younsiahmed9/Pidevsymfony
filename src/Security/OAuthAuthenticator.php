<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OAuthAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $route = (string) $request->attributes->get('_route', '');

        return in_array($route, ['connect_google_check', 'connect_github_check', 'connect_facebook_check'], true);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $route = (string) $request->attributes->get('_route', '');
        $providerName = $this->resolveProviderNameFromRoute($route);
        $client = $this->clientRegistry->getClient($providerName);
        $accessToken = $this->fetchAccessToken($client);
        $resourceOwner = $client->fetchUserFromToken($accessToken);

        $oauthId = $this->extractResourceOwnerId($resourceOwner);
        $email = $this->extractResourceOwnerEmail($resourceOwner);

        if ($oauthId === null) {
            throw new CustomUserMessageAuthenticationException('Impossible de recuperer votre identifiant OAuth.');
        }

        return new SelfValidatingPassport(
            new UserBadge($providerName . ':' . $oauthId, function () use ($providerName, $oauthId, $email, $resourceOwner): User {
                $userRepository = $this->entityManager->getRepository(User::class);

                $existingByProvider = $userRepository->findOneBy([
                    'oauthProvider' => $providerName,
                    'oauthId' => $oauthId,
                ]);
                if ($existingByProvider instanceof User) {
                    if (!$existingByProvider->isActive()) {
                        throw new CustomUserMessageAuthenticationException('Votre compte a ete bloque.');
                    }

                    return $existingByProvider;
                }

                if ($email !== null) {
                    $existingByEmail = $userRepository->findOneBy(['email' => $email]);
                    if ($existingByEmail instanceof User) {
                        if (!$existingByEmail->isActive()) {
                            throw new CustomUserMessageAuthenticationException('Votre compte a ete bloque.');
                        }

                        if ($existingByEmail->getOauthProvider() === null || $existingByEmail->getOauthId() === null) {
                            $existingByEmail->setOauthProvider($providerName);
                            $existingByEmail->setOauthId($oauthId);
                            $existingByEmail->setUpdatedAt(new \DateTimeImmutable());
                            $this->entityManager->flush();
                        }

                        return $existingByEmail;
                    }
                }

                if ($email === null) {
                    throw new CustomUserMessageAuthenticationException('Votre compte social ne fournit pas d email. Utilisez la connexion classique.');
                }

                $newUser = new User();
                $newUser->setEmail($email);
                $newUser->setFullName($this->extractResourceOwnerName($resourceOwner));
                $newUser->setRole('CLIENT');
                $newUser->setIsActive(true);
                $newUser->setOauthProvider($providerName);
                $newUser->setOauthId($oauthId);

                $newUser->setPasswordHash($this->passwordHasher->hashPassword(
                    $newUser,
                    bin2hex(random_bytes(32))
                ));

                $this->entityManager->persist($newUser);
                $this->entityManager->flush();

                return $newUser;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        if ($user->getRole() === 'ADMIN') {
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

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Connexion OAuth impossible: ' . $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function resolveProviderNameFromRoute(string $route): string
    {
        return match ($route) {
            'connect_google_check' => 'google',
            'connect_github_check' => 'github',
            'connect_facebook_check' => 'facebook',
            default => throw new CustomUserMessageAuthenticationException('Provider OAuth inconnu.'),
        };
    }

    private function extractResourceOwnerId(ResourceOwnerInterface $resourceOwner): ?string
    {
        if (method_exists($resourceOwner, 'getId')) {
            return (string) $resourceOwner->getId();
        }

        $data = $resourceOwner->toArray();

        return isset($data['id']) ? (string) $data['id'] : null;
    }

    private function extractResourceOwnerEmail(ResourceOwnerInterface $resourceOwner): ?string
    {
        if (method_exists($resourceOwner, 'getEmail')) {
            $email = $resourceOwner->getEmail();
            if (is_string($email) && trim($email) !== '') {
                return strtolower(trim($email));
            }
        }

        $data = $resourceOwner->toArray();
        if (isset($data['email']) && is_string($data['email']) && trim($data['email']) !== '') {
            return strtolower(trim($data['email']));
        }

        return null;
    }

    private function extractResourceOwnerName(ResourceOwnerInterface $resourceOwner): ?string
    {
        if (method_exists($resourceOwner, 'getName')) {
            $name = $resourceOwner->getName();
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        if (method_exists($resourceOwner, 'getNickname')) {
            $nickname = $resourceOwner->getNickname();
            if (is_string($nickname) && trim($nickname) !== '') {
                return trim($nickname);
            }
        }

        $data = $resourceOwner->toArray();

        if (isset($data['name']) && is_string($data['name']) && trim($data['name']) !== '') {
            return trim($data['name']);
        }

        return null;
    }
}
