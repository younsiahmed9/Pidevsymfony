<?php
namespace App\Entity\Enum;

enum StatutProduit: string
{
    case DISPONIBLE = 'disponible';
    case VENDU = 'vendu';
    case EXPIRE = 'expire';
}