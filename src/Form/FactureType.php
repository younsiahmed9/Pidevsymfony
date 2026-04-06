<?php

namespace App\Form;

use App\Entity\Facture;
use App\Entity\Produit;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroFacture', TextType::class, [
                'label' => 'Numéro de Facture',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('montant', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'USD',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateFacture', DateType::class, [
                'label' => 'Date de Facture',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateEcheance', DateType::class, [
                'label' => 'Date d\'Échéance',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('service', EntityType::class, [
                'label' => 'Service',
                'class' => Service::class,
                'choice_label' => 'nomService',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('produit', EntityType::class, [
                'label' => 'Produit',
                'class' => Produit::class,
                'choice_label' => 'nomProduit',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En Attente' => 'en_attente',
                    'Payée' => 'payee',
                    'Impayée' => 'impayee',
                ],
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facture::class,
        ]);
    }
}
