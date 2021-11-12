<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class SOAPService
{
    public function getNamespaces(array $data): array
    {
        $namespaces = [];
        foreach($data as $key => $datum) {
            if(($splitKey = explode(':', $key))[0] == '@xmlns'){
                $namespaces[$splitKey[1]] = $datum;
            }
        }
        return $namespaces;
    }

    public function getMessageType(array $data, array $namespaces): string
    {
        if(
            !($env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces))
        ){
            throw new BadRequestException("SOAP namespace is missing.");
        }

        if(!isset(explode(':', array_keys($data["$env:Body"])[0])[1])){
            throw new BadRequestException("Could not find message type");
        }
        return explode(':', array_keys($data["$env:Body"])[0])[1];
    }

    public function getLa01Message(string $bsn): string
    {
        switch($bsn){
        case "11900017090":
            return '
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
<s:Body>
    <zakLa01 xmlns="http://www.egem.nl/StUF/sector/zkn/0310" xmlns:StUF="http://www.egem.nl/StUF/StUF0301"
             xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:BG="http://www.egem.nl/StUF/sector/bg/0310">
        <stuurgegevens>
            <StUF:berichtcode>La01</StUF:berichtcode>
            <StUF:zender>
                <StUF:organisatie>0637</StUF:organisatie>
                <StUF:applicatie>DDX</StUF:applicatie>
            </StUF:zender>
            <StUF:ontvanger>
                <StUF:organisatie>SIMgroep</StUF:organisatie>
                <StUF:applicatie>SIMform</StUF:applicatie>
                <StUF:administratie>Zaken</StUF:administratie>
                <StUF:gebruiker>Systeem</StUF:gebruiker>
            </StUF:ontvanger>
            <StUF:referentienummer>c63fb33d-5f80-438d-ad0a-7ad2986c2887</StUF:referentienummer>
            <StUF:tijdstipBericht>20170227090248065</StUF:tijdstipBericht>
            <StUF:crossRefnummer>000</StUF:crossRefnummer>
            <StUF:entiteittype>ZAK</StUF:entiteittype>
        </stuurgegevens>
        <parameters>
            <StUF:indicatorVervolgvraag>false</StUF:indicatorVervolgvraag>
        </parameters>
        <antwoord>
            <object StUF:sleutelVerzendend="20161001" StUF:entiteittype="ZAK">
                <identificatie>20161001</identificatie>
            </object>
            <object StUF:sleutelVerzendend="20161002" StUF:entiteittype="ZAK">
                <identificatie>20161002</identificatie>
                <omschrijving>Alle datums ingevuld behalve einddatum</omschrijving>
                <registratiedatum>20170201</registratiedatum>
                <startdatum>20170202</startdatum>
                <publicatiedatum>20170203</publicatiedatum>
                <einddatumGepland>20170204</einddatumGepland>
                <einddatum></einddatum>
                <uiterlijkeEinddatum>20170228</uiterlijkeEinddatum>
                <isVan StUF:entiteittype="ZAKZKT">
                    <gerelateerde StUF:entiteittype="ZKT">
                        <omschrijving>Verhuizing</omschrijving>
                        <code>VHZT</code>
                    </gerelateerde>
                </isVan>
                <heeft StUF:entiteittype="ZAKSTT">
                    <gerelateerde StUF:entiteittype="STT">
                        <volgnummer>1</volgnummer>
                        <code>tvh-vw</code>
                        <omschrijving>Gepland</omschrijving>
                    </gerelateerde>
                    <toelichting>Uw toestemming is verwerkt.</toelichting>
                    <datumStatusGezet>20161014164325369</datumStatusGezet>
                    <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                </heeft>
                <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
            </object>
            <object StUF:sleutelVerzendend="20161003" StUF:entiteittype="ZAK">
                <identificatie>20161003</identificatie>
                <omschrijving>Alleen startdatum ingevuld</omschrijving>
                <registratiedatum></registratiedatum>
                <startdatum>20170302</startdatum>
                <publicatiedatum></publicatiedatum>
                <einddatumGepland></einddatumGepland>
                <einddatum></einddatum>
                <uiterlijkeEinddatum></uiterlijkeEinddatum>
                <isVan StUF:entiteittype="ZAKZKT">
                    <gerelateerde StUF:entiteittype="ZKT">
                        <omschrijving>Vergunning aanvraag</omschrijving>
                        <code>VHZT</code>
                    </gerelateerde>
                </isVan>
                <heeft StUF:entiteittype="ZAKSTT">
                    <gerelateerde StUF:entiteittype="STT">
                        <volgnummer>1</volgnummer>
                        <code>tvh-vw</code>
                        <omschrijving>Gepland</omschrijving>
                    </gerelateerde>
                    <toelichting>Uw toestemming is verwerkt.</toelichting>
                    <datumStatusGezet>20161014164325369</datumStatusGezet>
                    <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                </heeft>
                <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
            </object>
            <object StUF:sleutelVerzendend="20161004" StUF:entiteittype="ZAK">
                <identificatie>20161004</identificatie>
                <omschrijving>Geen datums ingevuld</omschrijving>
                <registratiedatum></registratiedatum>
                <startdatum></startdatum>
                <publicatiedatum></publicatiedatum>
                <einddatumGepland></einddatumGepland>
                <einddatum></einddatum>
                <uiterlijkeEinddatum></uiterlijkeEinddatum>
                <isVan StUF:entiteittype="ZAKZKT">
                    <gerelateerde StUF:entiteittype="ZKT">
                        <omschrijving>Aangifte geboorte</omschrijving>
                        <code>VHZT</code>
                    </gerelateerde>
                </isVan>
                <heeft StUF:entiteittype="ZAKSTT">
                    <gerelateerde StUF:entiteittype="STT">
                        <volgnummer>1</volgnummer>
                        <code>tvh-vw</code>
                        <omschrijving>In behandeling</omschrijving>
                    </gerelateerde>
                    <toelichting>Uw toestemming is verwerkt.</toelichting>
                    <datumStatusGezet>20161014164325369</datumStatusGezet>
                    <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                </heeft>
                <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
            </object>
			<object StUF:sleutelVerzendend="20161005" StUF:entiteittype="ZAK">
                <identificatie>20161005</identificatie>
                <omschrijving>Alle datums ingevuld</omschrijving>
                <registratiedatum>20170501</registratiedatum>
                <startdatum>20170502</startdatum>
                <publicatiedatum>20170503</publicatiedatum>
                <einddatumGepland>20170504</einddatumGepland>
                <einddatum>20170505</einddatum>
                <uiterlijkeEinddatum>20170528</uiterlijkeEinddatum>
                <isVan StUF:entiteittype="ZAKZKT">
                    <gerelateerde StUF:entiteittype="ZKT">
                        <omschrijving>Verhuizing</omschrijving>
                        <code>VHZT</code>
                    </gerelateerde>
                </isVan>
                <heeft StUF:entiteittype="ZAKSTT">
                    <gerelateerde StUF:entiteittype="STT">
                        <volgnummer>1</volgnummer>
                        <code>tvh-vw</code>
                        <omschrijving>Verwerkt</omschrijving>
                    </gerelateerde>
                    <toelichting>Uw toestemming is verwerkt.</toelichting>
                    <datumStatusGezet>20161014164325369</datumStatusGezet>
                    <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                </heeft>
                <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
            </object>
			<object StUF:sleutelVerzendend="20161006" StUF:entiteittype="ZAK">
                <identificatie>20161006</identificatie>
                <omschrijving>Alleen eindddatum ingevuld</omschrijving>
                <registratiedatum></registratiedatum>
                <startdatum></startdatum>
                <publicatiedatum></publicatiedatum>
                <einddatumGepland></einddatumGepland>
                <einddatum>20170605</einddatum>
                <uiterlijkeEinddatum></uiterlijkeEinddatum>
                <isVan StUF:entiteittype="ZAKZKT">
                    <gerelateerde StUF:entiteittype="ZKT">
                        <omschrijving>Verhuizing</omschrijving>
                        <code>VHZT</code>
                    </gerelateerde>
                </isVan>
                <heeft StUF:entiteittype="ZAKSTT">
                    <gerelateerde StUF:entiteittype="STT">
                        <volgnummer>1</volgnummer>
                        <code>tvh-vw</code>
                        <omschrijving>Verwerkt</omschrijving>
                    </gerelateerde>
                    <toelichting>Uw toestemming is verwerkt.</toelichting>
                    <datumStatusGezet>20161014164325369</datumStatusGezet>
                    <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                </heeft>
                <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
            </object>
            <object StUF:sleutelVerzendend="20161007" StUF:entiteittype="ZAK">
                <identificatie>20161007</identificatie>
                <omschrijving>Buurt BBQ</omschrijving>
                <registratiedatum></registratiedatum>
                <startdatum></startdatum>
                <publicatiedatum></publicatiedatum>
                <einddatumGepland></einddatumGepland>
                <einddatum></einddatum>
                <uiterlijkeEinddatum></uiterlijkeEinddatum>
                <isVan StUF:entiteittype="ZAKZKT">
                    <gerelateerde StUF:entiteittype="ZKT">
                        <omschrijving>Evenement melding</omschrijving>
                        <code>VHZT</code>
                    </gerelateerde>
                </isVan>
                <heeft StUF:entiteittype="ZAKSTT">
                    <gerelateerde StUF:entiteittype="STT">
                        <volgnummer>1</volgnummer>
                        <code>tvh-vw</code>
                        <omschrijving>In behandeling</omschrijving>
                    </gerelateerde>
                    <toelichting>Uw toestemming is verwerkt.</toelichting>
                    <datumStatusGezet>20161014164325369</datumStatusGezet>
                    <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                </heeft>
                <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
            </object>
		</antwoord>
    </zakLa01>
</s:Body>
</s:Envelope>';
            default:
                return '
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body>
        <zakLa01 xmlns="http://www.egem.nl/StUF/sector/zkn/0310" xmlns:StUF="http://www.egem.nl/StUF/StUF0301"
                 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xmlns:BG="http://www.egem.nl/StUF/sector/bg/0310">
            <stuurgegevens>
                <StUF:berichtcode>La01</StUF:berichtcode>
                <StUF:zender>
                    <StUF:organisatie>0637</StUF:organisatie>
                    <StUF:applicatie>DDS</StUF:applicatie>
                </StUF:zender>
                <StUF:ontvanger>
                    <StUF:organisatie>0637</StUF:organisatie>
                    <StUF:applicatie>PFS</StUF:applicatie>
                </StUF:ontvanger>
                <StUF:referentienummer>56589505-9e66-4b78-902e-a1d99f243357</StUF:referentienummer>
                <StUF:tijdstipBericht>20171002135923994</StUF:tijdstipBericht>
                <StUF:crossRefnummer>000</StUF:crossRefnummer>
                <StUF:entiteittype>ZAK</StUF:entiteittype>
            </stuurgegevens>
            <parameters>
                <StUF:indicatorVervolgvraag>false</StUF:indicatorVervolgvraag>
            </parameters>
        </zakLa01>
    </s:Body>
</s:Envelope>';
        }
    }

    public function processZakLv01Message (array $data, array $namespaces): string
    {
        if(
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces))
        ){

            throw new BadRequestException("STuF and/or case namespaces missing ");
        }
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $message = $data["$env:Body"]["$caseNamespace:zakLv01"];

        if(
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == "ZAK" &&
            $message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["@$stufNamespace:entiteittype"] == "ZAKBTRINI" &&
            $message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["@$stufNamespace:entiteittype"] == "BTR"
        ){
            return $this->getLa01Message($message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"]);
        }
        throw new BadRequestException("Not a valid Lv01 message");

    }
}
