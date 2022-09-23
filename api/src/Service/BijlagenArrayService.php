<?php

namespace App\Service;

class BijlagenArrayService
{
    private array $configuration;
    private array $data;

    public function __construct()
    {
    }


    /**
     * Sets bijlagen array properly
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $simXML SimXML POST which we entered the function with
     */
    public function bijlagenArrayHandler(array $data, array $configuration): array
    {
        $simXML = $data['request'];

        isset($simXML['SOAP-ENV:Body']['ns2:OntvangenIntakeNotificatie']['ns2:Bijlagen']) && $bijlagen = $simXML['SOAP-ENV:Body']['ns2:OntvangenIntakeNotificatie']['ns2:Bijlagen'];

        if (isset($bijlagen['ns2:Bijlage']['ns2:Naam'])) {
            $simXML['Bijlagen'] = [];
            $simXML['Bijlagen'][] = $bijlagen['ns2:Bijlage'];
        } elseif (isset($bijlagen[0])) {
            $simXML['Bijlagen'] = [];
            $simXML['Bijlagen'] = $bijlagen['ns2:Bijlage'];
        }

        return ['request' => $simXML];
    }
}
