<?php
namespace App\Entity\Enum;

enum StatutFacture: string
{
    case EN_ATTENTE = 'en_attente';
    case PAYEE = 'payee';
    case IMPAYEE = 'impayee';
}