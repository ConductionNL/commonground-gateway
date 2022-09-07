<?php

namespace App\Service;


use App\Exception\GatewayException;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use Faker\Generator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * The data service aims at providing an access layer to request, session and user information tha can be accessed and changed from different contexts e.g. actionHandlers, Events etc
 */
class DataService
{
    private SessionInterface $session;
    private Request $request;
    private Security $security;
    private UserInterface $user;
    private Generator $faker;
    private Endpoint $endpoint;
    private Handler $handler;
    private Entity $entity;
    private ObjectEntity $object;
    private ObjectEntityService $datalayer;
    private HandlerService $handlerService;

    public function __construct(SessionInterface $session, RequestStack $requestStack, Security $security, ObjectEntityService $datalayer, HandlerService $handlerService)
    {
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        $this->security = $security;
        $this->user = $this->security->getUser();
        $this->faker = \Faker\Factory::create();
        $this->datalayer = $datalayer;
        $this->handlerService = $handlerService;
    }

    /**
     * This function should be called on the start and end of a call to remove any call specific data from the session
     */
    public function clearCallData(): void
    {
        $this->session->remove('data');
        $this->session->remove('endpoint');
        $this->session->remove('handler');
        $this->session->remove('entity');
        $this->session->remove('object');
    }

    /**
     * Returns the data from the current requests and decodes it if necessary
     *
     * @return array
     *
     * @throws GatewayException
     * @todo check for specific error when decoding
     * @todo more content types ?
     */
    public function getData(): array
    {
        // If we already have the data in the session then we do NOT want to rearrange it
        if($data = $this->session->get('data',false)){
            return $data;
        }

        $content = $this->request->getContent();
        $contentType = $this->handlerService->getRequestType('content-type');

        // let's transform the data from the request
        switch ($contentType) {
            case 'json':
            case 'jsonhal':
            case 'jsonld':
                $data = json_decode($content, true);
                break;
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                $xml = $xmlEncoder->decode($content, $contentType);
                // otherwise xml will throw its own error bypassing our exception handling
//        libxml_use_internal_errors(true);
                // string to xml object, encode that to json then decode to array
//        $xml = simplexml_load_string($content);
                // if xml is false get errors and throw exception
                if ($xml === false) {
                    $errors = 'Something went wrong decoding xml:';
                    foreach (libxml_get_errors() as $e) {
                        $errors .= ' '.$e->message;
                    }

                    throw new GatewayException($errors, null, null, ['data' => $content, 'path' => 'Request body', 'responseType' => Response::HTTP_UNPROCESSABLE_ENTITY]);
                }

                $data = json_decode(json_encode($xml), true);
            default:
                throw new GatewayException('Unsupported content type', null, null, ['data' => $content, 'path' => null, 'responseType' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE]);
        }

        // Stuff it into the session, so we won't have to do this again
        $this->session->set('data',$data);

        return $data;
    }

    /**
     *
     *
     * @param array $data
     * @return DataService
     */
    public function setData(array $data): DataService
    {
        // Stuff it into the session, so we won't have to do this again
        $this->session->set('data',$data);

        return $this;
    }

    /**
     *
     * @return Endpoint
     */
    public function getEndpoint(): Endpoint
    {
        return $this->session->get('endpoint');
    }

    /**
     *
     * @param Endpoint $endpoint
     * @return DataService
     */
    public function setEndpoint(Endpoint $endpoint): DataService
    {
        // Stuff it into the session, so we won't have to do this again
        $this->session->set('endpoint',$endpoint->getId());

        return $this;
    }

    /**
     *
     * @return Handler
     */
    public function getHandler(): Handler
    {
        return $this->session->get('handler');
    }

    /**
     *
     * @param Handler $handler
     * @return DataService
     */
    public function setHandler(Handler $handler): DataService
    {
        // Stuff it into the session, so we won't have to do this again
        $this->session->set('handler', $handler->getId());

        return $this;
    }

    /**
     *
     * @return Entity
     */
    public function getEntity(): Entity
    {
        return $this->session->get('entity');
    }

    /**
     *
     * @param Entity $entity
     * @return DataService
     */
    public function setEntity(Entity $entity): DataService
    {
        // Stuff it into the session, so we won't have to do this again
        $this->session->set('entity',$entity->getId());

        return $this;
    }

    /**
     *
     * @return ObjectEntity
     */
    public function getObject(): ObjectEntity
    {
        return $this->session->get('object');
    }

    /**
     *
     * @param ObjectEntity $object
     * @return DataService
     */
    public function setObject(ObjectEntity $object): DataService
    {
        // Stuff it into the session, so we won't have to do this again
        $this->session->set('object',$object->getId());

        return $this;
    }

    /**
     * Get the current user, e.g. simple wrapper for the user interface
     *
     * @return UserInterface
     */
    public function getUser(): UserInterface
    {
        return $this->user;

    }

    /**
     * Get the current session, e.g. simple wrapper for the session interface
     *
     * @return SessionInterface
     */
    public function getSession(): SessionInterface
    {
        return $this->session;
    }

    /**
     * Accesses the faker component, e.g. simple wrapper for the faker bundle
     *
     * See https://fakerphp.github.io/formatters/numbers-and-strings/ for more information
     *
     * @return Generator
     */
    public function getFaker(): Generator
    {
        return $this->faker;
    }

    /**
     * Accesses the datalayer, e.g. simple wrapper for the object entity service
     *
     * @return ObjectEntityService
     */
    public function getDataLayer(): ObjectEntityService
    {
        return $this->datalayer;
    }

}
