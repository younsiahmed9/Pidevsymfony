<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user_id', ChoiceType::class, [
                'label' => 'Utilisateur',
                'choices' => $options['user_choices'],
                'placeholder' => '-- Sélectionner un utilisateur --',
                'constraints' => [new NotBlank(message: 'Veuillez sélectionner un utilisateur.')],
            ])
            ->add('nomService', TextType::class, [
                'label' => 'Nom du Service',
                'constraints' => [new NotBlank(message: 'Le nom du service est obligatoire.')],
            ])
            ->add('tarif', NumberType::class, [
                'label' => 'Tarif (TND)',
                'scale' => 2,
                'constraints' => [new NotBlank(message: 'Le tarif est obligatoire.')],
            ])
            ->add('typeService', ChoiceType::class, [
                'label' => 'Type de Service',
                'choices' => [
                    'Abonnement' => 'abonnement',
                    'Facture' => 'facture',
                ],
                'placeholder' => '-- Sélectionner un type --',
                'constraints' => [new NotBlank(message: 'Le type du service est obligatoire.')],
            ])
            ->add('frequence', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Unique' => 'unique',
                    'Mensuel' => 'mensuel',
                    'Trimestriel' => 'trimestriel',
                    'Annuel' => 'annuel',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => 'actif',
                    'Suspendu' => 'suspendu',
                    'Expiré' => 'expire',
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de Début',
                'widget' => 'single_text',
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de Fin (Optionnel)',
                'widget' => 'single_text',
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
                'required' => false,
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user_choices' => [],
        ]);
    }
}
