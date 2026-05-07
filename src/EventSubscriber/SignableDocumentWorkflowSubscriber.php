<?php

namespace App\EventSubscriber;

use App\Entity\SignableDocumentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

final class SignableDocumentWorkflowSubscriber implements EventSubscriberInterface
{
    private const WORKFLOW_NAME = 'fintrack_signable_document';

    public static function getSubscribedEvents(): array
    {
        return [
            sprintf('workflow.%s.transition.sign', self::WORKFLOW_NAME) => 'validateSignTransition',
            sprintf('workflow.%s.completed.sign', self::WORKFLOW_NAME) => 'onSignCompleted',
        ];
    }

    public function validateSignTransition(TransitionEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof SignableDocumentInterface) {
            throw new \InvalidArgumentException('Le document ne supporte pas le workflow de signature.');
        }

        $context = $event->getContext();
        $missingFields = [];

        if (!$this->isNonEmptyString($context['document_hash'] ?? null)) {
            $missingFields[] = 'document_hash';
        }

        if (!isset($context['signer_id']) || (!is_int($context['signer_id']) && !ctype_digit((string) $context['signer_id']))) {
            $missingFields[] = 'signer_id';
        }

        if ($missingFields === []) {
            return;
        }

        throw new \InvalidArgumentException(implode(', ', array_map(
            static fn (string $field): string => sprintf('%s requis', $field),
            $missingFields
        )));
    }

    public function onSignCompleted(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof SignableDocumentInterface) {
            return;
        }

        $context = $event->getContext();
        $subject->setDocumentHash((string) $context['document_hash']);
        $subject->setSignedByUserId((int) $context['signer_id']);
        $subject->setSignedAt($this->resolveSignedAt($context['signed_at'] ?? null));
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function resolveSignedAt(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                // Fallback to current timestamp if provided input is invalid.
            }
        }

        return new \DateTimeImmutable();
    }
}
