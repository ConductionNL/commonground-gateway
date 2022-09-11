<?php

namespace App\Service;


use App\Exception\GatewayException;
use App\Entity\Endpoint;
use App\Entity\Handler;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use DateTime;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
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
    private ObjectEntityService $datalayer;
    private Environment $twig;

    public function __construct(
        SessionInterface $session,
        RequestStack $requestStack,
        Security $security,
        ObjectEntityService $datalayer,
        Environment $twig)
    {
        $this->session = $session;
        $this->request = $requestStack->getCurrentRequest();
        $this->security = $security;
        $this->user = $this->security->getUser();
        $this->faker = \Faker\Factory::create();
        $this->datalayer = $datalayer;
        $this->twig = $twig;
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
     * @return SessionInterface
     */
    public function getFaker(): SessionInterface
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

    /**
     * This function hydrates an array with the values of another array bassed on a mapping diffined in dot notation, with al little help from https://github.com/adbario/php-dot-notation and twig
     *
     * @param array $source      the array that contains the data that is mapped
     * @param array $mapping     the array that determines how the mapping takes place
     * @param bool $list         whether the mappable objects are contained in a list (isnstead of a single object)
     *
     * @return array
     */
    public function mapper(array $source, $mapping, bool $list = false): array
    {
        // Lets first check if we are dealing with a list
        if($list){
            // We need to map object for object so....
            foreach($list as $key => $value){
                $list[$key] = $this->mapper($source, $mapping);
            }
            return $list;
        }

        // We are using dot notation for array's so lets make sure we do not intefene on the . part
        $destination = $this->encodeArrayKeys($source, '.', '&#2E');

        // lets get any drops
        $drops = [];
        if(array_key_exists('_drop',$mapping)){
            $drops = $mapping['_drops'];
            unset($mapping['_drops']);
        }

        // Lets turn  destination into a dat array
        $destination = new \Adbar\Dot($destination);

        // Lets use the mapping to hydrate the array
        foreach ($mapping as $key => $value) {
            // lets handle non-twig mapping
            if($destination->has($value)){
                $destination[$key] =$destination->get($value);
            }
            // and then the twig mapping
            $destination[$key] = castValue($this->twig->createTemplate($value)->render(['source'=>$source]));
        }

        // Lets remove the drops (is anny
        foreach ($drops as $drop){
            if($destination->has($drop)){
                $destination->clear($drop);
            }
            else{
                // @todo throw error?
            }
        }

        // Let turn the dot array back into an array
        $destination = $destination->all();
        $destination = $this->encodeArrayKeys($destination, '&#2E', '.');

        return $destination;
    }

    /**
     * This function cast a value to a specific value type
     *
     * @param string $value
     * @return void
     */
    public function castValue(string $value)
    {
        // Find the format for this value
        // @todo this should be a regex
        if (strpos($value, '|')) {
            $values = explode('|', $value);
            $value = trim($values[0]);
            $format = trim($values[1]);
        }
        else{
            return $value;
        }

        // What if....
        if(!isset($format)){
            return $value;
        }

        // Lets cast
        switch ($format){
            case 'string':
                return  strval($value);
            case 'bool':
            case 'boolean':
                return  boolval($value);
            case 'int':
            case 'integer':
                return  intval($value);
            case 'float':
                return  floatval($value);
            case 'array':
                return  (array) $value;
            case 'date':
                return  new DateTime($value);
            case 'url':
                return  urlencode(($value);
            case 'rawurl':
                return  rawurlencode(($value);
            case 'base64':
                return  base64_encode(($value);
            case 'json':
                return  json_encode($value);
            case 'xml':
                $xmlEncoder = new XmlEncoder();
                return  $xmlEncoder->decode($value, 'xml');
            case 'mapping':
                // @todo
            default:
                //@todo throw error
        }
    }

    private function encodeArrayKeys($array, string $toReplace, string $replacement): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = str_replace($toReplace, $replacement, $key);

            if (\is_array($value) && $value) {
                $result[$newKey] = $this->encodeArrayKeys($value, $toReplace, $replacement);
                continue;
            }
            $result[$newKey] = $value;

            if ($value === [] && $newKey != 'results') {
                unset($result[$newKey]);
            }
        }

        return $result;
    }

}
