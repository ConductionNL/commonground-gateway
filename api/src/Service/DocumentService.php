<?php

namespace App\Service;

use App\Entity\Document;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\SerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentService
{
    private CommonGroundService $commonGroundService;
    private EavService $eavService;
    private GatewayService $gatewayService;

    public function __construct(EavService $eavService, CommonGroundService $commonGroundService, GatewayService $gatewayService)
    {
        $this->eavService = $eavService;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
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
        return json_encode($this->eavService->getObject($dataId, 'GET', $document->getEntity()));
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
