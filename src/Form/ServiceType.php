<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomService', TextType::class, [
                'label' => 'Nom du Service',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('typeService', ChoiceType::class, [
                'label' => 'Type de Service',
                'choices' => [
                    'Abonnement' => 'abonnement',
                    'Facture' => 'facture',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('tarif', MoneyType::class, [
                'label' => 'Tarif',
                'currency' => 'USD',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('frequence', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Mensuel' => 'mensuel',
                    'Annuel' => 'annuel',
                ],
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de Début',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de Fin',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => 'actif',
                    'Suspendu' => 'suspendu',
                    'Expiré' => 'expire',
                ],
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
