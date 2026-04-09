<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RequireServiceOrProduitValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RequireServiceOrProduit) {
            throw new UnexpectedTypeException($constraint, RequireServiceOrProduit::class);
        }

        // null is valid
        if (null === $value) {
            return;
        }

        // S'assurer que c'est une Facture
        if (!method_exists($value, 'getService') || !method_exists($value, 'getProduit')) {
            return;
        }

        $service = $value->getService();
        $produit = $value->getProduit();

        if (null === $service && null === $produit) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }

        if (null !== $service && null !== $produit) {
            $this->context->buildViolation("Une facture ne peut pas être liée à la fois à un service ET à un produit.")
                ->addViolation();
        }
    }
}
