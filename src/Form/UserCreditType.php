<?php
namespace App\Form;

use App\Entity\Credit;
use App\Entity\Compte;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserCreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('compte', EntityType::class, [
                'class'        => Compte::class,
                'label'        => 'Compte bancaire',
                'choice_label' => fn(Compte $c) => 'N° ' . $c->getNumeroCompte() . ' — ' . ucfirst($c->getTypeCompte()) . ' (' . number_format((float)$c->getSolde(), 2) . ' DT)',
                'placeholder'  => '— Sélectionnez un compte —',
                'attr'         => ['class' => 'form-select', 'id' => 'credit_compte'],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant demandé (DT)',
                'scale' => 2,
                'attr'  => ['class' => 'form-control', 'id' => 'credit_montant', 'placeholder' => 'Ex : 15 000', 'min' => '500', 'step' => '100'],
            ])
            ->add('tauxInteret', NumberType::class, [
                'label' => 'Taux d\'intérêt annuel (%)',
                'scale' => 2,
                'attr'  => ['class' => 'form-control', 'id' => 'credit_taux', 'placeholder' => 'Ex : 8.00', 'step' => '0.01', 'min' => '0.01', 'max' => '30'],
            ])
            ->add('dureeMois', IntegerType::class, [
                'label' => 'Durée (mois)',
                'attr'  => ['class' => 'form-control', 'id' => 'credit_duree', 'placeholder' => 'Ex : 36', 'min' => '1', 'max' => '360'],
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
