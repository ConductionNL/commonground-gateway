<?php

namespace App\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class GatewayDriverCompilerPass implements CompilerPassInterface
{

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        $driverChainDefinition = $container->findDefinition('doctrine.orm.data_warehouse_metadata_driver');

        $driverChainDefinition->addMethodCall('addDriver', [new Reference('conduction.orm.metadata.gateway'), 'Database']);

    }
}
