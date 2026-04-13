<?php

namespace App\Form;

use App\Entity\Portefeuille;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class PortefeuilleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du portefeuille ne peut pas être vide.']),
                    new Length(['min' => 1, 'max' => 100, 'minMessage' => 'Le nom doit contenir au moins 1 caractère.', 'maxMessage' => 'Le nom ne peut pas dépasser 100 caractères.']),
                ],
            ])
            ->add('devise_principale', ChoiceType::class, [
                'choices' => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
            ])
            ->add('thumbnail', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Miniature du portefeuille',
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'mimeTypesMessage' => 'La miniature doit être une image JPG, PNG, WEBP ou GIF.',
                        'maxSizeMessage' => 'La miniature du portefeuille doit faire 5 Mo maximum.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Portefeuille::class,
        ]);
    }
}

