<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomService', TextType::class, [
                'label' => 'Nom du service',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom du service']
            ])
            ->add('tarif', NumberType::class, [
                'label' => 'Tarif',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Tarif en DT', 'step' => '0.01']
            ])
            ->add('typeService', ChoiceType::class, [
                'label' => 'Type de service',
                'choices' => [
                    'Abonnement' => 'abonnement',
                    'Facture' => 'facture'
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('frequence', ChoiceType::class, [
                'label' => 'Fréquence',
                'choices' => [
                    'Mensuel' => 'mensuel',
                    'Annuel' => 'annuel'
                ],
                'required' => false,
                'placeholder' => 'Choisir une fréquence',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => 'actif',
                    'Suspendu' => 'suspendu',
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
            'data_class' => Service::class,
        ]);
    }
}
