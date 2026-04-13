<?php

namespace App\Form;

use App\Entity\Compte;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminCompteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user) => sprintf('%s (%s)', $user->getFullName() ?: 'Sans nom', $user->getEmail() ?: 'N/A'),
                'placeholder' => '-- Sélectionner un utilisateur --',
                'label' => 'Utilisateur',
            ])
            ->add('numeroCompte', TextType::class, [
                'label' => 'Numéro de Compte',
            ])
            ->add('typeCompte', ChoiceType::class, [
                'label' => 'Type de Compte',
                'choices' => [
                    'Courant' => 'courant',
                    'Épargne' => 'epargne',
                ],
            ])
            ->add('solde', TextType::class, [
                'label' => 'Solde (TND)',
            ])
            ->add('etat', ChoiceType::class, [
                'label' => 'État',
                'choices' => [
                    'Actif' => 'actif',
                    'Bloqué' => 'bloque',
                    'Clos' => 'clos',
                ],
            ])
            ->add('tauxInteret', TextType::class, [
                'label' => 'Taux d\'intérêt (%)',
                'required' => false,
            ])
            ->add('plafondDecouvert', TextType::class, [
                'label' => 'Plafond Découvert (TND)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Compte::class,
        ]);
    }
}
