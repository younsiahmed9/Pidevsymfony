<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class InactiveUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');

        if ($route !== '' && in_array($route, ['app_login', 'app_register', 'app_home', 'app_logout'], true)) {
            return;
        }

        if (str_starts_with($route, 'connect_')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isActive()) {
            return;
        }

        $this->tokenStorage->setToken(null);

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept', ''), 'application/json')) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => 'Votre compte a ete bloque.',
                'code' => 'account_blocked',
            ], JsonResponse::HTTP_LOCKED));

            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }
}