<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use mysql_xdevapi\Exception;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class ZgwService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     * @param ObjectEntityService $objectEntityService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ObjectEntityService $objectEntityService
    ) {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
    }

    public function zgwZaakHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if(!array_key_exists('id', $this->data)){
            // het is nieuwe zaak
            $zaaktype = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($this->data['zaaktype']);
        }
        else{
            // het is een bestaande zaak
            $zaaktype = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($this->data['id'])->getValue('zaaktype');
        }

        // heb $zaaktype en in $data de zaak

        // dus nu kan je valideren

        // 1. bestaan de rollen in het zaaktype

        // 2. bestaan de eigenschappen in het zaaktype

        // 3. bestaat de status in het zaaktype

        // 4. Als de status anders is dan de huidige status, is die dan opvolgend?



        return $this->data;
    }
}
