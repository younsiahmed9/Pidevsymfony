<?php
namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DateSuperior extends Constraint
{
    public string $propertyPath;
    public string $message = 'La date de fin doit être postérieure à la date de début.';
}