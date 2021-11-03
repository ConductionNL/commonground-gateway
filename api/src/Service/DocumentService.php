<?php

namespace App\Service;

use App\Entity\Document;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentService
{
    private CommonGroundService $commonGroundService;
    private EavService $eavService;
    private GatewayService $gatewayService;
    private SerializerInterface $serializer;

    public function __construct(EavService $eavService, CommonGroundService $commonGroundService, GatewayService $gatewayService, SerializerInterface $serializer)
    {
        $this->eavService = $eavService;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
        $this->serializer = $serializer;
    }

    /**
     * Get the data for a document and send it to the document creation service.
     */
    public function handleDocument(Document $document, string $dataId): Response
    {
        $data = $this->getData($document, $dataId);

        return $this->sendData($document, $data);
    }

    /**
     * Get document data.
     */
    private function getData(Document $document, string $dataId): string
    {
        return $this->serializer->serialize($this->eavService->getObject($dataId, 'GET', $document->getEntity()), 'json');
    }

    /**
     * Sends the data to the document creation service.
     */
    private function sendData(Document $document, string $data): Response
    {
        $url = $document->getDocumentCreationService();

        return $this->gatewayService->createResponse($this->commonGroundService->callService($this->gatewayService->gatewayToArray($document->getEntity()->getGateway()), $url, $data, [], [], false, 'POST'));
    }
}
