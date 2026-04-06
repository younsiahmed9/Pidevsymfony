<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomProduit', TextType::class, [
                'label' => 'Nom du Produit',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('typeProduit', ChoiceType::class, [
                'label' => 'Type de Produit',
                'choices' => [
                    'Carte Cadeau' => 'carte_cadeau',
                    'Carte Abonnement' => 'carte_abonnement',
                    'Carte Prépayée' => 'carte_prepayee',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('montant', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'USD',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('codeUnique', TextType::class, [
                'label' => 'Code Unique',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Disponible' => 'disponible',
                    'Vendu' => 'vendu',
                    'Expiré' => 'expire',
                ],
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
