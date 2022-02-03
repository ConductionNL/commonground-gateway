<?php


namespace App\Service;


use App\Entity\Entity;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class StorageORMService
{
    private EntityManagerInterface $entityManager;

    public function __construct()
    {
    }

    public function createEntity(Entity $entity): Entity
    {
    }
}