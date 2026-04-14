<?php

namespace App\Form;

use App\Entity\Compte;
use App\Entity\Credit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminCreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('compte', EntityType::class, [
                'class' => Compte::class,
                'choice_label' => static function (Compte $compte): string {
                    $user = $compte->getUtilisateur();
                    $owner = $user->getFullName() ?: $user->getEmail() ?: 'N/A';
                    return sprintf('%s (%s)', $compte->getNumeroCompte(), $owner);
                },
                'placeholder' => '-- Sélectionner un compte --',
                'label' => 'Compte Bénéficiaire',
            ])
            ->add('montant', TextType::class, [
                'label' => 'Montant du Crédit (TND)',
            ])
            ->add('tauxInteret', TextType::class, [
                'label' => 'Taux d\'intérêt (%)',
            ])
            ->add('dureeMois', TextType::class, [
                'label' => 'Durée (Mois)',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Approuvé' => 'approuve',
                    'Refusé' => 'refuse',
                    'Remboursé' => 'rembourse',
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Credit::class,
        ]);
    }
}
