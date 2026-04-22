<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Dossier;
use App\Entity\Document;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;
use Symfony\Component\Validator\Constraints\File;

final class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Facture EDF janvier',
                ],
            ])
            ->add('typeDocument', ChoiceType::class, [
                'choices' => [
                    'Contrat' => 'contrat',
                    'Facture' => 'facture',
                    'Relevé' => 'releve',
                    'Identité' => 'identite',
                    'Assurance' => 'assurance',
                    'Fiscal' => 'fiscal',
                    'Autre' => 'autre',
                ],
            ])
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('dossier', EntityType::class, [
                'class' => Dossier::class,
                'required' => false,
                'placeholder' => 'Aucun dossier',
            ])
            ->add('dateDocument', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateEcheance', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Valide' => 'valide',
                    'Expiré' => 'expire',
                    'À renouveler' => 'a_renouveler',
                    'Archivé' => 'archive',
                ],
            ])
            ->add('fichier', DropzoneType::class, [
                'mapped' => false,
                'required' => !$options['is_edit'],
                'label' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '12M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez importer un fichier PDF, JPG ou PNG valide.',
                    ]),
                ],
                'attr' => [
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                    'data-document-dropzone-placeholder-value' => 'Drag and drop your invoice or click to browse',
                    'data-document-dropzone-icon-value' => 'fa-solid fa-file-arrow-up',
                ],
            ])
            ->add('tags', TagAutocompleteField::class, [
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'csrf_protection' => true,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}