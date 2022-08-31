<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;

/**
 * This service holds al the logic for the huwelijksplanner plugin
 */
class HuwelijksplannerService
{
    private ObjectEntityService $objectEntityService;
    private array $data;
    private array $configuration;

    /**
     * @param \App\Service\ObjectEntityService $objectEntityService
     */
    public function __construct(
        ObjectEntityService $objectEntityService
    ) {
        $this->objectEntityService = $objectEntityService;
    }

    /**
     * Handles Huwelijkslnner actions
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     */
    public function HuwelijksplannerHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Check if the incommming data exisits and is a huwelijk object
        if(
            in_array('id', $this->data) &&
            $huwelijk = $this->objectEntityService->getObject(null, $this->data['id']) &&
            $huwelijk->getEntity()->getName() == 'huwelijk'){

            return $this->checkHuwelijk($huwelijk)->toArray();
        }

        return $data;
    }

    public function checkHuwelijk(ObjectEntity $huwelijk): ObjectEntity
    {
        $checklist = [];

        // Check partners
        if(count($huwelijk->getValueByAttribute('partners')) <2){
            $checklist['partners'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 partners nodig';
        }
        elseif(count($huwelijk->getValueByAttribute('partners')) >2){
            $checklist['partners'] = 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven';
        }
        // Check getuigen
        // @todo eigenlijk is het minimaal 1 en maximaal 2 getuigen per partner
        if(count($huwelijk->getValueByAttribute('getuigen')) <2){
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 getuigen nodig';
        }
        elseif(count($huwelijk->getValueByAttribute('getuigen')) >4){
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven';
        }
        // Kijken naar locatie
        if(!$huwelijk->getValueByAttribute('locatie')){
            $checklist['locatie'] = 'Nog geen locatie opgegeven';
        }
        // Kijken naar ambtenaar
        if(!$huwelijk->getValueByAttribute('ambtenaar')){
            $checklist['ambtenaar'] = 'Nog geen ambtenaar opgegeven';
        }
        // @todo trouwdatum minimaal 2 weken groter dan aanvraag datum

        $huwelijk->setValue('checklist',$checklist);

        $this->objectEntityService->saveObject($huwelijk);

        return $huwelijk;
    }

}
