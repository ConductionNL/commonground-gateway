<?php

namespace App\Service;

use App\Entity\Entity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class HandelsRegisterSearchService
{
    private EntityManagerInterface $entityManager;
    private EavService $eavService;
    private array $data;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        EavService $eavService
    ) {
        $this->entityManager = $entityManager;
        $this->eavService = $eavService;
    }

    /**
     * Handles the search action for kvk handelsRegister.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function handelsRegisterSearchHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Query params
        $queryParameters = $this->fixQueryParams($this->data['queryParameters']);
        if (array_key_exists('errorMessage', $queryParameters)) {
            throw new GatewayException($queryParameters['errorMessage'], null, null, ['data' => $this->data['queryParameters'], 'path' => "/api/kvk/zoeken?{$_SERVER['QUERY_STRING']}", 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        // Let's allow for extending
        $extend = $this->eavService->getRequestExtend($this->data['httpRequest']);
        if (isset($extend['x-commongateway-metadata']) && $extend['x-commongateway-metadata'] === true) {
            $extend['x-commongateway-metadata'] = [];
            $extend['x-commongateway-metadata']['all'] = true;
        }

        $entities = $this->getEntitiesFromConfig();
        if (isset($entities['vestiging'])) {
            $result = $this->eavService->handleSearch(
                $entities['vestiging'],
                $this->data['httpRequest'],
                null,
                $extend,
                false,
                $queryParameters ?? [],
                'json',
                $queryParameters
            );
            $this->data['response'] = $result;
        }

        return $this->data;
    }

    /**
     * Make sure the query params used with the zoeken endpoint also match the query params we can use for vestigingen.
     *
     * @param array $queryParameters
     *
     * @return array
     */
    private function fixQueryParams(array $queryParameters): array
    {
        if (array_key_exists('kvkNummer', $queryParameters)) {
            $queryParameters['kvknummer'] = $queryParameters['kvkNummer'];
            unset($queryParameters['kvkNummer']);
        }
        if (array_key_exists('handelsnaam', $queryParameters)) {
            $queryParameters['eersteHandelsnaam'] = $queryParameters['handelsnaam'];
//            $queryParameters['handelsnamen'] = $queryParameters['handelsnaam']; //todo ?
            unset($queryParameters['handelsnaam']);
        }
        if (array_key_exists('postcode', $queryParameters) && array_key_exists('huisnummer', $queryParameters)) {
            $queryParameters['postadres.postcode'] = $queryParameters['postcode'];
            $queryParameters['postadres.huisnummer'] = $queryParameters['huisnummer'];
            if (array_key_exists('huisnummerToevoeging', $queryParameters)) {
                $queryParameters['postadres.huisnummertoevoeging'] = $queryParameters['huisnummerToevoeging'];
                unset($queryParameters['huisnummerToevoeging']);
            }
            unset($queryParameters['postcode']);
            unset($queryParameters['huisnummer']);
        } elseif (!empty(array_intersect_key($queryParameters, array_flip(['postcode', 'huisnummer', 'huisnummerToevoeging'])))) {
            return ['errorMessage' => 'Gebruik een postcode en een huisnummer in je filters om te zoeken op een adres.'];
        }

        // Lets always extend all by default
        if (!isset($queryParameters['extend']) && !isset($queryParameters['_extend'])) {
            $queryParameters['extend'] = ['all'];
        }

        return $queryParameters;
    }

    /**
     * Searches and returns the entities of the configuration in the database.
     *
     * @return Entity|null The found entities for the configuration
     */
    private function getEntitiesFromConfig(): ?array
    {
        if (isset($this->configuration['entities']['vestiging'])) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->configuration['entities']['vestiging']]);
            if ($entity instanceof Entity) {
                return [
                    'vestiging' => $entity,
                ];
            }
        }

        return null;
    }
}
