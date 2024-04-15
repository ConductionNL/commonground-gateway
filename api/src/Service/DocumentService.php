<?php

namespace App\Service;

use App\Entity\Document;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class DocumentService
{
    private EavService $eavService;
    private GatewayService $gatewayService;
    private ParameterBagInterface $parameterBag;
    private TranslationService $translationService;
    private TemplateService $templateService;

    public function __construct(EavService $eavService, GatewayService $gatewayService, ParameterBagInterface $parameterBag, TranslationService $translationService, TemplateService $templateService)
    {
        $this->eavService = $eavService;
        $this->gatewayService = $gatewayService;
        $this->parameterBag = $parameterBag;
        $this->translationService = $translationService;
        $this->templateService = $templateService;
    }

    /**
     * Get the data for a document and send it to the document creation service.
     */
    public function handleDocument(Document $document, string $dataId): Response
    {
        $data = $this->getData($document, $dataId);

        // is we have an error we need to abort
        if (array_key_exists('type', $data) && in_array($data['type'], ['Bad Request', ''])) {
            $response = new Response();
            $response->setContent(json_encode($data));
            $response->setStatusCode(404);

            return $response;
        }

        $date = new \DateTime();
        $date = $date->format('Ymd_His');
        $response = new Response();
        $extension = 'pdf';
        $file = $this->templateService->renderPdf($document, $data);
        $response->setContent($file);

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$document->getName()}_{$date}.{$extension}");
        $response->headers->set('Content-Disposition', $disposition);

        return $response;

        //return $this->sendData($document, json_encode($data));
    }

    /**
     * Get document data.
     */
    private function getData(Document $document, string $dataId)
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

        $data = $this->eavService->getObject($dataId, 'GET', $document->getObject());

        if (!is_array($data)) { // Lets see if we have an error
            $data = $data->toArray();
        }

        $data = json_encode($data); // $this->serializer->serialize($data, 'json');
        $data = $this->translationService->parse($data, true, $translationVariables);
        $data = json_decode($data, true);

        if (isset($data['id'])) {
            unset($data['id']);
        }
        if (isset($data['@id'])) {
            unset($data['@id']);
        }

        return $data;
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
