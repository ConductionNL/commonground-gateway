<?php

namespace App\Service;

use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;
    private AuthenticationService $authenticationService;
    private RequestStack $requestStack;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage, AuthenticationService $authenticationService, RequestStack $requestStack)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->authenticationService = $authenticationService;
        $this->requestStack = $requestStack;
    }

    /**
     * Processes the call to the Gateway and returns the response.
     *
     * @param string $name     Name of the Gateway.
     * @param string $endpoint Endpoint of the Gateway to send the request to.
     * @param string $method   Method to use against the Gateway.
     * @param string $content  Content to send to the Gateway.
     * @param array  $query    Query parameters to send to the Gateway.
     *
     * @return Response Created response received from Gateway or error received from Gateway.
     */
    public function processGateway(string $name, string $endpoint, string $method, string $content, array $query, array $headers): Response
    {
        $this->checkAuthentication();
        $gateway = $this->retrieveGateway($name);
        $this->checkGateway($gateway);
        $component = $this->gatewayToArray($gateway);
        $url = $gateway->getLocation().'/'.$endpoint;

        $result = $this->commonGroundService->callService($component, $url, $content, $query, ['accept' => $headers['accept'][0]], false, $method);

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

    public function checkAuthentication(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $authorized = true;
        $user = $this->tokenStorage->getToken()->getUser();

        $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));

        if (is_string($user)) {
            $authorized = $this->authenticationService->validateJWTAndGetPayload($token, $this->commonGroundService->getResourceList(['component'=>'uc', 'type'=>'public_key']));
            $authorized = $this->authenticationService->checkJWTExpiration($token);
            $authorized = $this->authenticationService->retrieveJWTUser($token);
        }

        if (!$authorized) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }

    /**
     * Creates Response object based on the guzzle response.
     *
     * @param object $result The object returned from guzzle.
     *
     * @return Response Created response object.
     */
    public function createResponse(object $result): Response
    {
        $response = new Response();
        $response->setContent($result->getBody()->getContents());
        $response->headers->replace($result->getHeaders());
        $headers = $result->getHeaders();

        $response->headers->add(['access-control-allow-origin' => '*']);
        $response->headers->remove('Server');
        $response->headers->remove('X-Content-Type-Options');
        $response->headers->remove('Set-Cookie');
        $response->setStatusCode($result->getStatusCode());

        return $response;
    }

    /**
     * Creates array from Gateway object to be used by common ground service.
     *
     * @param Gateway $gateway The Gateway object.
     *
     * @return array Created array from the Gateway object.
     */
    public function gatewayToArray(Gateway $gateway): array
    {
        $result = [
            'auth'     => $gateway->getAuth(),
            'location' => $gateway->getLocation(),
            'apikey'   => $gateway->getApiKey(),
            'jwt'      => $gateway->getJwt(),
            'secret'   => $gateway->getSecret(),
            'id'       => $gateway->getJwtId(),
            'locale'   => $gateway->getLocale(),
            'accept'   => $gateway->getAccept(),
            'username' => $gateway->getUsername(),
            'password' => $gateway->getPassword(),
        ];

        return array_filter($result);
    }

    /**
     * Checks if the Gateway object is valid.
     *
     * @param Gateway $gateway The Gateway object that needs to be checked.
     *
     * @throws BadRequestHttpException If the Gateway object is not valid.
     */
    public function checkGateway(Gateway $gateway): void
    {
        switch ($gateway->getAuth()) {
            case 'jwt':
                if ($gateway->getJwtId() == null || $gateway->getSecret() == null) {
                    throw new BadRequestHttpException('jwtid and secret are required for auth type: jwt');
                }
                break;
            case 'apikey':
                if ($gateway->getApiKey() == null) {
                    throw new BadRequestHttpException('ApiKey is required for auth type: apikey');
                }
                break;
            case 'username-password':
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
     *
     * @throws NotFoundHttpException If there is no Gateway object found with the provided name.
     *
     * @return Gateway The retrieved Gateway object.
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
