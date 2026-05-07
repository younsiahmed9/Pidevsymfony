<?php

namespace App\Form;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
final class TagAutocompleteField extends AbstractType
{
    public function __construct(private TagRepository $tagRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($tags) {
                return $tags instanceof Collection ? $tags->toArray() : $tags;
            },
            function ($values) {
                if (!$values) {
                    return [];
                }

                $tags = [];
                foreach ($values as $value) {
                    if ($value instanceof Tag) {
                        $tags[] = $value;
                    } elseif (is_string($value)) {
                        // This handles the 'create' option from TomSelect
                        // We use the repository to find or create the tag entity
                        $tags[] = $this->tagRepository->findOrCreateByName($value);
                    } else {
                        $tags[] = $value;
                    }
                }

                return $tags;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Tag::class,
            'multiple' => true,
            'choice_label' => 'nomTag',
            'placeholder' => 'Rechercher ou créer des tags',
            'min_characters' => 1,
            'max_results' => 20,
            'preload' => false,
            'security' => 'ROLE_USER',
            'filter_query' => function (QueryBuilder $qb, string $query, EntityRepository $repository): void {
                $this->tagRepository->applyAutocompleteFilter($qb, $query);
            },
            'tom_select_options' => [
                'create' => true,
                'createOnBlur' => true,
                'persist' => false,
                'plugins' => [
                    'remove_button',
                ],
            ],
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}