<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidDateRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidDateRange) {
            throw new UnexpectedTypeException($constraint, ValidDateRange::class);
        }

        // null is valid
        if (null === $value) {
            return;
        }

        // S'assurer que c'est une Facture avec dateFacture et dateEcheance
        if (!method_exists($value, 'getDateFacture') || !method_exists($value, 'getDateEcheance')) {
            return;
        }

        $dateFacture = $value->getDateFacture();
        $dateEcheance = $value->getDateEcheance();

        if (null === $dateFacture || null === $dateEcheance) {
            return;
        }

        if ($dateEcheance <= $dateFacture) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
