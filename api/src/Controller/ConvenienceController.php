<?php

namespace App\Controller;

use App\Entity\CollectionEntity;
use App\Exception\GatewayException;
use App\Service\OasParserService;
use App\Service\PackagesService;
use CommonGateway\CoreBundle\Service\ActionService;
use CommonGateway\CoreBundle\Service\EndpointService;
use CommonGateway\CoreBundle\Subscriber\ActionSubscriber;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

/**
 * Authors: Barry Brands <barry@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>.
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */
class ConvenienceController extends AbstractController
{
    private PackagesService $packagesService;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param ActionSubscriber $actionSubscriber
     * @param MappingService $mappingService
     * @param ActionService $actionService
     */

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface    $serializer,
        private readonly ActionSubscriber       $actionSubscriber,
        private readonly MappingService         $mappingService,
        private readonly ActionService          $actionService,
        private readonly EndpointService        $endpointService

    ) {
        $this->packagesService = new PackagesService();
    }

    /**
     * This function runs an action.
     *
     * @Route("/admin/run_action/{actionId}")
     *
     * @throws GatewayException
     * @throws Exception
     */
    public function runAction(string $actionId, Request $request): Response
    {
        $action = $this->entityManager->getRepository('App:Action')->find($actionId);

        $contentType = $this->endpointService->getAcceptType($request);
        $data        = $this->endpointService->decodeBody($request);

        $data = $this->actionSubscriber->runFunction($action, $data, $action->getListens()[0]);

        // throw events
        foreach ($action->getThrows() as $throw) {
            $this->actionService->dispatchEvent('commongateway.action.event', $data, $throw);
        }

        return new Response(
            $this->serializer->serialize(['message' => 'Action '.$action->getName()], $contentType),
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );
    }

    /**
     * @Route("/admin/mappings/{id}/test")
     *
     * @throws GatewayException
     */
    public function getMapping(string $id, Request $request)
    {
        $contentType = $this->endpointService->getAcceptType($request);

        if (!$mappingObject = $this->entityManager->getRepository('App:Mapping')->find($id)) {
            return new GatewayException(
                $this->serializer->serialize(['message' => 'There is no mapping found with id '.$id], $contentType),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $requestMappingArray = $this->endpointService->decodeBody($request);

        $mapping = $this->mappingService->mapping($mappingObject, $requestMappingArray);

        return new Response(json_encode($mapping), 200, ['content-type' => 'json']);
    }

    /**
     * @Route("/admin/packages")
     *
     * @throws GuzzleException
     */
    public function getPackages(): Response
    {
        return $this->packagesService->discoverPackagist();
    }

    /**
     * @Route("/admin/packages/packagist")
     *
     * @throws GuzzleException
     */
    public function getPackagistPackage(Request $request): Response
    {
        return $this->packagesService->getPackagistPackageContent($request);
    }
}
