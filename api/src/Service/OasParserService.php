<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;

/*
 * This servers takes an external oas document and turns that into an gateway + eav structure
 */
class OasParserService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
    }

    public function getOAS(string $url): array
    {
        $oas = [];

        // @todo validate on url
        $pathinfo = parse_url($url);
        $extension = explode('.', $pathinfo['path']);
        $extension = end($extension);
        $pathinfo['extension'] = $extension;

        // file_get_contents(
        $file = file_get_contents($url);

        switch ($pathinfo['extension']) {
            case 'yaml':
                $oas = Yaml::parse($file);
                break;
            case 'json':
                $oas = json_decode($file, true);
                break;
            default:
                $oas = json_decode($file, true);
               // @todo throw error
        }

        // Do we have servers?
        if(array_key_exists('servers',$oas)  ) {
            foreach($oas['servers'] as $server){
                $source = New Gateway();
                $source->setName($server['description']);
                $source->setLocation($server['url']);
                $source->setAuth('none');
                $this->em->persist($source);
            }
        }

        // Do we have schemse?
        $schemas = [];
        $attributeDependencies = [];
        if(array_key_exists('components',$oas) && array_key_exists('schemas',$oas['components']) ) {
            foreach($oas['components']['schemas'] as $schemaName => $schema){
                $entity = New Entity();
                $entity->setName($schemaName);
                if(array_key_exists('description',$schema)){ $entity->setDescription($schema['description']);}
                // Handle properties
                foreach($schema['properties'] as $propertyName => $property){
                    $attribute = New Attribute();
                    $attribute->setEntity($entity);
                    $attribute->setName($propertyName);
                    // Catching arrays
                    if(array_key_exists('type',$property) && $property['type']  == "array" && is_array($property['items']) && count($property['items']) == 1){
                        $attribute->setMultiple(true);
                        $property = $property['items']; // @todo this is wierd
                        //var_dump($property['items']);
                        //var_dump(array_values($property['items'])[0]);
                    }
                    if(array_key_exists('type',$property)){ $attribute->setType($property['type']);}
                    if(array_key_exists('format',$property)){ $attribute->setFormat($property['format']);}
                    if(array_key_exists('description',$property)){ $attribute->setDescription($property['description']);}
                    if(array_key_exists('enum',$property)){ $attribute->setEnum($property['enum']);}
                    if(array_key_exists('maxLength',$property)){ $attribute->setMaxLength((int)$property['maxLength']);}
                    if(array_key_exists('minLength',$property)){ $attribute->setMinLength((int)$property['minLength']);}
                    //if(array_key_exists('pattern',$property)){ $attribute->setPatern($property['pattern']);} /* @todo hey een oas spec die we niet ondersteunen .... */
                    if(array_key_exists('example',$property)){ $attribute->setExample($property['example']);}
                    if(array_key_exists('uniqueItems',$property)){ $attribute->setUniqueItems($property['uniqueItems']);}

                    // Handling required
                    if(array_key_exists('required',$schema) && in_array($propertyName, $schema['required'])){
                        $attribute->setRequired(true);
                    }

                    // Handling external references is a bit more complicated since we can only set the when all the objects are created
                    if(array_key_exists('$ref', $property)) {
                        $attribute->setRef($property['$ref']);
                        $attributeDependencies[] = $attribute;
                    }// this is a bit more complicaties;
                    $entity->addAttribute($attribute);
                    $this->em->persist($attribute);
                }
                // Handle Gateway
                if($source){
                    $entity->setGateway($source);
                }

                // Build an array for interlinking schema's
                $schemas['#/components/schemas/'.$schemaName] = $entity;
                $this->em->persist($entity);
            }

            foreach ($attributeDependencies as $attribute){
                $attribute->setObject($schemas[$attribute->getRef()]);
                $attribute->setType('object');
                $this->em->persist($attribute);
            }
        }
        else{
            // @throw error
        }

        // Do we have paths?
        if (array_key_exists('paths', $oas)) {
            // Lets grap the paths
            foreach($oas['paths'] as $path => $info){
                // Let just grap the post for now
                if(array_key_exists('post', $info)){
                    $schema = $info['post']['requestBody']['content']['application/json']['schema']['$ref'];
                    $schemas[$schema]->setEndpoint($path);
                }
            }
        }
        else{
            // @todo throw error
        }

        $this->em->flush();

        return $oas;
    }
}
