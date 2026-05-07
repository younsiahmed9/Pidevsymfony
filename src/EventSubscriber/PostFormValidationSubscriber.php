<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PostFormValidationSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_HTML_FIELDS = ['description', 'notes', 'message', 'content', 'body'];
    private const EXCLUDED_FROM_LENGTH_CHECK = ['facedescriptor', 'face_descriptor', 'facetemplate', 'face_template', 'g-recaptcha-response', 'h-captcha-response'];

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST')) {
            return;
        }

        if ($request->getContentTypeFormat() === 'json') {
            return;
        }

        if ($request->isXmlHttpRequest()) {
            return;
        }

        $payload = $request->request->all();
        if ($payload === []) {
            return;
        }

        $errors = [];
        $sanitized = $this->sanitizePayload($payload);
        $this->validatePayload($sanitized, $errors);
        $request->request->replace($sanitized);

        if ($errors === [] || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $session->getFlashBag()->add('form_error', 'Le formulaire contient des erreurs de saisie.');
        foreach (array_slice(array_unique($errors), 0, 5) as $error) {
            $session->getFlashBag()->add('form_error', $error);
        }

        $target = $request->headers->get('referer') ?: $request->getUri();
        $event->setResponse(new RedirectResponse($target));
    }

    private function sanitizePayload(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->sanitizePayload($value);
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                $normalized = preg_replace('/\s+/u', ' ', $trimmed);
                $result[$key] = $normalized ?? $trimmed;
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function validatePayload(array $payload, array &$errors): void
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $this->validatePayload($value, $errors);
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $field = strtolower((string) $key);
            $stringValue = (string) ($value ?? '');

            if ($stringValue === '' || str_starts_with($field, '_')) {
                continue;
            }

            $this->validateNoUnsafeHtml($field, $stringValue, $errors);
            $this->validateLength($field, $stringValue, $errors);
            $this->validateCommonFormats($field, $stringValue, $errors);
        }
    }

    private function validateNoUnsafeHtml(string $field, string $value, array &$errors): void
    {
        if (in_array($field, self::ALLOWED_HTML_FIELDS, true)) {
            return;
        }

        if ($value !== strip_tags($value)) {
            $errors[] = sprintf('Le champ "%s" ne doit pas contenir de balises HTML.', $field);
        }
    }

    private function validateLength(string $field, string $value, array &$errors): void
    {
        if (in_array($field, self::EXCLUDED_FROM_LENGTH_CHECK, true)) {
            return;
        }

        $maxLength = in_array($field, self::ALLOWED_HTML_FIELDS, true) ? 3000 : 255;
        if (mb_strlen($value) > $maxLength) {
            $errors[] = sprintf('Le champ "%s" depasse %d caracteres.', $field, $maxLength);
        }
    }

    private function validateCommonFormats(string $field, string $value, array &$errors): void
    {
        if ($field === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        if (in_array($field, ['nom', 'prenom', 'fullname', 'full_name'], true)
            && preg_match('/^[\p{L}\s\-\']+$/u', $value) !== 1) {
            $errors[] = sprintf('Le champ "%s" contient des caracteres invalides.', $field);
        }

        if (in_array($field, ['telephone', 'phone', 'mobile', 'tel'], true)
            && preg_match('/^\+?[0-9\s\-]{8,15}$/', $value) !== 1) {
            $errors[] = 'Numero de telephone invalide.';
        }

        if ($this->isNumericField($field) && !is_numeric($value)) {
            $errors[] = sprintf('Le champ "%s" doit etre numerique.', $field);
        }

        if (str_contains($field, 'taux') && is_numeric($value)) {
            $numericValue = (float) $value;
            if ($numericValue < 0 || $numericValue > 100) {
                $errors[] = sprintf('Le champ "%s" doit etre compris entre 0 et 100.', $field);
            }
        }

        if ($this->isDateField($field)
            && \DateTimeImmutable::createFromFormat('Y-m-d', $value) === false
            && strtotime($value) === false) {
            $errors[] = sprintf('Le champ "%s" doit etre une date valide.', $field);
        }

        if ($field === 'password' && mb_strlen($value) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caracteres.';
        }

        if ($this->isIdField($field) && preg_match('/^[0-9]+$/', $value) !== 1) {
            $errors[] = sprintf('Le champ "%s" doit etre un identifiant numerique valide.', $field);
        }
    }

    private function isNumericField(string $field): bool
    {
        // Keep budget labels (e.g. nom_budget) as text fields.
        if (str_starts_with($field, 'nom_') || str_contains($field, '_nom')) {
            return false;
        }

        foreach (['montant', 'solde', 'taux', 'mensualite', 'plafond', 'prix'] as $fragment) {
            if (str_contains($field, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function isDateField(string $field): bool
    {
        return str_contains($field, 'date') || str_ends_with($field, '_at');
    }

    private function isIdField(string $field): bool
    {
        if (str_starts_with($field, 'id_') || str_ends_with($field, '_id')) {
            return true;
        }

        return in_array($field, ['utilisateur', 'compte', 'document', 'credit', 'categorie'], true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1],
        ];
    }
}
