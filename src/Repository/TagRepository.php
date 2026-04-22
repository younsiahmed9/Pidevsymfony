<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function searchAutocomplete(string $query = '', int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t');
        $normalizedQuery = mb_strtolower(trim($query));

        if ($normalizedQuery !== '') {
            $qb->andWhere('LOWER(t.nomTag) LIKE :starts OR LOWER(t.nomTag) LIKE :contains')
                ->setParameter('starts', $normalizedQuery.'%')
                ->setParameter('contains', '%'.$normalizedQuery.'%');
        }

        return $qb
            ->orderBy('t.nomTag', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByNormalizedName(string $name): ?Tag
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.nomTag) = :name')
            ->setParameter('name', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOrCreateByName(string $name): ?Tag
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return null;
        }

        $tag = $this->findOneByNormalizedName($normalized);
        if ($tag instanceof Tag) {
            return $tag;
        }

        $tag = new Tag();
        $tag->setNomTag($normalized);
        $this->getEntityManager()->persist($tag);

        return $tag;
    }

    public function applyAutocompleteFilter(QueryBuilder $qb, string $query): void
    {
        $alias = $qb->getRootAliases()[0];
        $normalizedQuery = mb_strtolower(trim($query));

        if ($normalizedQuery === '') {
            return;
        }

        $qb->andWhere(sprintf('LOWER(%s.nomTag) LIKE :starts OR LOWER(%s.nomTag) LIKE :contains', $alias, $alias))
            ->setParameter('starts', $normalizedQuery.'%')
            ->setParameter('contains', '%'.$normalizedQuery.'%');
    }
}