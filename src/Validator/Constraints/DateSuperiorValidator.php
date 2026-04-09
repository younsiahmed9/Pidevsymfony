<?php
namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DateSuperiorValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value || !$constraint->propertyPath) {
            return;
        }

        $object = $this->context->getObject();
        $startDate = $this->getPropertyValue($object, $constraint->propertyPath);

        if (!$startDate || !$value || $value <= $startDate) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

    private function getPropertyValue($object, string $propertyPath)
    {
        $getter = 'get' . ucfirst($propertyPath);
        if (method_exists($object, $getter)) {
            return $object->$getter();
        }
        return null;
    }
}