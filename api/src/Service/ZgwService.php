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
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ObjectEntityService $objectEntityService
    ) {
        $this->entityManager = $entityManager;
    }

    public function zgwZaakHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // var_dump($data);

        if (!array_key_exists('id', $this->data)) {
            // het is nieuwe zaak
            // var_dump($this->data['response']['zaaktype']);
            $zaaktype = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($this->data['response']['zaaktype']);
        } else {
            // het is een bestaande zaak
            $zaaktype = $this->entityManager->getRepository('App:ObjectEntity')->findByAnyId($this->data['response']['id']->toString())->getValue('zaaktype');
        }

        // var_dump($zaaktype);

        // heb $zaaktype en in $data de zaak

        // // 1. bestaan de rollen in het zaaktype
        // $this->doRolesExist();
        // // 2. bestaan de eigenschappen in het zaaktype
        // $this->doPropertiesExist();
        // // 3. bestaat de status in het zaaktype
        // $this->doesStatusExist();
        // // 4. Als de status anders is dan de huidige status, is die dan opvolgend?
        // $this->isStatusValid();




        // var_dump('ZGW Zaak plugin works');
        // die;

        return $this->data;
    }
}
