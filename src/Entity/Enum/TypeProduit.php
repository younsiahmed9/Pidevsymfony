<?php
namespace App\Entity\Enum;

enum TypeProduit: string
{
    case CARTE_CADEAU = 'carte_cadeau';
    case CARTE_ABONNEMENT = 'carte_abonnement';
    case CARTE_PREPAYEE = 'carte_prepayee';
}