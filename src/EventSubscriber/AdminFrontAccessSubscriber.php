<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class AdminFrontAccessSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $locator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Resolve Security lazily to avoid circular dependency with Router / Firewall
        $security = $this->locator->get('security');
        $user = $security->getUser();

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

    public static function getSubscribedServices(): array
    {
        return [
            'security' => \Symfony\Bundle\SecurityBundle\Security::class,
        ];
    }
}
