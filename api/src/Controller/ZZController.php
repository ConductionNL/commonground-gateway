<?php


namespace App\Controller;


use Adbar\Dot;
use App\Service\EavService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use function GuzzleHttp\json_decode;


class ZZController extends AbstractController
{
    /**
     * @Route("/api/{path}", name="dynamic_route_entity")
     * @Route("/api/{path}/{id}", name="dynamic_route_collection")
     */
    public function dynamicAction(?string $path, ?string $id, Request $request, EavService $eavService, EntityManagerInterface $em, SerializerInterface $serializer): Response
    {


        // @todo this should be in an service
        // @todo /api should never be part of an route but we use it everywhere as custom route (alowing the gateway to exacpe the /api endindpoint
        $path = '/api/'.$path;

        $entity = $em->getRepository("App:Entity")->findOneBy(['route' => $path]);
        if(!$entity){
            // @todo throw error
        }



        $contentType =  $request->headers->get('accept');
        // This should be moved to the commonground service and callded true $this->serializerService->getRenderType($contentType);
        $acceptHeaderToSerialiazation = [
            "application/json"=>"json",
            "application/ld+json"=>"jsonld",
            "application/json+ld"=>"jsonld",
            "application/hal+json"=>"jsonhal",
            "application/json+hal"=>"jsonhal",
            "application/xml"=>"xml",
            "text/csv"=>"csv",
            "text/yaml"=>"yaml",
        ];
        if(array_key_exists($contentType,$acceptHeaderToSerialiazation)){
            $renderType = $acceptHeaderToSerialiazation[$contentType];
        }
        else{
            $contentType = 'application/json';
            $renderType = 'json';
        }


        $renderTypes = ['json','jsonld','jsonhal','xml','csv','yaml'];
        $supportedExtensions = ['json','jsonld','jsonhal','xml','csv','yaml'];
        $extension = false;

        // Lets pull a render type form the extension if we have any
        if(strpos( $path, '.' ) && $renderType = explode('.', $path)){
            $path =$renderType[0];
            $renderType = end($renderType);
            $extension = $renderType;

        }
        elseif(strpos( $id, '.' ) && $renderType = explode('.', $id)){
            $renderType = end($renderType);
            $extension = $renderType;
        }
        else{
            $renderType = 'json';
        }

        // Let do a backup to defeault to an allowed render type
        if($renderType && !in_array($renderType, $renderTypes)){
            // @todo throw an error
        }


        // Lets allow for filtering specific fields
        $fields = $request->query->get('fields');

        // Get  a body
        if($request->getContent()){
            $body = json_decode($request->getContent(), true);
        }


        if($fields){
            // Lets deal with a comma seperated list
            if(!is_array($fields)){
                $fields = explode(',',$fields);

            }

            $dot = New Dot();
            // Lets turn the from dor attat into an propper array
            foreach($fields as $field => $value){
                $dot->add($value, true);
            }

            $fields = $dot->all();
        }


        // Lets setup a switchy kinda thingy to handle the input
        // Its a enity endpoint
        if($id){
            switch ($request->getMethod()){
                case 'GET':
                    $result = $eavService->handleGet($entity, $request, $fields);
                    $responseType = Response::HTTP_OK;
                    break;
                case 'PUT':
                    // Transfer the variable to the service
                    $result = $eavService->handleMutation($entity, $body, $fields);
                    $responseType = Response::HTTP_OK;
                    break;
                case 'DELETE':
                    $result = $this->handleDelete($entity, $request);
                    $responseType = Response::HTTP_NO_CONTENT;
                    break;
                default:
                    $result =  [
                        "message" => "This method is not allowed on this endpoint, allowed methods are GET, PUT and DELETE",
                        "type" => "Bad Request",
                        "path" => $path,
                        "data" => ["method" => $request->getMethod()],
                    ];
                    $responseType = Response::HTTP_BAD_REQUEST;
                    break;
            }
        }

        // its an collection endpoind
        else{
            switch ($request->getMethod()){
                case 'GET':
                    $result =  $eavService->handleSearch($entity->getName(), $request, $fields);
                    $responseType = Response::HTTP_OK;
                    break;
                case 'POST':
                    // Transfer the variable to the service
                    $result = $eavService->handleMutation($entity, $body, $fields);
                    $responseType = Response::HTTP_CREATED;
                    break;
                default:
                    $result =  [
                        "message" => "This method is not allowed on this endpoint, allowed methods are GET and POST",
                        "type" => "Bad Request",
                        "path" => $path,
                        "data" => ["method" => $request->getMethod()],
                    ];
                    $responseType = Response::HTTP_BAD_REQUEST;
                    break;
            }
        }

        // If we have an error we want to set the responce type to error
        if($result && array_key_exists('type',$result ) && $result['type']== 'error'){
            $responseType = Response::HTTP_BAD_REQUEST;
        }

        // Let seriliaze the shizle
        $result = $serializer->serialize(new ArrayCollection($result), $renderType, []);

        // Let interven is it is  a known file extension
        if(in_array($extension,$supportedExtensions)){
            $date = new \DateTime();
            $date = $date->format('Ymd_His');
            $response = new Response($result, 200, [
                'content-type'=> $contentType,
            ]);
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$entity->getName()}_{$date}.{$extension}");
            $response->headers->set('Content-Disposition', $disposition);

            return $response;
        }

        // @todo the handleRequest should be ocay with an entitye as an entity instead of tis name (more fail proof) and flexible
        return new Response(
            $result,
            $responseType,
            ['content-type' => $contentType]
        );
    }

}
