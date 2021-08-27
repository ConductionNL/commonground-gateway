<?php

namespace App\Service;

use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
    }

    /**
     * Processes the call to the Gateway and returns the response.
     *
     * @param string $name Name of the Gateway.
     * @param string $endpoint Endpoint of the Gateway to send the request to.
     * @param string $method Method to use against the Gateway.
     * @param string $content Content to send to the Gateway.
     * @param array $query Query parameters to send to the Gateway.
     * @return Response Created response received from Gateway or error received from Gateway.
     */
    public function processGateway(string $name, string $endpoint, string $method, string $content, array $query): Response
    {
        $gateway = $this->retrieveGateway($name);
        $this->checkGateway($gateway);
        $component = $this->gatewayToArray($gateway);
        $url = $gateway->getLocation() . '/' . $endpoint;

        $result = $this->commonGroundService->callService($component, $url, $content, $query, [], false, $method);

        if (is_array($result)) {
            $result['error'] = json_decode($result['error'], true);
            return new Response(
                json_encode($result),
                Response::HTTP_OK,
                ['content-type' => 'application/json']
            );
        }
        return $this->createResponse($result);
    }

    /**
     * Creates Response object based on the guzzle response.
     *
     * @param Object $result The object returned from guzzle.
     * @return Response Created response object.
     */
    public function createResponse(Object $result): Response
    {
        $response = New Response();
        $response->setContent($result->getBody()->getContents());
        $response->headers->replace($result->getHeaders());
        $response->headers->add(['Access-Control-Allow-Origin' => '*']);
        $response->setStatusCode($result->getStatusCode());

        return $response;
    }

    /**
     * Creates array from Gateway object to be used by common ground service.
     *
     * @param Gateway $gateway The Gateway object.
     * @return array Created array from the Gateway object.
     */
    public function gatewayToArray(Gateway $gateway): array
    {
        $result = [
            'auth' => $gateway->getAuth(),
            'location' => $gateway->getLocation(),
            'apikey' => $gateway->getApiKey(),
            'jwt' => $gateway->getJwt(),
            'locale' => $gateway->getLocale(),
            'accept' => $gateway->getAccept(),
            'username' => $gateway->getUsername(),
            'password' => $gateway->getPassword(),
        ];

        return array_filter($result);
    }

    /**
     * Checks if the Gateway object is valid.
     *
     * @param Gateway $gateway The Gateway object that needs to be checked.
     * @throws BadRequestHttpException If the Gateway object is not valid.
     */
    public function checkGateway(Gateway $gateway): void
    {
        switch ($gateway->getAuth()) {
            case "jwt":
                if ($gateway->getJwt() == null) {
                    throw new BadRequestHttpException('JWT is required for auth type: jwt');
                }
                break;
            case "apikey":
                if ($gateway->getApiKey() == null) {
                    throw new BadRequestHttpException('ApiKey is required for auth type: apikey');
                }
                break;
            case "username-password":
                if ($gateway->getUsername() == null || $gateway->getPassword() == null) {
                    throw new BadRequestHttpException('Username and password are required for auth type: username-password');
                }
                break;
        }
    }

    /**
     * Tries to retrieve the Gateway object with entity manager.
     *
     * @param $gateway string Name of the Gateway used to search for the object.
     * @return Gateway The retrieved Gateway object.
     * @throws NotFoundHttpException If there is no Gateway object found with the provided name.
     */
    public function retrieveGateway(string $gateway): Gateway
    {
        $gateways = $this->entityManager->getRepository('App\Entity\Gateway')->findBy(['name' => $gateway]);

        if (count($gateways) == 0 || !$gateways[0] instanceof Gateway) {
            throw new NotFoundHttpException('Unable to find Gateway');
        }

        return $gateways[0];
    }

}
