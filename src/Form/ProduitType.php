<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomProduit', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom du produit']
            ])
            ->add('typeProduit', ChoiceType::class, [
                'label' => 'Type de produit',
                'choices' => [
                    'Carte Cadeau' => 'carte_cadeau',
                    'Carte Abonnement' => 'carte_abonnement',
                    'Carte Prépayée' => 'carte_prepayee'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Montant en DT', 'step' => '0.01']
            ])
            ->add('codeUnique', TextType::class, [
                'label' => 'Code unique',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Code unique (ex: CODE123)']
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Disponible' => 'disponible',
                    'Vendu' => 'vendu',
                    'Expiré' => 'expire'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary w-100 mt-3']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
