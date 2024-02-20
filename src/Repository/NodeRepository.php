<?php

namespace App\Repository;

use App\Entity\Node;
use App\Entity\WebPage;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Node>
 *
 * @method Node|null find($id, $lockMode = null, $lockVersion = null)
 * @method Node|null findOneBy(array $criteria, array $orderBy = null)
 * @method Node[]    findAll()
 * @method Node[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Node::class);
    }

    public function findByWebPageIds(array $ids): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.owner', 'wp')
            ->andWhere('wp.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addOrderBy('n.crawlTime')
            ->getQuery()
            ->getResult();
    }

    public function deleteAllNodes(WebPage $owner): void
    {
        $this->createQueryBuilder('n')
            ->delete('App:Node', 'n')
            ->andWhere('n.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->execute();
    }

    public function createNewNode(
        WebPage $owner,
        string $url,
        ?string $title = null,
        ?Node $parent = null,
        ?DateTimeImmutable $crawTime = null,
    ): Node
    {
        $node = (new Node())
            ->setOwner($owner)
            ->setCrawlTime($crawTime)
            ->setUrl($url)
            ->setTitle($title);

        $parent?->addLink($node);

        $em = $this->getEntityManager();
        $em->persist($node);
        if ($parent != null) {
            $em->persist($parent);
        }

        return $node;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->_em;
    }

    /** @throws UniqueConstraintViolationException */
    public function saveChanges(): void
    {
        $this->getEntityManager()->flush();
    }

    public function addLink(Node $parent, Node $child): void
    {
        $parent->addLink($child);

        $em = $this->getEntityManager();
        $em->persist($parent);
        $em->persist($child);
    }

    /** @return Node[] */
    public function findNodes(): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('l')
            ->leftJoin('n.links', 'l')
            ->addOrderBy('n.crawlTime')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Node[] Returns an array of Node objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('n.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Node
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
