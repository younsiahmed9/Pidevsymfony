<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\AdminFaceAuthSession;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminFaceGateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();

        if ($route === 'app_admin_face_id_challenge' || $route === 'app_face_id_login' || $route === 'app_logout') {
            return;
        }

        $sessionData = $request->hasSession() ? $request->getSession()->get(AdminFaceAuthSession::SESSION_KEY) : null;
        $verified = is_array($sessionData) && ($sessionData['verified'] ?? false) === true;

        if ($verified) {
            return;
        }

        if (str_starts_with($path, '/admin') || str_starts_with($route, 'front_') || $route === 'app_home' || $route === 'app_login' || $route === 'app_register') {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_admin_face_id_challenge')));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }
}
