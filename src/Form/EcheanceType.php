<?php
namespace App\Form;

use App\Entity\Echeance;
use App\Entity\Document;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EcheanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('document', EntityType::class, [
                'class'       => Document::class,
                // No choice_label needed — Document::__toString() returns titre
                'label'       => 'Document associé',
                'placeholder' => '— Sélectionnez un document —',
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('titre', TextType::class, [
                'label' => "Titre de l'échéance",
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : Renouvellement assurance'],
            ])
            ->add('dateEcheance', DateType::class, [
                'label'  => "Date d'échéance",
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('dateRappel', DateType::class, [
                'label'    => 'Date de rappel',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'Notifié'    => 'notified',
                    'Complété'   => 'completed',
                    'En retard'  => 'overdue',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('montant', NumberType::class, [
                'label'    => 'Montant (TND)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Ex : 150.00'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Echeance::class]);
    }
}
