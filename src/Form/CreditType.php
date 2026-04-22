<?php
namespace App\Form;
use App\Entity\Credit;
use App\Entity\Compte;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('compte', EntityType::class, [
                'class'       => Compte::class,
                'label'       => 'Compte',
                'placeholder' => '— Sélectionnez un compte —',
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant (DT)',
                'scale' => 2,
                'attr'  => ['class' => 'form-control', 'placeholder' => '0.00'],
            ])
            ->add('tauxInteret', NumberType::class, [
                'label' => 'Taux d\'intérêt (%)',
                'scale' => 2,
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : 8.50'],
            ])
            ->add('dureeMois', IntegerType::class, [
                'label' => 'Durée (mois)',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : 36'],
            ])
            ->add('dateDebut', DateType::class, [
                'label'  => 'Date de début',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Approuvé'   => 'approuve',
                    'Refusé'     => 'refuse',
                    'Remboursé'  => 'rembourse',
                ],
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Credit::class,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}
