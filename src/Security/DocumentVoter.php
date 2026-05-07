<?php

namespace App\Security;

use App\Entity\Document;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';
    public const EDIT = 'DOCUMENT_EDIT';
    public const CLASSIFY = 'DOCUMENT_CLASSIFY';
    public const DELETE = 'DOCUMENT_DELETE';
    public const DOWNLOAD = 'DOCUMENT_DOWNLOAD';
    public const ARCHIVE = 'DOCUMENT_ARCHIVE';
    public const RESTORE = 'DOCUMENT_RESTORE';
    public const APPROVE = 'DOCUMENT_APPROVE';
    public const SHARE = 'DOCUMENT_SHARE';
    public const SIGN = 'DOCUMENT_SIGN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::CLASSIFY,
            self::DELETE,
            self::DOWNLOAD,
            self::ARCHIVE,
            self::RESTORE,
            self::APPROVE,
            self::SHARE,
            self::SIGN,
        ], true) && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var Document $document */
        $document = $subject;
        $isOwner = $document->getUtilisateur()?->getId() === $user->getId();

        if (!$isOwner) {
            return false;
        }

        if ($document->isDeleted() && !in_array($attribute, [self::RESTORE], true)) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::DOWNLOAD, self::SHARE => true,
            self::CLASSIFY => !$document->isDeleted(),
            // Allow owners to edit their document even if archived; only deleted documents are blocked.
            self::EDIT => !$document->isDeleted(),
            self::DELETE, self::ARCHIVE, self::RESTORE => true,
            self::APPROVE, self::SIGN => !$document->isDeleted(),
            default => false,
        };
    }
}
