<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RequireServiceOrProduit extends Constraint
{
    public string $message = 'La facture doit être liée à au moins un Service ou un Produit.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
