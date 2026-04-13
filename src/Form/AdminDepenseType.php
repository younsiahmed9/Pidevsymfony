<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminDepenseType extends AbstractType
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
            ->add('id_budget', ChoiceType::class, [
                'label' => 'Budget (Optionnel)',
                'choices' => $options['budget_choices'],
                'placeholder' => '-- Aucun budget --',
                'required' => false,
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'constraints' => [new NotBlank(message: 'La catégorie est obligatoire.')],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant (TND)',
                'scale' => 2,
                'constraints' => [new NotBlank(message: 'Le montant est obligatoire.')],
            ])
            ->add('date_depense', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'string',
                'format' => 'yyyy-MM-dd',
            ])
            ->add('mode_paiement', ChoiceType::class, [
                'label' => 'Mode de Paiement',
                'choices' => [
                    'Virement' => 'virement',
                    'Espèces' => 'espece',
                    'Carte Bancaire' => 'carte',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (Optionnel)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user_choices' => [],
            'budget_choices' => [],
        ]);
    }
}
