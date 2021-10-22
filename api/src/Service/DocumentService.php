<?php

namespace App\Service;

use App\Entity\Document;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentService
{
    private EntityManagerInterface $em;
    private CommonGroundService $commonGroundService;
    private SerializerService $serializerService;
    private SerializerInterface $serializer;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commonGroundService, SerializerService $serializerService, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
        $this->serializerService = $serializerService;
        $this->serializer = $serializer;
    }

    /**
     * Get the data for a document and send it to the document creation service.
     */
    public function handleDocument(Document $document)
    {
        $data = $this->getData($document);
        $this->sendData($document, $data);
    }

    /**
     * Get document data.
     */
    private function getData(Document $document): string
    {
        $data = $document->getData();
        $dataId = $document->getDataId();

        return $data;
    }

    /**
     * Sends the data to the document creation service.
     */
    private function sendData(Document $document, string $data)
    {
        $url = $document->getDocumentCreationService();

        //TODO send data to document creation service
//        $this->commonGroundService->createResource($object, ['component' => '???', 'type' => '???']);
    }
}
