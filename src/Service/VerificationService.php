<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service pour gérer la génération et la validation des codes de vérification SMS.
 */
class VerificationService
{
    private const SESSION_KEY_CODE = 'reallocate_verify_code';
    private const SESSION_KEY_DATA = 'reallocate_verify_data';
    private const SESSION_KEY_TIME = 'reallocate_verify_time';
    
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Génère un code à 6 chiffres et le stocke en session avec les données associées.
     */
    public function initiateVerification(array $data): string
    {
        $code = (string) rand(100000, 999999);
        $session = $this->requestStack->getSession();

        $session->set(self::SESSION_KEY_CODE, $code);
        $session->set(self::SESSION_KEY_DATA, $data);
        $session->set(self::SESSION_KEY_TIME, time());

        return $code;
    }

    /**
     * Vérifie si le code fourni est correct.
     */
    public function verifyCode(string $code): bool
    {
        $session = $this->requestStack->getSession();
        $storedCode = $session->get(self::SESSION_KEY_CODE);
        $storedTime = $session->get(self::SESSION_KEY_TIME);

        // Code valide pendant 10 minutes
        if (!$storedCode || (time() - $storedTime > 600)) {
            return false;
        }

        return $storedCode === $code;
    }

    /**
     * Récupère les données de transfert stockées.
     */
    public function getStoredData(): ?array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY_DATA);
    }

    /**
     * Nettoie la session après validation.
     */
    public function clearVerification(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY_CODE);
        $session->remove(self::SESSION_KEY_DATA);
        $session->remove(self::SESSION_KEY_TIME);
    }
}