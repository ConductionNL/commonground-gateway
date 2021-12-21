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
    private TranslationService $translationService;

    public function __construct(EavService $eavService, ResponseService $responseService, CommonGroundService $commonGroundService, GatewayService $gatewayService, SerializerInterface $serializer, ParameterBagInterface $parameterBag, TranslationService $translationService)
    {
        $this->eavService = $eavService;
        $this->responseService = $responseService;
        $this->commonGroundService = $commonGroundService;
        $this->gatewayService = $gatewayService;
        $this->serializer = $serializer;
        $this->parameterBag = $parameterBag;
        $this->translationService = $translationService;
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
        // opgeven van vertalingen
        $translationVariables = [
            'Unknown'              => 'Onbekend',
            'UNKNOWN'              => 'Onbekend',
            'Beginner'             => 'Beginner',
            'BEGINNER'             => 'Beginner',
            'Advanced'             => 'Gevorderd',
            'ADVANCED'             => 'Gevorderd',
            'Reasonable'           => 'Redelijk',
            'REASONABLE'           => 'Redelijk',
            'CAN_NOT_WRITE'        => 'Kan niet schrijven',
            'WRITE_NAW_DETAILS'    => 'Kan NAW gegevens schrijven',
            'WRITE_SIMPLE_LETTERS' => 'Kan (eenvoudige) brieven schrijven',
            'WRITE_SIMPLE_TEXTS'   => 'Kan eenvoudige teksten schrijven (boodschappenbriefje etc.)',
            'CAN_NOT_READ'         => 'Kan niet lezen',
            'Male'                 => 'Man',
            'Female'               => 'Vrouw',
            'English'              => 'Engels',
        ];

        $data = $this->serializer->serialize($this->eavService->getObject($dataId, 'GET', $document->getObject())->toArray(), 'json');

        $data = $this->translationService->parse($data, true, $translationVariables);
        $data = json_decode($data, true);

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
