<?php
namespace App\Form;

use App\Entity\Dossier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DossierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomDossier', TextType::class, [
                'label' => 'Nom du dossier',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : Documents personnels'],
            ])
            ->add('description', TextareaType::class, [
                'label'      => 'Description',
                'required'   => false,
                'empty_data' => null,
                'attr'       => ['class' => 'form-control', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dossier::class,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}
