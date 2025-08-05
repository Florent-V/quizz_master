<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

/**
 * @extends NestedTreeRepository<Category>
 */
class CategoryRepository extends NestedTreeRepository
{
    public function __construct(
        EntityManagerInterface $manager,
    ) {
        parent::__construct($manager, $manager->getClassMetadata(Category::class));
    }

    /**
     * @return array<string,mixed>
     */
    public function getStatistics(int $categoryId): array
    {
        $category = $this->find($categoryId);

        if (!$category instanceof Category) {
            throw new \InvalidArgumentException('Catégorie non trouvée');
        }

        return [
            'category'          => $category,
            'direct_questions'  => $category->getQuestions()->count(),
            'total_questions'   => $category->getTotalQuestionsCount(),
            'children_count'    => $category->getChildren()->count(),
            'depth_level'       => $category->getLvl(),
            'descendants_count' => $category->getActiveChildrenCount(),
            'created_days_ago'  => $category->getCreatedAt() ?
                (new \DateTime())->diff($category->getCreatedAt())->days : 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function exportToArray(): array
    {
        $categories = $this->createQueryBuilder('c')
            ->leftJoin('c.questions', 'q')
            ->addSelect('q')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->orderBy('c.lft', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(function (Category $category) {
            return [
                'id'              => $category->getId(),
                'name'            => $category->getName(),
                'slug'            => $category->getSlug(),
                'description'     => $category->getDescription(),
                'icon'            => $category->getIcon(),
                'level'           => $category->getLvl(),
                'parent'          => $category->getParent() ? $category->getParent()->getName() : null,
                'questions_count' => $category->getQuestions()->count(),
                'children_count'  => $category->getChildren()->count(),
                'created_at'      => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at'      => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'is_deleted'      => null !== $category->getDeletedAt(),
            ];
        }, $categories);
    }

    public function getTotalCount(): int
    {
        $this->getEntityManager()->getFilters()->disable('softdeleteable');

        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $this->getEntityManager()->getFilters()->enable('softdeleteable');

        return (int) $count;
    }

    public function getDeletedCount(): int
    {
        $this->getEntityManager()->getFilters()->disable('softdeleteable');

        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.deletedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $this->getEntityManager()->getFilters()->enable('softdeleteable');

        return (int) $count;
    }

    /**
     * @return Category[]
     */
    public function search(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.name LIKE :term OR c.description LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
