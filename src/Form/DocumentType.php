<?php
namespace App\Form;

use App\Entity\Document;
use App\Entity\Categorie;
use App\Entity\Dossier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', EntityType::class, [
                'class'       => Categorie::class,
                'label'       => 'Catégorie',
                'required'    => false,
                'placeholder' => '— Aucune catégorie —',
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('dossier', EntityType::class, [
                'class'       => Dossier::class,
                'label'       => 'Dossier',
                'required'    => false,
                'placeholder' => '— Aucun dossier —',
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Titre du document'],
            ])
            ->add('typeDocument', ChoiceType::class, [
                'label'       => 'Type de document',
                'choices'     => [
                    'Identité'        => 'identite',
                    'Contrat'         => 'contrat',
                    'Facture'         => 'facture',
                    'Relevé bancaire' => 'releve',
                    'Assurance'       => 'assurance',
                    'Fiscal'          => 'fiscal',
                    'Autre'           => 'autre',
                ],
                'placeholder' => '— Choisissez un type —',
                'attr'        => ['class' => 'form-select'],
            ])
            ->add('fichier', FileType::class, [
                'label'       => 'Fichier (PDF, DOC, JPG, PNG…)',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '10M',
                        'mimeTypesMessage' => 'Veuillez téléverser un fichier valide.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateDocument', DateType::class, [
                'label'    => 'Date du document',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('dateEcheance', DateType::class, [
                'label'    => "Date d'échéance",
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'Valide'       => 'valide',
                    'Expiré'       => 'expire',
                    'À renouveler' => 'a_renouveler',
                    'Archivé'      => 'archive',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('description', TextareaType::class, [
                'label'      => 'Description',
                'required'   => false,
                'empty_data' => null,
                'attr'       => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('tags', TextType::class, [
                'label'      => 'Tags',
                'required'   => false,
                'empty_data' => null,
                'attr'       => ['class' => 'form-control', 'placeholder' => 'tag1, tag2, tag3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}
