<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminProduitType extends AbstractType
{
    private const TYPE_CHOICES = [
        'Carte Prépayée' => 'carte_prepaye',
        'Carte Cadeaux' => 'carte_cadeaux',
        'Carte Abonnement' => 'carte_abonnement',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user_id', ChoiceType::class, [
                'label' => 'Propriétaire',
                'choices' => $options['user_choices'],
                'placeholder' => '-- Sélectionner un utilisateur --',
                'constraints' => [new NotBlank(message: 'Veuillez sélectionner un utilisateur.')],
            ])
            ->add('nomProduit', TextType::class, [
                'label' => 'Nom du Produit',
                'constraints' => [new NotBlank(message: 'Le nom du produit est obligatoire.')],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Prix / Valeur (TND)',
                'scale' => 2,
                'constraints' => [new NotBlank(message: 'Le montant est obligatoire.')],
            ])
            ->add('codeUnique', TextType::class, [
                'label' => 'Code Unique',
                'required' => false,
            ])
            ->add('typeProduit', ChoiceType::class, [
                'label' => 'Type de Carte',
                'choices' => self::TYPE_CHOICES,
                'placeholder' => '-- Sélectionner un type --',
                'constraints' => [new NotBlank(message: 'Le type du produit est obligatoire.')],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Disponible' => 'disponible',
                    'Vendu' => 'vendu',
                    'Expiré' => 'expire',
                ],
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
