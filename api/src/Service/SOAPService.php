<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class SOAPService
{
    public function getNamespaces(array $data): array
    {
        $namespaces = [];
        foreach ($data as $key => $datum) {
            if (($splitKey = explode(':', $key))[0] == '@xmlns') {
                $namespaces[$splitKey[1]] = $datum;
            }
        }

        return $namespaces;
    }

    public function getMessageType(array $data, array $namespaces): string
    {
        if (
            !($env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces))
        ) {
            throw new BadRequestException('SOAP namespace is missing.');
        }

        if (!isset(explode(':', array_keys($data["$env:Body"])[0])[1])) {
            throw new BadRequestException('Could not find message type');
        }

        return explode(':', array_keys($data["$env:Body"])[0])[1];
    }

    public function getLa01MessageForBSN(string $bsn): string
    {
        switch ($bsn) {
        case '11900017090':
            return
'<?xml version="1.0"?>
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
                return
'<?xml version="1.0"?>
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

    public function getZakLa01MessageForIdentifier(string $identifier, bool $documents): string
    {
        switch($identifier) {
            case '20161006':
                if(!$documents)
                    return
'<?xml version="1.0"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body>
        <zakLa01 xmlns="http://www.egem.nl/StUF/sector/zkn/0310" xmlns:StUF="http://www.egem.nl/StUF/StUF0301"
                 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xmlns:BG="http://www.egem.nl/StUF/sector/bg/0310">
            <stuurgegevens>
                <StUF:berichtcode>La01</StUF:berichtcode>
                <StUF:zender>
                    <StUF:organisatie>BCT-OTA</StUF:organisatie>
                    <StUF:applicatie>Liber ZakenMagazijn</StUF:applicatie>
                    <StUF:administratie>Zaken</StUF:administratie>
                    <StUF:gebruiker>Systeem</StUF:gebruiker>
                </StUF:zender>
                <StUF:ontvanger>
                    <StUF:organisatie>BCT</StUF:organisatie>
                    <StUF:applicatie>StUFEFAdapter</StUF:applicatie>
                    <StUF:administratie>Zaken</StUF:administratie>
                    <StUF:gebruiker>Systeem</StUF:gebruiker>
                </StUF:ontvanger>
                <StUF:referentienummer>03735fea-7988-4d20-bd6c-96509287b9dd</StUF:referentienummer>
                <StUF:tijdstipBericht>20170420080553272</StUF:tijdstipBericht>
                <StUF:crossRefnummer>19101082</StUF:crossRefnummer>
                <StUF:entiteittype>ZAK</StUF:entiteittype>
            </stuurgegevens>
            <parameters>
                <StUF:indicatorVervolgvraag>false</StUF:indicatorVervolgvraag>
            </parameters>
            <antwoord>
                <object StUF:sleutelVerzendend="1046" StUF:entiteittype="ZAK">
                    <identificatie>20161006</identificatie>
                    <omschrijving>Alleen eindddatum ingevuld</omschrijving>
                    <toelichting xsi:nil="true" StUF:noValue="geenWaarde"/>
                    <resultaat>
                        <omschrijving>Huis gekraakt</omschrijving>
                        <toelichting xsi:nil="true" StUF:noValue="geenWaarde"/>
                    </resultaat>
                    <startdatum></startdatum>
                    <registratiedatum></registratiedatum>
                    <publicatiedatum></publicatiedatum>
                    <einddatumGepland></einddatumGepland>
                    <uiterlijkeEinddatum></uiterlijkeEinddatum>
                    <einddatum>20170605</einddatum>
                    <opschorting>
                        <indicatie>N</indicatie>
                        <reden xsi:nil="true" StUF:noValue="geenWaarde"/>
                    </opschorting>
                    <verlenging>
                        <duur>0</duur>
                        <reden xsi:nil="true" StUF:noValue="geenWaarde"/>
                    </verlenging>
                    <betalingsIndicatie>N.v.t.</betalingsIndicatie>
                    <laatsteBetaaldatum xsi:nil="true" StUF:noValue="geenWaarde"/>
                    <archiefnominatie>N</archiefnominatie>
                    <datumVernietigingDossier xsi:nil="true" StUF:noValue="geenWaarde"/>
                    <zaakniveau>1</zaakniveau>
                    <deelzakenIndicatie>N</deelzakenIndicatie>
                    <StUF:extraElementen>
                        <StUF:extraElement naam="kanaalcode">web</StUF:extraElement>
                    </StUF:extraElementen>
                    <isVan StUF:entiteittype="ZAKZKT">
                        <gerelateerde StUF:entiteittype="ZKT">
                                <omschrijving>Verhuizing</omschrijving>
                            <code>VHZT</code>
                            <omschrijvingGeneriek xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <zaakcategorie xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <trefwoord xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <doorlooptijd xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <servicenorm xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <archiefcode xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <vertrouwelijkAanduiding>ZAAKVERTROUWELIJK</vertrouwelijkAanduiding>
                            <publicatieIndicatie>N</publicatieIndicatie>
                            <publicatietekst xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <ingangsdatumObject xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <einddatumObject xsi:nil="true" StUF:noValue="geenWaarde"/>
                        </gerelateerde>
                    </isVan>
                    <heeftBetrekkingOp xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKOBJ"/>
                    <heeftAlsBelanghebbende xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBTRBLH"/>
                    <heeftAlsGemachtigde xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBTRGMC"/>
                    <heeftAlsInitiator StUF:entiteittype="ZAKBTRINI">
                        <gerelateerde>
                            <natuurlijkPersoon StUF:entiteittype="NPS">
                                <BG:inp.bsn>144209007</BG:inp.bsn>
                                <BG:authentiek StUF:metagegeven="true">J</BG:authentiek>
                                <BG:geslachtsnaam>Aardenburg</BG:geslachtsnaam>
                                <BG:voorvoegselGeslachtsnaam xsi:nil="true" StUF:noValue="geenWaarde"/>
                                <BG:voorletters xsi:nil="true" StUF:noValue="geenWaarde"/>
                                <BG:voornamen xsi:nil="true" StUF:noValue="geenWaarde"/>
                                <BG:geslachtsaanduiding>V</BG:geslachtsaanduiding>
                                <BG:geboortedatum xsi:nil="true" StUF:noValue="geenWaarde"/>
                                <BG:verblijfsadres>
                                    <BG:aoa.identificatie xsi:nil="true" StUF:noValue="geenWaarde"/>
                                    <BG:wpl.woonplaatsNaam>Ons Dorp</BG:wpl.woonplaatsNaam>
                                    <BG:gor.openbareRuimteNaam></BG:gor.openbareRuimteNaam>
                                    <BG:gor.straatnaam>Beukenlaan</BG:gor.straatnaam>
                                    <BG:aoa.postcode>5665DV</BG:aoa.postcode>
                                    <BG:aoa.huisnummer>14</BG:aoa.huisnummer>
                                    <BG:aoa.huisletter xsi:nil="true" StUF:noValue="geenWaarde"/>
                                    <BG:aoa.huisnummertoevoeging xsi:nil="true" StUF:noValue="geenWaarde"/>
                                    <BG:inp.locatiebeschrijving xsi:nil="true" StUF:noValue="geenWaarde"/>
                                </BG:verblijfsadres>
                            </natuurlijkPersoon>
                        </gerelateerde>
                        <code xsi:nil="true" StUF:noValue="geenWaarde"/>
                        <omschrijving>Initiator</omschrijving>
                        <toelichting xsi:nil="true" StUF:noValue="geenWaarde"/>
                        <heeftAlsAanspreekpunt StUF:entiteittype="ZAKBTRINICTP">
                            <gerelateerde StUF:entiteittype="CTP">
                                <telefoonnummer>0624716603</telefoonnummer>
                                <emailadres>r.schram@simgroep.nl</emailadres>
                            </gerelateerde>
                        </heeftAlsAanspreekpunt>
                    </heeftAlsInitiator>
                    <heeftAlsUitvoerende xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBTRUTV"/>
                    <heeftAlsVerantwoordelijke xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBTRVRA"/>
                    <heeftAlsOverigBetrokkene StUF:entiteittype="ZAKBTROVR">
                        <gerelateerde>
                            <organisatorischeEenheid StUF:entiteittype="OEH">
                                <identificatie>1910</identificatie>
                                <naam>CZSDemo</naam>
                            </organisatorischeEenheid>
                        </gerelateerde>
                        <code xsi:nil="true" StUF:noValue="geenWaarde"/>
                        <omschrijving>Overig</omschrijving>
                        <toelichting xsi:nil="true" StUF:noValue="geenWaarde"/>
                        <heeftAlsAanspreekpunt StUF:entiteittype="ZAKBTROVRCTP">
                            <gerelateerde StUF:entiteittype="CTP"/>
                        </heeftAlsAanspreekpunt>
                    </heeftAlsOverigBetrokkene>
                    <heeftAlsHoofdzaak xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKZAKHFD"/>
                    <heeft StUF:entiteittype="ZAKSTT">
                        <gerelateerde StUF:entiteittype="STT">
                            <volgnummer>1</volgnummer>
                            <code>ontv</code>
                            <omschrijving>Verwerkt</omschrijving>
                            <ingangsdatumObject xsi:nil="true" StUF:noValue="geenWaarde"/>
                        </gerelateerde>
                        <toelichting>Dit is een toelichting</toelichting>
                        <datumStatusGezet>20150422134435539</datumStatusGezet>
                        <indicatieLaatsteStatus>J</indicatieLaatsteStatus>
                        <isGezetDoor StUF:entiteittype="ZAKSTTBTR">
                            <gerelateerde>
                                <organisatorischeEenheid StUF:entiteittype="OEH">
                                    <identificatie>1910</identificatie>
                                    <naam>CZSDemo</naam>
                                </organisatorischeEenheid>
                            </gerelateerde>
                            <rolOmschrijving>Overig</rolOmschrijving>
                            <rolomschrijvingGeneriek>Overig</rolomschrijvingGeneriek>
                        </isGezetDoor>
                    </heeft>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>19102214</identificatie>
                            <dct.omschrijving>Overig stuk intern</dct.omschrijving>
                            <creatiedatum>20150422</creatiedatum>
                            <ontvangstdatum>20150422</ontvangstdatum>
                            <titel>B0798</titel>
                            <beschrijving>Overig stuk intern Openbare ruimtemeldingen 2015</beschrijving>
                            <formaat>application/pdf</formaat>
                            <taal>nl-NL</taal>
                            <versie xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <status>Definitief</status>
                            <verzenddatum xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <vertrouwelijkAanduiding>INTERN</vertrouwelijkAanduiding>
                            <auteur>Aardenburg</auteur>
                            <link>
                            http://key2documenten-demo.mo.centric.nl/Zaken/B0798-2015/Dossiers/19101082/B0798.pdf
                            </link>
                        </gerelateerde>
                        <titel>B0798</titel>
                        <beschrijving>Overig stuk intern Openbare ruimtemeldingen 2015</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>19102215</identificatie>
                            <dct.omschrijving>Overig stuk intern</dct.omschrijving>
                            <creatiedatum>20150422</creatiedatum>
                            <ontvangstdatum>20150422</ontvangstdatum>
                            <titel>Intake verzoek.xml</titel>
                            <beschrijving>Intake verzoek</beschrijving>
                            <formaat>text/xml</formaat>
                            <taal>nl-NL</taal>
                            <versie xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <status>Definitief</status>
                            <verzenddatum xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <vertrouwelijkAanduiding>INTERN</vertrouwelijkAanduiding>
                            <auteur>Aardenburg</auteur>
                            <link>http://key2documenten-demo.mo.centric.nl/Zaken/B0798-2015/Dossiers/19101082/Intake
                            verzoek.xml
                            </link>
                        </gerelateerde>
                        <titel>Intake verzoek.xml</titel>
                        <beschrijving>Intake verzoek</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>19102216</identificatie>
                            <dct.omschrijving>Overig stuk intern</dct.omschrijving>
                            <creatiedatum>20150422</creatiedatum>
                            <ontvangstdatum>20150422</ontvangstdatum>
                            <titel>Ontvangen</titel>
                            <beschrijving>Melding openbare ruimte ontvangen (19101082)</beschrijving>
                            <formaat>message/rfc822</formaat>
                            <taal>nl-NL</taal>
                            <versie xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <status>Definitief</status>
                            <verzenddatum xsi:nil="true" StUF:noValue="geenWaarde"/>
                            <vertrouwelijkAanduiding>INTERN</vertrouwelijkAanduiding>
                            <auteur>Aardenburg</auteur>
                            <link>
                            http://key2documenten-demo.mo.centric.nl/Zaken/B0798-2015/Dossiers/19101082/Ontvangen.eml
                            </link>
                        </gerelateerde>
                        <titel>Ontvangen</titel>
                        <beschrijving>Melding openbare ruimte ontvangen (19101082)</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                    <leidtTot xsi:nil="true" StUF:noValue="geenWaarde" StUF:entiteittype="ZAKBSL"/>
                </object>
            </antwoord>
        </zakLa01>
    </s:Body>
</s:Envelope>';
                else
                    return
'<?xml version="1.0"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body>
        <zakLa01 xmlns="http://www.egem.nl/StUF/sector/zkn/0310" xmlns:StUF="http://www.egem.nl/StUF/StUF0301" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:BG="http://www.egem.nl/StUF/sector/bg/0310">
            <stuurgegevens>
                <StUF:berichtcode>La01</StUF:berichtcode>
                <StUF:zender>
                    <StUF:organisatie>BCT-OTA</StUF:organisatie>
                    <StUF:applicatie>Liber ZakenMagazijn</StUF:applicatie>
                    <StUF:administratie>Zaken</StUF:administratie>
                    <StUF:gebruiker>Systeem</StUF:gebruiker>
                </StUF:zender>
                <StUF:ontvanger>
                    <StUF:organisatie>BCT</StUF:organisatie>
                    <StUF:applicatie>StUFEFAdapter</StUF:applicatie>
                    <StUF:administratie>Zaken</StUF:administratie>
                    <StUF:gebruiker>Systeem</StUF:gebruiker>
                </StUF:ontvanger>
                <StUF:referentienummer>e0563a8e-e2ad-4558-8115-3a7ec5716bdf</StUF:referentienummer>
                <StUF:tijdstipBericht>20160823151953062</StUF:tijdstipBericht>
                <StUF:entiteittype>ZAK</StUF:entiteittype>
            </stuurgegevens>
            <parameters>
                <StUF:indicatorVervolgvraag>false</StUF:indicatorVervolgvraag>
                <StUF:aantalVoorkomens>1</StUF:aantalVoorkomens>
            </parameters>
            <antwoord>
                <object StUF:sleutelVerzendend="1045" StUF:entiteittype="ZAK">
                    <identificatie>19101081</identificatie>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                       <gerelateerde StUF:entiteittype="EDC">
                           <identificatie>10000001</identificatie>
                            <formaat>txt</formaat>
                            <auteur>SIM</auteur>
                            <titel>txt bestand.txt</titel>
                        </gerelateerde>
                        <titel>txt bestand.txt</titel>
                        <beschrijving>Overig stuk intern Openbare ruimtemeldingen 2015</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>10000002</identificatie>
                            <formaat>xlsx</formaat>
                            <auteur>SIM</auteur>
                            <titel>xlsx bestand.xlsx</titel>
                        </gerelateerde>
                        <titel>xlsx bestand.xlsx</titel>
                        <beschrijving>Intake verzoek</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>10000003</identificatie>
                            <formaat>png</formaat>
                            <auteur>SIM</auteur>
                            <titel>png bestand.png</titel>
                        </gerelateerde>
                        <titel>png bestand.png</titel>
                        <beschrijving>Melding openbare ruimte ontvangen (19101081)</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                    <heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>10000004</identificatie>
                            <formaat>JPG</formaat>
                            <auteur>SIM</auteur>
                            <titel>jpg bestand.JPG</titel>
                        </gerelateerde>
                        <titel>jpg bestand.JPG</titel>
                        <beschrijving>Status van uw aanvraag met ID 19101081 is gewijzigd: In behandeling</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
					<heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>10000005</identificatie>
                            <formaat>docx</formaat>
                            <auteur>SIM</auteur>
                            <titel>docx bestand.jpg</titel>
                        </gerelateerde>
                        <titel>docx bestand.jpg</titel>
                        <beschrijving>Status van uw aanvraag met ID 19101081 is gewijzigd: In behandeling</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
					<heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>10000006</identificatie>
                            <formaat>pdf</formaat>
                            <auteur>SIM</auteur>
                            <titel>pdf bestand.jpg</titel>
                        </gerelateerde>
                        <titel>pdf bestand.jpg</titel>
                        <beschrijving>Status van uw aanvraag met ID 19101081 is gewijzigd: In behandeling</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
					<heeftRelevant StUF:entiteittype="ZAKEDC">
                        <gerelateerde StUF:entiteittype="EDC">
                            <identificatie>10000007</identificatie>
                            <formaat stuf:noValue="waardeOnbekend" xsi:nil="true"/>
                            <auteur>SIM</auteur>
                            <titel>testbestand</titel>
                        </gerelateerde>
                        <titel>pdf bestand.jpg</titel>
                        <beschrijving>Status van uw aanvraag met ID 19101081 is gewijzigd: In behandeling</beschrijving>
                        <registratiedatum>20150422</registratiedatum>
                    </heeftRelevant>
                </object>
            </antwoord>
        </zakLa01>
    </s:Body>
</s:Envelope>';
                    default:
                        return
'<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <zkn:zakLa01 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                     xmlns:zkn="http://www.egem.nl/StUF/sector/zkn/0310" xmlns:stuf="http://www.egem.nl/StUF/StUF0301">
            <zkn:stuurgegevens xmlns:xmime="http://www.w3.org/2005/05/xmlmime">
                <stuf:berichtcode>La01</stuf:berichtcode>
                <stuf:zender>
                    <stuf:organisatie>Zuidplas</stuf:organisatie>
                    <stuf:applicatie>Liber ZakenMagazijn</stuf:applicatie>
                    <stuf:administratie>Zaken</stuf:administratie>
                    <stuf:gebruiker>Systeem</stuf:gebruiker>
                </stuf:zender>
                <stuf:ontvanger>
                    <stuf:organisatie>SIMgroep</stuf:organisatie>
                    <stuf:applicatie>PIP</stuf:applicatie>
                    <stuf:administratie/>
                    <stuf:gebruiker/>
                </stuf:ontvanger>
                <stuf:referentienummer>d3ef7833-51ab-4293-b0ce-e4b6cab21f64</stuf:referentienummer>
                <stuf:tijdstipBericht>20211105</stuf:tijdstipBericht>
                <stuf:crossRefnummer>3381175237</stuf:crossRefnummer>
                <stuf:entiteittype>ZAK</stuf:entiteittype>
            </zkn:stuurgegevens>
            <zkn:parameters xmlns:xmime="http://www.w3.org/2005/05/xmlmime">
                <zkn:indicatorVervolgvraag>false</zkn:indicatorVervolgvraag>
            </zkn:parameters>
            <zkn:antwoord/>
        </zkn:zakLa01>
    </soap:Body>
</soap:Envelope>';
        }
    }

    public function getEdcLa01MessageForIdentifier(string $identifier): string
    {
        switch($identifier){
            case "10000004":
                return
'<?xml version="1.0"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Body>
        <edcLa01 xmlns:StUF="http://www.egem.nl/StUF/StUF0301" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"  xmlns:ZKN="http://www.egem.nl/StUF/sector/zkn/0310" xmlns:BG="http://www.egem.nl/StUF/sector/bg/0310" xmlns:gml="http://www.opengis.net/gml">
            <stuurgegevens>
                <StUF:berichtcode>La01</StUF:berichtcode>
                <StUF:zender>
                    <StUF:organisatie>KING</StUF:organisatie>
                    <StUF:applicatie>KING</StUF:applicatie>
                    <StUF:gebruiker></StUF:gebruiker>
                </StUF:zender>
                <StUF:ontvanger>
                    <StUF:organisatie>KING</StUF:organisatie>
                    <StUF:applicatie>KING</StUF:applicatie>
                    <StUF:gebruiker></StUF:gebruiker>
                </StUF:ontvanger>
                <StUF:tijdstipBericht>2013041109255225</StUF:tijdstipBericht>
                <StUF:entiteittype>EDC</StUF:entiteittype>
            </stuurgegevens>
            <parameters>
                <StUF:indicatorVervolgvraag>false</StUF:indicatorVervolgvraag>
            </parameters>
            <melding>melding</melding>
            <antwoord>
                <object StUF:entiteittype="EDC">
                    <identificatie>10000004</identificatie>
                    <dct.omschrijving>Aanvraag Levensonderhoud</dct.omschrijving>
                    <dct.categorie>dct.categorie</dct.categorie>
                    <creatiedatum>20130411</creatiedatum>
                    <ontvangstdatum>20130517</ontvangstdatum>
                    <titel>jpg bestand</titel>
                    <beschrijving>beschrijving</beschrijving>
                    <formaat>jpg</formaat>
                    <taal>nld</taal>
                    <versie>1.0</versie>
                    <status>in bewerking</status>
                    <verzenddatum>20130518</verzenddatum>
                    <vertrouwelijkAanduiding>ZEER GEHEIM</vertrouwelijkAanduiding>
                    <auteur>Andre Wiel</auteur>
                    <link>http://link.to</link>
                    <inhoud StUF:bestandsnaam="dezezaak.txt">
                        VGhpcyBpcyBzb21lIHRlc3QgZGF0YQ==
                    </inhoud>
                    <isRelevantVoor StUF:entiteittype="EDCZAK">
                        <gerelateerde StUF:entiteittype="ZAK">
                            <identificatie>5700000000000000000000000000000000000001</identificatie>
                        </gerelateerde>
                    </isRelevantVoor>
                </object>
            </antwoord>
        </edcLa01>
    </s:Body>
</s:Envelope>';
            default:
                return
'<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <soap:Fault xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <faultcode>soap:Server</faultcode>
            <faultstring>Proces voor afhandelen bericht geeft fout</faultstring>
            <detail>
                <ns1:Fo02Bericht xmlns:xmime="http://www.w3.org/2005/05/xmlmime"
                                 xmlns:ns8="http://www.w3.org/2001/SMIL20/Language"
                                 xmlns:ns7="http://www.w3.org/2001/SMIL20/"
                                 xmlns:ns6="http://www.egem.nl/StUF/sector/bg/0310"
                                 xmlns:ns5="http://www.w3.org/1999/xlink" xmlns:ns4="http://www.opengis.net/gml"
                                 xmlns:ns2="http://www.egem.nl/StUF/sector/zkn/0310"
                                 xmlns:ns1="http://www.egem.nl/StUF/StUF0301">
                    <ns1:stuurgegevens>
                        <ns1:berichtcode>Fo02</ns1:berichtcode>
                    </ns1:stuurgegevens>
                    <ns1:body>
                        <ns1:code>StUF058</ns1:code>
                        <ns1:plek>server</ns1:plek>
                        <ns1:omschrijving>Proces voor afhandelen bericht geeft fout</ns1:omschrijving>
                        <ns1:details>Bestand kan niet opgehaald worden. Documentnummer kan niet bepaald worden.
                        </ns1:details>
                    </ns1:body>
                </ns1:Fo02Bericht>
            </detail>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>
                ';
        }
    }

    public function getBv03Message(): string
    {
        return
'<?xml version="1.0"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <StUF:Bv03Bericht xmlns:StUF="http://www.egem.nl/StUF/StUF0301">
            <StUF:stuurgegevens>
                <StUF:berichtcode>Bv03</StUF:berichtcode>
                <StUF:zender>
                    <StUF:applicatie>CGS</StUF:applicatie>
                </StUF:zender>
                <StUF:ontvanger>
                    <StUF:applicatie>SIMform</StUF:applicatie>
                </StUF:ontvanger>
                <StUF:referentienummer>S15163644391</StUF:referentienummer>
                <StUF:tijdstipBericht>2018012408185277</StUF:tijdstipBericht>
                <StUF:crossRefnummer>1572191056</StUF:crossRefnummer>
            </StUF:stuurgegevens>
        </StUF:Bv03Bericht>
    </soapenv:Body>
</soapenv:Envelope>';
    }

    public function processZakLv01Message(array $data, array $namespaces): string
    {
        if (
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces))
        ) {
            throw new BadRequestException('STuF and/or case namespaces missing ');
        }
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $message = $data["$env:Body"]["$caseNamespace:zakLv01"];

        if (
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == 'ZAK' &&
            $message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["@$stufNamespace:entiteittype"] == 'ZAKBTRINI' &&
            $message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["@$stufNamespace:entiteittype"] == 'BTR'
        ) {
            return $this->getLa01MessageForBSN($message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"]);
        } elseif (
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"]) &&
            isset($message["$caseNamespace:scope"]["$caseNamespace:object"]["$caseNamespace:heeftRelevant"]["$caseNamespace:gerelateerde"]["@$stufNamespace:entiteittype"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == 'ZAK' &&
            $message["$caseNamespace:scope"]["$caseNamespace:object"]["@$stufNamespace:entiteittype"] == 'ZAK' &&
            $message["$caseNamespace:scope"]["$caseNamespace:object"]["$caseNamespace:heeftRelevant"]["@$stufNamespace:entiteittype"] == 'ZAKEDC' &&
            $message["$caseNamespace:scope"]["$caseNamespace:object"]["$caseNamespace:heeftRelevant"]["$caseNamespace:gerelateerde"]["@$stufNamespace:entiteittype"] == 'EDC'
        )
        {
            return $this->getZakLa01MessageForIdentifier($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"], true);
        } elseif (
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == 'ZAK'
        ) {
            return $this->getZakLa01MessageForIdentifier($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"], false);
        }

        throw new BadRequestException('Not a valid Lv01 message');
    }

    public function processEdcLv01Message(array $data, array $namespaces): string
    {
        if (
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces))
        ) {
            throw new BadRequestException('STuF and/or case namespaces missing ');
        }

        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $message = $data["$env:Body"]["$caseNamespace:edcLv01"];

        if (
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == 'EDC'
        ) {
            return $this->getEdcLa01MessageForIdentifier($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"]);
        }
        throw new BadRequestException('Not a valid Lv01 message');
    }

    public function processEdcLk01(array $data, array $namespaces): string
    {
        if (
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces))
        ) {
            throw new BadRequestException('STuF and/or case namespaces missing ');
        }
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $message = $data["$env:Body"]["$caseNamespace:edcLk01"];

        return $this->getBv03Message();
    }
}
