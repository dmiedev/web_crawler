<?php

namespace App\Repository;

use App\Entity\Execution;
use App\Entity\ExecutionStatus;
use App\Entity\WebPage;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Execution>
 *
 * @method Execution|null find($id, $lockMode = null, $lockVersion = null)
 * @method Execution|null findOneBy(array $criteria, array $orderBy = null)
 * @method Execution[]    findAll()
 * @method Execution[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Execution::class);
    }

    public function createNewExecution(WebPage $webPage): Execution
    {
        $execution = (new Execution())
            ->setWebPage($webPage)
            ->setStartTime(new DateTimeImmutable())
            ->setStatusEnum(ExecutionStatus::Running)
            ->setCrawledCount(0);

        $em = $this->getEntityManager();
        $em->persist($execution);
        $em->flush();

        return $execution;
    }

    public function finishExecution(Execution $execution, int $crawledCount): Execution
    {
        $execution
            ->setStatusEnum(ExecutionStatus::Finished)
            ->setEndTime(new DateTimeImmutable())
            ->setCrawledCount($crawledCount);

        $em = $this->getEntityManager();
        $em->persist($execution);
        $em->flush();

        return $execution;
    }

//    /**
//     * @return Execution[] Returns an array of Execution objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Execution
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
