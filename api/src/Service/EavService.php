<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Value;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SensioLabs\Security\Exception\HttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\String\Inflector\EnglishInflector;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

class EavService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private ValidationService $validationService;

    /* @wilco waar hebben we onderstaande voor nodig? */
    private string $entityName;
    private ?string $uuid;
    private array $body;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, ValidationService $validationService)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->validationService = $validationService;
    }

    /*
     * This function handles data mutations on EAV Objects
     */
    public function handleMutation(ObjectEntity $object, array $body)
    {
        // Validation stap
        $object = $this->validationService->validateEntity($object, $body);

        // Let see if we have errors
        if($object->getHasErrors()) {
            return $this->returnErrors($object);
        }

        /* this way of working is way vasther then passing stuff trough the object's, lets also implement this for error checks */
        if(!empty($this->validationService->promises)){
            Utils::settle($this->validationService->promises)->wait();

            foreach($this->validationService->promises as $promise){
                echo $promise->wait();
            }
        }

        // Afther guzzle has cleared we need to again check for errors
        if($object->getHasErrors()) {
            return $this->returnErrors($object);
        }

        // Saving the data
        $this->em->persist($object);
        $this->em->flush();

        return $this->renderResult($object);
    }

    public function handleGet(array $body, string $entityName, ?string $uuid)
    {

    }


    public function handleDelete(array $body, string $entityName, ?string $uuid)
    {

    }

    /**
     * Check if a given string is a valid UUID
     *
     * @param   string  $uuid   The string to check
     * @return  boolean
     */
    private function isValidUuid( $uuid ) {
        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }

        return true;
    }

    public function returnErrors(ObjectEntity $objectEntity)
    {
        return [
            "message" => "The where errors",
            "type" => "error",
            "path" => $objectEntity->getEntity()->getName(),
            "data" => $objectEntity->getAllErrors(),
        ];
    }

    // TODO: Change this to be more efficient? (same foreach as in prepareEntity) or even move it to a different service?
    public function renderResult(ObjectEntity $result): array
    {
        $response = [];

        if($result->getUri()){
            $response['@uri'] = $result->getUri();
        }


        // Lets start with the external results
        $response = array_merge($response, $result->getExternalResult());

        // Lets move some stuff out of the way
        if(array_key_exists('@context',$response)){$response['@gateway/context'] = $response['@context'];}
        if(array_key_exists('id',$response)){$response['@gateway/id'] = $response['id'];}
        if(array_key_exists('@type',$response)){$response['@gateway/type'] = $response['@type'];}

        foreach ($result->getObjectValues() as $value) {
            $attribute = $value->getAttribute();
            if ($attribute->getType() == 'object') {
                if ($value->getValue() == null) {
                    $response[$attribute->getName()] = null;
                    continue;
                }
                if (!$attribute->getMultiple()) {
                    $response[$attribute->getName()] = $this->renderResult($value->getValue());
                    continue;
                }
                $objects = $value->getValue();
                $objectsArray = [];
                foreach ($objects as $object) {
                    $objectsArray[] = $this->renderResult($object);
                }
                $response[$attribute->getName()] = $objectsArray;
                continue;
            }
            $response[$attribute->getName()] = $value->getValue();

            // Lets isnert the object that we are extending
        }

        // Lets make ik personal
        $response['@context'] = '/contexts/' . ucfirst($result->getEntity()->getName());
        $response['@id'] = ucfirst($result->getEntity()->getName()).'/'.$result->getId();
        $response['@type'] = ucfirst($result->getEntity()->getName());
        $response['id'] = $result->getId();

        return $response;
    }
}
