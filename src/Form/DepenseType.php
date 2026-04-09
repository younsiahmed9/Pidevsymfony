<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\Depense;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class DepenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];

        $builder
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'attr' => ['class' => 'form-control', 'readonly' => true],
                'required' => false
            ])
            ->add('montant', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control', 'type' => 'text'],
                'required' => false
            ])
            ->add('dateDepense', DateType::class, [
                'label' => 'Date de la dépense',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'required' => false
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (Optionnel)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices'  => [
                    'Carte' => 'carte',
                    'Virement' => 'virement',
                    'Cash' => 'cash',
                ],
                'attr' => ['class' => 'form-control'],
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Depense::class,
            'user' => null,
        ]);
    }
}
