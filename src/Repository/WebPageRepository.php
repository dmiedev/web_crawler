<?php

namespace App\Repository;

use App\Entity\WebPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebPage>
 *
 * @method WebPage|null find($id, $lockMode = null, $lockVersion = null)
 * @method WebPage|null findOneBy(array $criteria, array $orderBy = null)
 * @method WebPage[]    findAll()
 * @method WebPage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WebPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebPage::class);
    }

//    /**
//     * @return WebPage[] Returns an array of WebPage objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?WebPage
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
