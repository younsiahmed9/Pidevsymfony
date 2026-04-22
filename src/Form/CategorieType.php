<?php
namespace App\Form;

use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategorieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomCategorie', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : Identité, Banque…'],
            ])
            ->add('icon', TextType::class, [
                'label'      => 'Icône (classe Font Awesome)',
                'required'   => false,
                'empty_data' => null,
                'attr'       => [
                    'class'       => 'form-control',
                    'placeholder' => 'Ex : fas fa-id-card',
                    'id'          => 'cat_icon',
                ],
            ])
            ->add('couleur', TextType::class, [
                'label'      => 'Couleur (code hex)',
                'required'   => false,
                'empty_data' => null,
                'attr'       => [
                    'class'       => 'form-control',
                    'placeholder' => '#3b82f6',
                    'maxlength'   => '7',
                    'id'          => 'cat_couleur',
                ],
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
            'data_class' => Categorie::class,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}
