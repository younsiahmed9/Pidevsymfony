<?php

namespace App\Form;

use App\Entity\Facture;
use App\Entity\Produit;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
                'disabled' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control bg-light',
                    'placeholder' => 'Généré automatiquement (FAC-AAAA-MM-JJ-XXX)',
                ],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant (DT)',
                'disabled' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control bg-light',
                    'placeholder' => 'Calculé automatiquement',
                ],
            ])
            ->add('dateFacture', DateType::class, [
                'label' => 'Date de Facture',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateEcheance', DateType::class, [
                'label' => 'Date d\'Échéance',
                'widget' => 'single_text',
                'disabled' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control bg-light',
                    'placeholder' => 'Calculée automatiquement (+30 jours)',
                ],
            ])
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'nomService',
                'required' => false,
                'placeholder' => 'Choisir un service',
                'attr' => ['class' => 'form-control']
            ])
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nomProduit',
                'required' => false,
                'placeholder' => 'Choisir un produit',
                'attr' => ['class' => 'form-control']
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'En attente' => 'en_attente',
                    'Payée' => 'payee',
                    'Impayée' => 'impayee'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facture::class,
        ]);
    }
}
