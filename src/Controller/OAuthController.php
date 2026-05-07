<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['openid', 'email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(): never
    {
        throw new \LogicException('This route is handled by OAuthAuthenticator.');
    }

    #[Route('/connect/github', name: 'connect_github_start', methods: ['GET'])]
    public function connectGithub(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('github')
            ->redirect(['read:user', 'user:email']);
    }

    #[Route('/connect/github/check', name: 'connect_github_check', methods: ['GET'])]
    public function connectGithubCheck(): never
    {
        throw new \LogicException('This route is handled by OAuthAuthenticator.');
    }

    #[Route('/connect/facebook', name: 'connect_facebook_start', methods: ['GET'])]
    public function connectFacebook(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('facebook')
            ->redirect(['email', 'public_profile']);
    }

    #[Route('/connect/facebook/check', name: 'connect_facebook_check', methods: ['GET'])]
    public function connectFacebookCheck(): never
    {
        throw new \LogicException('This route is handled by OAuthAuthenticator.');
    }
}
