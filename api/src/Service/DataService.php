<?php

namespace App\Service;


use App\Exception\GatewayException;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

/**
 * The data service aims at providing an acces layer to request, session and user information tha can be accesed and changed from differend contexts e.g. actionHandels, Events etc
 */
class DataService
{
    private SessionInterface $session;
    private Request $request;
    private Security $security;
    private $user;
    private $faker;
    private Endpoint $endpoint;
    private Handler $handler;
    private Entity $entity;
    private ObjectEntity $object;

    public function __construct(SessionInterface $session, RequestStack $requestStack, Security $security)
    {
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        $this->security = $security;
        $this->user = $this->security->getUser();
        $this->faker = \Faker\Factory::create();
    }

    /*
     * This function should be call on the start and end of a call to remove any call specific data from the session
     */
    function clearCallData(){
        $this->sesion->clear('data');
        $this->sesion->clear('endpoint');
        $this->sesion->clear('handler');
        $this->sesion->clear('entity');
        $this->sesion->clear('object');
    }

    /**
     * Returns the data from the current requests and decodes it if necicary
     *
     * @return array
     *
     * @todo more content types ?
     * @todo check for specific error when decoding
     */
    public function getData(): array
    {
        // If we already have the data in the session then we do NOT want to rearange it
        if($data = $this->session->get('data',false)){
            return $data;
        }

        $content = $this->request->getContent();
        $contentType = $this->getRequestType('content-type');

        // lets transform the data from the request
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

        // Stuf it into the session so we wont have to do this again
        $this->session->set('data',$data);

        return $data;
    }

    /**
     *
     *
     * @param array $data
     * @return User
     */
    public function setData(array $data): DataService
    {
        // Stuf it into the session so we wont have to do this again
        $this->session->set('data',$data);

        return $this;
    }

    /**
     *
     * @return Endpoint
     */
    public function getEndpoint(): Endpoint
    {
        $this->session->get('endpoint');

        return $this;
    }

    /**
     *
     * @param Endpoint $endpoint
     * @return User
     */
    public function setEndpoint(Endpoint $endpoint): DataService
    {
        // Stuf it into the session so we wont have to do this again
        $this->session->set('endpoint',$endpoint);

        return $this;
    }

    /**
     *
     * @return Handler
     */
    public function getHandler(): Handler
    {
        $this->session->get('handler');

        return $this;
    }

    /**
     *
     * @param Handler $handler
     * @return User
     */
    public function setHandler(Handler $handler): DataService
    {
        // Stuf it into the session so we wont have to do this again
        $this->session->set('handler',$handler);

        return $this;
    }

    /**
     *
     * @return Entity
     */
    public function getEntity(): Entity
    {
        $this->session->get('entity');

        return $this;
    }

    /**
     *
     * @param Entity $entity
     * @return User
     */
    public function setEntity(Entity $entity): DataService
    {
        // Stuf it into the session so we wont have to do this again
        $this->session->set('entity',$entity);

        return $this;
    }

    /**
     *
     * @return ObjectEntity
     */
    public function getObject(): ObjectEntity
    {
        $this->session->get('object');

        return $this;
    }

    /**
     *
     * @param ObjectEntity $object
     * @return User
     */
    public function setObject(ObjectEntity $object): DataService
    {
        $this->session->set('object',$object);

        return $this;
    }

    /**
     * Get the current user, e.g. simple wrapper for the user interface
     *
     * @return User
     */
    public function getUser(): User
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
     * @return
     */
    public function getFaker(): SessionInterface
    {
        return $this->faker;
    }

}
