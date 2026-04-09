<?php
namespace App\Entity\Enum;

enum StatutService: string
{
    case ACTIF = 'actif';
    case SUSPENDU = 'suspendu';
    case EXPIRE = 'expire';
}