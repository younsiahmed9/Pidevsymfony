<?php
namespace App\Form;
use App\Entity\Compte;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroCompte', TextType::class, [
                'label' => 'Numéro de compte',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : ACC-00123'],
            ])
            ->add('typeCompte', ChoiceType::class, [
                'label'       => 'Type de compte',
                'choices'     => ['Courant' => 'courant', 'Épargne' => 'epargne'],
                'placeholder' => '— Sélectionnez le type —',
                'attr'        => ['class' => 'form-select', 'id' => 'compte_typeCompte'],
            ])
            ->add('solde', NumberType::class, [
                'label' => 'Solde initial (DT)',
                'scale' => 2,
                'attr'  => ['class' => 'form-control', 'placeholder' => '0.00'],
            ])
            ->add('tauxInteret', NumberType::class, [
                'label'      => 'Taux d\'intérêt (%)',
                'required'   => false,
                'scale'      => 2,
                'empty_data' => null,
                'attr'       => ['class' => 'form-control', 'placeholder' => 'Ex : 3.5', 'id' => 'champ_taux'],
            ])
            ->add('plafondDecouvert', NumberType::class, [
                'label'      => 'Plafond de découvert (DT)',
                'required'   => false,
                'scale'      => 2,
                'empty_data' => null,
                'attr'       => ['class' => 'form-control', 'placeholder' => 'Ex : 500.00', 'id' => 'champ_plafond'],
            ])
            ->add('dateCreation', DateType::class, [
                'label'  => 'Date de création',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('etat', ChoiceType::class, [
                'label'   => 'État',
                'choices' => ['Actif' => 'actif', 'Bloqué' => 'bloque', 'Clos' => 'clos'],
                'attr'    => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Compte::class,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}
