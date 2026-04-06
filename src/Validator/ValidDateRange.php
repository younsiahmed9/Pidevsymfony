<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidDateRange extends Constraint
{
    public string $message = 'La date d\'échéance doit être après la date de facture.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
