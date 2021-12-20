<?php

namespace App\Service;

use App\Entity\Document;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentService
{
    private CommonGroundService $commonGroundService;
    private EavService $eavService;
    private ResponseService $responseService;
    private GatewayService $gatewayService;
    private SerializerInterface $serializer;
    private ParameterBagInterface $parameterBag;

    public function __construct(EavService $eavService, ResponseService $responseService, CommonGroundService $commonGroundService, GatewayService $gatewayService, SerializerInterface $serializer, ParameterBagInterface $parameterBag)
    {
        $this->eavService = $eavService;
        $this->responseService = $responseService;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
        $this->serializer = $serializer;
        $this->parameterBag = $parameterBag;
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
        $data = json_decode($this->serializer->serialize($this->responseService->renderResult($this->eavService->getObject($dataId, 'GET', $document->getObject()), ['properties']), 'json'), true);
        if (isset($data['id'])) {
            unset($data['id']);
        }
        if (isset($data['@id'])) {
            unset($data['@id']);
        }

        return json_encode($data);
    }

    /**
     * Sends the data to the document creation service.
     */
    private function sendData(Document $document, string $data): Response
    {
        $url = $document->getDocumentCreationService();

        return $this->gatewayService->createResponse($this->commonGroundService->callService($this->parameterBag->get('components')['dcs'], $url, $data, [], [], false, 'POST'));
    }
}
