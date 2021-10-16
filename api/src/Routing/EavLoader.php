<?php

namespace App\Routing;

use App\Entity\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class EavLoader extends Loader
{
    private bool $isLoaded = false;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @inheritDoc
     */
    public function load($resource, string $type = null): RouteCollection
    {
        if (true === $this->isLoaded) {
            throw new \RuntimeException('Do not add the "eav" loader twice');
        }
        $routes = new RouteCollection();
        $paths = $this->getEntityRoutes();

        foreach ($paths as $path) {
            $routes->add("dynamic_eav_{$path['entity']}_get_item", $this->createSingularRoute($path['path'], 'get'));
            $routes->add("dynamic_eav_{$path['entity']}_put_item", $this->createSingularRoute($path['path'], 'put'));
            $routes->add("dynamic_eav_{$path['entity']}_delete_item", $this->createSingularRoute($path['path'], 'delete'));
            $routes->add("dynamic_eav_{$path['entity']}_post_collection", $this->createPluralRoute($path['path'], 'post'));
            $routes->add("dynamic_eav_{$path['entity']}_get_collection", $this->createPluralRoute($path['path'], 'get'));
        }

        $this->isLoaded = true;

        return $routes;
    }

    /**
     * @inheritDoc
     */
    public function supports($resource, string $type = null)
    {
        return 'eav' === $type;
    }

    public function getEntityRoutes(): array
    {
        $routes = [];

        try {
            $entities = $this->entityManager->getRepository('App:Entity')->findAll();
        } catch (\Doctrine\DBAL\Exception\ConnectionException|\Doctrine\DBAL\Exception\TableNotFoundException|\Doctrine\DBAL\Exception\InvalidFieldNameException $e) {
            return [];
        } catch (\Exception $e) {
            throw new \Exception(get_class($e));
        }

        foreach ($entities as $entity) {
            if ($entity instanceof Entity && $entity->getRoute()) {
                $routes[] = ['path' => $entity->getRoute(), 'entity' => $entity->getName()];
            }
        }

        return $routes;
    }

    public function createSingularRoute(string $path, string $method): Route
    {
        $path = "$path/{id}";
        $defaults = [
            '_controller'   => 'App\Controller\EavController:extraAction',
        ];
        $requirements = [
            'id'    => '\b[0-9a-f]{8}\b-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-\b[0-9a-f]{12}\b',
        ];

        return new Route($path, $defaults, $requirements, [], null, [], [$method]);
    }

    public function createPluralRoute(string $path, string $method): Route
    {
        $defaults = [
            '_controller'   => 'App\Controller\EavController:extraAction',
        ];

        return new Route($path, $defaults, [], [], null, [], [$method]);
    }
}
