<?php

namespace App\Form;

use App\Entity\Budget;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_budget', TextType::class, [
                'label' => 'Nom du Budget',
                'attr' => ['class' => 'form-control'],
                'required' => false
            ])
            ->add('montant_total', MoneyType::class, [
                'label' => 'Montant Totale',
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control', 'type' => 'text'],
                'required' => false
            ])
            ->add('periode', ChoiceType::class, [
                'label' => 'Période',
                'choices'  => [
                    'Mensuel' => 'mensuel',
                    'Annuel' => 'annuel',
                ],
                'attr' => ['class' => 'form-select'],
                'required' => false
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices'  => [
                    'Actif' => 'actif',
                    'Clôturé' => 'cloture',
                ],
                'attr' => ['class' => 'form-select'],
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Budget::class,
        ]);
    }
}
