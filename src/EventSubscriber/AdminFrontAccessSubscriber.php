<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminFrontAccessSubscriber implements EventSubscriberInterface
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
        if (!$user instanceof User) {
            return;
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if ($route === '' || !str_starts_with($route, 'front_')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_index')));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
