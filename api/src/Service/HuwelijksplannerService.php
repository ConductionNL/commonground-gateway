<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use DateInterval;
use DatePeriod;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This service holds al the logic for the huwelijksplanner plugin.
 */
class HuwelijksplannerService
{
    private ObjectEntityService $objectEntityService;
    private RequestStack $requestStack;
    private Request $request;
    private array $data;
    private array $configuration;

    /**
     * @param \App\Service\ObjectEntityService $objectEntityService
     * @param RequestStack                     $requestStack
     */
    public function __construct(
        ObjectEntityService $objectEntityService,
        RequestStack $requestStack
    ) {
        $this->objectEntityService = $objectEntityService;
        $this->request = $requestStack->getCurrentRequest();
        $this->data = [];
        $this->configuration = [];
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws \Exception
     *
     * @return array
     */
    public function HuwelijksplannerHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $begin = new DateTime($this->request->get('start'));
        $end = new DateTime($this->request->get('stop'));

        $interval = new DateInterval($this->request->get('interval'));
        $period = new DatePeriod($begin, $interval, $end);

        $resultArray = [];
        foreach ($period as $currentDate) {
            // start voorbeeld code
            $dayStart = clone $currentDate;
            $dayStop = clone $currentDate;

            $dayStart->setTime(9, 0);
            $dayStop->setTime(17, 0);

            if ($currentDate->format('Y-m-d H:i:s') >= $dayStart->format('Y-m-d H:i:s') && $currentDate->format('Y-m-d H:i:s') < $dayStop->format('Y-m-d H:i:s')) {
                $resourceArray = $this->request->get('resources_could');
            } else {
                $resourceArray = [];
            }

            // end voorbeeld code
            $resultArray[$currentDate->format('Y-m-d')][] = [
                'start'     => $currentDate->format('Y-m-d\TH:i:sO'),
                'stop'      => $currentDate->add($interval)->format('Y-m-d\TH:i:sO'),
                'resources' => $resourceArray,
            ];
        }

        $this->data['response'] = $resultArray;

        return $this->data;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function HuwelijksplannerCheckHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Check if the incommming data exisits and is a huwelijk object
        if (
            in_array('id', $this->data) &&
            $huwelijk = $this->objectEntityService->getObject(null, $this->data['id']) &&
                $huwelijk->getEntity()->getName() == 'huwelijk') {
            return $this->checkHuwelijk($huwelijk)->toArray();
        }

        return $data;
    }

    public function checkHuwelijk(ObjectEntity $huwelijk): ObjectEntity
    {
        $checklist = [];

        // Check partners
        if (count($huwelijk->getValueObject('partners')) < 2) {
            $checklist['partners'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 partners nodig';
        } elseif (count($huwelijk->getValueObject('partners')) > 2) {
            $checklist['partners'] = 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven';
        }
        // Check getuigen
        // @todo eigenlijk is het minimaal 1 en maximaal 2 getuigen per partner
        if (count($huwelijk->getValueObject('getuigen')) < 2) {
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 getuigen nodig';
        } elseif (count($huwelijk->getValueObject('getuigen')) > 4) {
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven';
        }
        // Kijken naar locatie
        if (!$huwelijk->getValueObject('locatie')) {
            $checklist['locatie'] = 'Nog geen locatie opgegeven';
        }
        // Kijken naar ambtenaar
        if (!$huwelijk->getValueObject('ambtenaar')) {
            $checklist['ambtenaar'] = 'Nog geen ambtenaar opgegeven';
        }
        // @todo trouwdatum minimaal 2 weken groter dan aanvraag datum

        $huwelijk->setValue('checklist', $checklist);

        $this->objectEntityService->saveObject($huwelijk);

        return $huwelijk;
    }
}
