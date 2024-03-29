<?php

namespace App\Repository;

use App\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    // /**
    //  * @return Translation[] Returns an array of Translation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Translation
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /*
     * This function find all applicable translations
     */
    public function getTranslations(array $translationTables, array $languages = [])
    {
        $query = $this->createQueryBuilder('t')
            ->andWhere('t.translationTable IN (:translationTables)')
            ->setParameter('translationTables', $translationTables)
            ->orderBy('t.id', 'ASC');

        if (!empty($languages)) {
            $query
                ->andWhere('t.language = IN (:languages) OR t.language = null')
                ->setParameter('languages', $languages);
        }

        $results = $query->getQuery()->getResult();

        $translations = [];
        foreach ($results as $result) {
            $translations[$result->getTranslateFrom()] = $result->getTranslateTo();
        }

        return $translations;
    }

    public function getTables()
    {
        $query = $this->createQueryBuilder('t')
            ->select('t.translationTable')
            ->distinct();

        $results = $query->getQuery()->getResult();

        $tableNames = [];
        foreach ($results as $tables) {
            $tableNames = $this->getTableNames($tables, $tableNames);
        }

        return $tableNames;
    }

    public function getTableNames(array $tables, array $tableNames)
    {
        foreach ($tables as $tableName) {
            array_push($tableNames, $tableName);
        }

        return $tableNames;
    }
}
