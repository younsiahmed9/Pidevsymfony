<?php

namespace App\Controller\FrontOffice;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart', name: 'cart_')]
final class CartController extends AbstractController
{
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('frontoffice/cart/index.html.twig');
    }
}
