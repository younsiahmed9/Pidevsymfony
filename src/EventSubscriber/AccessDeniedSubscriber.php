<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Ne gérer que les AccessDeniedException directes
        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        // Si l'utilisateur n'est pas connecté, rediriger vers la page de login
        if (!$user) {
            $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
            $event->setResponse($response);
            return;
        }

        // Vérifier si c'est une route du front office (produit, service, etc.)
        $route = $request->attributes->get('_route', '');
        $frontOfficeRoutes = ['produit_new', 'service_new', 'facture_new', 'compte_new', 'budget_new'];
        
        if (in_array($route, $frontOfficeRoutes)) {
            // Pour les routes front office, ne pas bloquer l'accès
            return;
        }

        // Si l'utilisateur essaie d'accéder au back office sans être admin
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            // Ajouter un message flash
            $request->getSession()->getFlashBag()->add('error', 'Accès refusé. Vous devez être administrateur pour accéder au back office.');
            
            // Rediriger vers le dashboard front office
            $response = new RedirectResponse($this->urlGenerator->generate('front_dashboard_index'));
            $event->setResponse($response);
            return;
        }

        // Pour les autres accès refusés, rediriger vers le dashboard avec un message
        $request->getSession()->getFlashBag()->add('error', 'Accès refusé. Vous n\'avez pas les permissions nécessaires.');
        $response = new RedirectResponse($this->urlGenerator->generate('front_dashboard_index'));
        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10], // Priorité basse pour laisser Symfony gérer d'abord
        ];
    }
}
