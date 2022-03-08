<?php

namespace App\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use UnexpectedValueException;

class DataWarehouseRepository implements ObjectRepository,Selectable
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function find($id)
    {
        // TODO: Implement find() method.
    }

    public function findAll()
    {
        $this->findBy([]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
    {
        // TODO: Implement findBy() method.
    }

    public function findOneBy(array $criteria)
    {
        // TODO: Implement findOneBy() method.
    }

    public function getClassName()
    {
        // TODO: Implement getClassName() method.
    }

    public function matching(Criteria $criteria)
    {
        // TODO: Implement matching() method.
    }
}
