<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RequireServiceOrProduit extends Constraint
{
    public string $message = 'Veuillez sélectionner un produit OU un service';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
