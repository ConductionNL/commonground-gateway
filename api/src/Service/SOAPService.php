<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\Entity;
use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use App\Entity\Soap;
use \App\Service\EavService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class SOAPService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private EavService $eavService;
    private TranslationService $translationService;
    private GatewayService $gatewayService;

    /**
     * Translation table for case type descriptions.
     */
    const TRANSLATE_TYPE_TABLE = [
        'death_in_municipality' => 'Overlijden in gemeente',
        'intra_mun_relocation'  => 'Binnengemeentelijke verhuizing',
        'inter_mun_relocation'  => 'Inter-gemeentelijke verhuizing',
        'emigration'            => 'Emigratie',
        'resettlement'          => 'Hervestiging',
        'birth'                 => 'Geboorte',
        'confidentiality'       => 'Verstrekkingsbeperking',
        'commitment'            => 'Huwelijk/Geregistreerd partnerschap',
        'discovered_body'       => 'Lijkvinding',
        'acknowledgement'       => 'Erkenning',

    ];

    /**
     * Translation table for case statuses.
     */
    const TRANSLATE_STATUS_TABLE = [
        'incomplete'    => ['description' => 'Incompleet',      'endStatus' => false,   'explanation' => 'Zaak is incompleet'],
        'created'       => ['description' => 'Opgenomen',       'endStatus' => false,   'explanation' => 'Zaak is aangemaakt'],
        'processing'    => ['description' => 'In behandeling',  'endStatus' => false,   'explanation' => 'Zaak wordt behandeld'],
        'on_hold'       => ['description' => 'In wachtkamer',   'endStatus' => false,   'explanation' => 'Zaak staat in de wachtkamer'],
        'processed'     => ['description' => 'Verwerkt',        'endStatus' => true,    'explanation' => 'Zaak is verwerkt'],
        'cancelled'     => ['description' => 'Geannuleerd',     'endStatus' => true,    'explanation' => 'Zaak is geannuleerd'],
        'deleted'       => ['description' => 'Verwijderd',      'endStatus' => true,    'explanation' => 'Zaak is verwijderd'],
        'refused'       => ['description' => 'Geweigerd',       'endStatus' => true,    'explanation' => 'Zaak is geweigerd'],
    ];

    public function __construct(
        CommonGroundService $commonGroundService,
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        EavService $eavService,
        TranslationService $translationService,
        GatewayService $gatewayService
    )
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->eavService = $eavService;
        $this->translationService = $translationService;
        $this->gatewayService = $gatewayService;
    }

    public function getZaakType(array $data, array $namespaces): string
    {
        if (
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces)) ||
            !($bgNamespace = array_search('http://www.egem.nl/StUF/sector/bg/0310', $namespaces))

        ) {
            throw new BadRequestException('STuF and/or case namespaces missing ');
        }
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);

        return $data["$env:Body"]["$caseNamespace:zakLk01"]["$caseNamespace:object"]["$caseNamespace:isVan"]["$caseNamespace:gerelateerde"]["$caseNamespace:code"];
    }

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

    public function removeBSNPrefix(string $bsn): string
    {
        return substr($bsn, -9);
    }

    public function getCasesByBSN(string $bsn): array
    {
        $result = $this->commonGroundService->callService(
            $this->commonGroundService->getComponent('vrijbrp-dossier'),
            $this->commonGroundService->getComponent('vrijbrp-dossier')['location'].'/api/v1/dossiers/search',
            json_encode(['bsns' => [$bsn], 'paging' => ['pageSize' => 30, 'pageNumber' => 1]]),
            [],
            [],
            false,
            'POST'
        );
        $body = $result->getBody()->getContents();
        if ($result->getStatusCode() != 200 || !isset(json_decode($body, true)['result']['content'])) {
            return [];
        } else {
            return json_decode($body, true)['result']['content'];
        }
    }

    public function getCasesById(string $id): array
    {
        $result = $this->commonGroundService->callService(
            $this->commonGroundService->getComponent('vrijbrp-dossier'),
            $this->commonGroundService->getComponent('vrijbrp-dossier')['location'].'/api/v1/dossiers/search',
            json_encode(['dossierIds' => [$id], 'paging' => ['pageSize' => 30, 'pageNumber' => 1]]),
            [],
            [],
            false,
            'POST'
        );
        $body = $result->getBody()->getContents();
        if ($result->getStatusCode() != 200 || !isset(json_decode($body, true)['result']['content'])) {
            return [];
        } else {
            return json_decode($body, true)['result']['content'];
        }
    }

    public function getDocumentsByCaseId(string $id): array
    {
        $result = $this->commonGroundService->callService(
            $this->commonGroundService->getComponent('vrijbrp-dossier'),
            $this->commonGroundService->getComponent('vrijbrp-dossier')['location']."/api/v1/dossiers/$id/documents",
            '',
        );
        $body = $result->getBody()->getContents();
        if ($result->getStatusCode() != 200) {
            return [];
        } else {
            return json_decode($body, true);
        }
    }

    public function getSender(array $data, array $namespaces): array
    {
        return [
            'StUF:organisatie'  => '0637',
            'StUF:applicatie'   => 'DDX',
        ];
    }

    public function getReceiver(array $data, array $namespaces): array
    {
        return [
            'StUF:organisatie'      => 'SIMgroep',
            'StUF:applicatie'       => 'SIMform',
            'StUF:administratie'    => 'Zaken',
            'StUF:gebruiker'        => 'Systeem',
        ];
    }

    public function getSendData(array $data, array $namespaces): array
    {
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces);
        $caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces);
        $now = new DateTime('now');

        return [
            'StUF:berichtcode'      => 'La01',
            'StUF:zender'           => $this->getSender($data, $namespaces),
            'StUF:ontvanger'        => $this->getReceiver($data, $namespaces),
            'StUF:referentienummer' => Uuid::uuid4()->toString(),
            'StUF:tijdstipBericht'  => $now->format('YmdHisv'),
            'StUF:crossRefnummer'   => '000',
            'StUF:entiteittype'     => isset($data["$env:Body"]["$caseNamespace:zakLv01"]["$caseNamespace:stuurgegevens"]["$stufNamespace:entiteittype"]) ? $data["$env:Body"]["$caseNamespace:zakLv01"]["$caseNamespace:stuurgegevens"]["$stufNamespace:entiteittype"] : 'ZAK', ];
    }

    public function getLa01Skeleton(array $data): array
    {
        $namespaces = $this->getNamespaces($data);

        return [
            '@xmlns:s' => 'http://schemas.xmlsoap.org/soap/envelope/',
            's:Body'   => [
                'zakLa01' => [
                    '@xmlns'        => 'http://www.egem.nl/StUF/sector/zkn/0310',
                    '@xmlns:StUF'   => 'http://www.egem.nl/StUF/StUF0301',
                    '@xmlns:xsi'    => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:BG'     => 'http://www.egem.nl/StUF/sector/bg/0310',
                    'stuurgegevens' => $this->getSendData($data, $namespaces),
                    'parameters'    => ['StUF:indicatorVervolgvraag' => 'false'],
                ],
            ],
        ];
    }

    public function translateType(array $case): ?string
    {
        if (!isset($case['type']['code'])) {
            return null;
        } else {
            return self::TRANSLATE_TYPE_TABLE[$case['type']['code']] ?? null;
        }
    }

    public function translateStatus(array $case): array
    {
        if (!isset($case['status']['code'])) {
            return [];
        } else {
            return self::TRANSLATE_STATUS_TABLE[$case['status']['code']] ?? [];
        }
    }

    public function getCaseType(array $case): array
    {
        return [
            '@StUF:entiteittype'    => 'ZAKZKT',
            'gerelateerde'          => [
                '@StUF:entiteittype'    => 'ZKT',
                'omschrijving'          => $this->translateType($case) ?? '',
                'code'                  => $case['type']['code'] ?? '',
            ],
        ];
    }

    public function getCaseStatus(array $case, array $status): array
    {
        $endDate = isset($case['status']['entryDateTime']) ? new DateTime($case['status']['entryDateTime']) : null;

        return [
            '@StUF:entiteittype'    => 'ZAKSTT',
            'gerelateerde'          => [
                '@StUF:entiteittype'    => 'STT',
                'volgnummer'            => '1',
                'code'                  => $case['status']['code'] ?? '',
                'omschrijving'          => $status['description'] ?? '',
            ],
            'toelichting'               => $status['explanation'],
            'datumStatusGezet'          => $endDate->format('YmdHisv'),
            'indicatieLaatsteStatus'    => isset($status['endStatus']) ? ($status['endStatus'] ? 'J' : 'N') : 'N',
        ];
    }

    public function getResult(array $case): array
    {
        return [
            'omschrijving'  => '',
            'toelichting'   => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
        ];
    }

    public function addCaseTypeDetails(array $case, array &$result): void
    {
        $details = [
            'omschrijvingGeneriek'      => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'zaakcategorie'             => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'trefwoord'                 => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'doorlooptijd'              => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'servicenorm'               => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'archiefcode'               => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'vertrouwelijkAanduiding'   => 'ZAAKVERTROUWELIJK',
            'publicatieIndicatie'       => 'N',
            'publicatieTekst'           => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'ingangsdatumObject'        => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'einddatumObject'           => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
        ];

        $result['isVan']['gerelateerde'] = array_merge($result['isVan']['gerelateerde'], $details);
    }

    public function addStatusDetails(array $case, array &$result): void
    {
        $details = [
            'gerelateerde'  => [
                'ingangsdaumObject' => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            ],
            'isGezetDoor'   => [
                '@StUF:entiteittype'    => 'ZAKSTTBTR',
                'gerelateerde'          => [
                    'organisatorischeEenheid'   => [
                        '@StUF:entiteittype'    => 'OEH',
                        'identificatie'         => '1910',
                        'naam'                  => 'CZSDemo',
                    ],
                ],
                'rolOmschrijving'           => 'Overig',
                'rolomschrijvingGeneriek'   => 'Overig',
            ],
        ];
        $result['heeft'] = array_merge_recursive($result['heeft'], $details);
    }

    public function addCaseDetails(array $case, array &$result): void
    {
        $details = [
            'opschorting'               => ['indicatie' => 'N', 'reden' => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde']],
            'verlenging'                => ['duur' => '0',      'reden' => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde']],
            'betalingsIndicatie'        => 'N.v.t.',
            'laatsteBetaalDatum'        => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'archiefnominatie'          => 'N',
            'datumVernietigingDossier'  => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
            'zaakniveau'                => '1',
            'deelzakenIndicatie'        => 'N',
            'StUF:extraElementen'       => [
                'StUF:extraElement' => ['@naam' => 'kanaalcode', '#' => 'web'],
            ],
        ];
        if (!isset($result['toelichting']) || !$result['toelichting']) {
            $result['toelichting'] = ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenwaarde'];
        }
        $result = array_slice($result, 0, array_search('isVan', array_keys($result)), true) + $details + array_slice($result, array_search('isVan', array_keys($result)), null, true);
        $this->addCaseTypeDetails($case, $result);

        $details = [
            'heeftBetrekkingOp'         => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKOBJ'],
            'heeftAlsBelanghebbende'    => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRBLH'],
            'heeftAlsGemachtigde'       => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRGMC'],
            'heeftAlsInitiator'         => [
                '@StUF:entiteittype'    => 'ZAKBTRINI',
                'gerelateerde'          => [
                    'natuurlijkPersoon' => [
                        '@StUF:entiteittype'            => 'NPS',
                        'BG:inp.bsn'                    => '144209007',
                        'BG:authentiek'                 => ['@StUF:metagegeven' => 'true', '#' => 'J'],
                        'BG:geslachtsnaam'              => 'Aardenburg',
                        'BG:voorvoegselGeslachtsnaam'   => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                        'BG:voorletters'                => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                        'BG:voornamen'                  => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                        'BG:geslachtsaanduiding'        => 'V',
                        'BG:geboortedatum'              => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                        'BG:verblijfsadres'             => [
                            'BG:aoa.identificatie'          => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                            'BG:wpl.woonplaatsNaam'         => 'Ons Dorp',
                            'BG:gor.openbareRuimteNaam'     => '',
                            'BG:gor.straatnaam'             => 'Beukenlaan',
                            'BG:aoa.postcode'               => '5665DV',
                            'BG:aoa.huisnummer'             => '14',
                            'BG:aoa.huisletter'             => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                            'BG:aoa.huisnummertoevoeging'   => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                            'BG:inp.locatiebeschrijving'    => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                        ],
                    ],
                ],
                'code'                  => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                'omschrijving'          => 'Initiator',
                'toelichting'           => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                'heeftAlsAanspreekpunt' => [
                    '@StUF:entiteittype' => 'ZAKBTRINICTP',
                    'gerelateerde'       => [
                        '@StUF:entiteittype'    => 'CTP',
                        'telefoonnummer'        => '0624716603',
                        'emailadres'            => 'r.schram@simgroep.nl',
                    ],
                ],
            ],
            'heeftAlsUitvoerende'       => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRUTV'],
            'heeftAlsVerantwoordelijke' => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRVRA'],
            'heeftAlsOverigBetrokkene'  => [
                '@StUF:entiteittype'    => 'ZAKBTROVR',
                'gerelateerde'          => [
                    'organisatorischeEenheid'   => [
                        '@StUF:entiteittype'    => 'OEH',
                        'identificatie'         => '1910',
                        'naam'                  => 'CZSDemo',
                    ],
                ],
                'code'                  => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                'omschrijving'          => 'Overig',
                'toelichting'           => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
                'heeftAlsAanspreekPunt' => [
                    '@StUF:entiteittype'    => 'ZAKBTROVRCTP',
                    'gerelateerde'          => ['@StUF:entiteittype' => 'CTP'],
                ],
            ],
            'heeftAlsHoofdzaak' => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKZAKHFD'],
        ];
        $result = array_slice($result, 0, array_search('heeft', array_keys($result)), true) + $details + array_slice($result, array_search('heeft', array_keys($result)), null, true);
        $this->addStatusDetails($case, $result);
    }

    public function caseToObject(array $case, bool $details = false): array
    {
        $startDate = new DateTime($case['startDate']);
        $publicationDate = new DateTime($case['entryDateTime']);
        $status = $this->translateStatus($case);
        $endDate = isset($case['status']['entryDateTime']) && $case['status']['endStatus'] ? new DateTime($case['status']['entryDateTime']) : null;
//        var_dump($status);
        /* @TODO There is not yet a field to derive end dates from, as well as a field for the description of a case. */
        $result = [
            '@StUF:sleutelVerzendend'   => $case['dossierId'],
            '@StUF:entiteittype'        => 'ZAK',
            'identificatie'             => $case['dossierId'],
            'omschrijving'              => $case['description'],
            'toelichting'               => null,
            'resultaat'                 => $details ? $this->getResult($case) : [],
            'registratiedatum'          => $publicationDate->format('Ymd'),
            'startdatum'                => $startDate->format('Ymd'),
            'publicatiedatum'           => $publicationDate->format('Ymd'),
            'einddatumGepland'          => '',
            'einddatum'                 => $endDate ? $endDate->format('Ymd') : '',
            'uiterlijkeEinddatum'       => '',
            'isVan'                     => $this->getCaseType($case),
            'heeft'                     => $this->getCaseStatus($case, $status),
            'heeftRelevant'             => $details ? $this->getDocumentObjects($this->getDocumentsByCaseId($case['dossierId']), $case['dossierId']) : null,
            'leidtTot'                  => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBSL'],
        ];
        if ($details) {
            $this->addCaseDetails($case, $result);
        }

        return $result;
    }

    public function casesToObjects(array $cases, bool $details = false): array
    {
        $result = ['object' => []];
        foreach ($cases as $case) {
            $result['object'][] = array_filter($this->caseToObject($case, $details), function ($value) {return $value !== null && $value !== false && $value !== []; });
        }

        return $result;
    }

    public function getLa01MessageForBSN(string $bsn, array $data): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        $cases = $this->getCasesByBSN($this->removeBSNPrefix($bsn));
        $result = $this->getLa01Skeleton($data);
        $objects = $this->casesToObjects($cases);

        $objects['object'] ? $result['s:Body']['zakLa01']['antwoord'] = $objects : null;
//        var_dump($result);

        return $xmlEncoder->encode($result, 'xml', ['remove_empty_tags' => false]);
    }

    public function addParameters(array &$result, array $documents): void
    {
        $result['s:Body']['zakLa01']['parameters']['StUF:aantalVoorkomens'] = count($documents);
    }

    public function getDocumentObject(array $document, string $identifier): array
    {
//        var_dump($document);
        $filenameArray = explode('.', $document['filename']);
        $creationDate = new DateTime($document['entryDateTime']);

        return [
            '@StUF:entiteittype'    => 'ZAKEDC',
            'gerelateerde'          => [
                '@StUF:entiteittype'    => 'EDC',
                'identificatie'         => "$identifier.{$document['id']}",
                'formaat'               => end($filenameArray),
                'auteur'                => 'SIM',
                'titel'                 => $document['filename'],
            ],
            'titel'                 => $document['filename'],
            'beschrijving'          => $document['title'],
            'registratiedatum'      => $creationDate->format('Ymd'),
        ];
    }

    public function getDocumentObjects(array $documents, string $identifier): array
    {
        $results = [];
        foreach ($documents as $document) {
            $results[] = $this->getDocumentObject($document, $identifier);
        }

        return $results;
    }

    public function getZakLa01MessageForIdentifier(string $identifier, array $data, bool $documents): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        $result = $this->getLa01Skeleton($data);
        if (!$documents) {
            $cases = $this->getCasesById($identifier);
            $objects = $this->casesToObjects($cases, true);
            $objects['object'] ? $result['s:Body']['zakLa01']['antwoord'] = $objects : null;

            return $xmlEncoder->encode($result, 'xml', ['remove_empty_tags' => false]);
        } else {
            $documents = $this->getDocumentsByCaseId($identifier);
            $this->addParameters($result, $documents);
            $result['s:Body']['zakLa01']['antwoord']['object']['identificatie'] = $identifier;
            $result['s:Body']['zakLa01']['antwoord']['object']['heeftRelevant'] = $this->getDocumentObjects($documents, $identifier);
            $result['s:Body']['zakLa01']['antwoord']['object']['@StUF:sleutelVerzendend'] = $identifier;
            $result['s:Body']['zakLa01']['antwoord']['object']['@StUF:entiteittype'] = 'ZAK';

            return $xmlEncoder->encode($result, 'xml', ['remove_empty_tags' => false]);
        }
    }

    public function getSenderEdc(): array
    {
        return [
            'StUF:organisatie'  => 'KING',
            'StUF:applicatie'   => 'KING',
            'StUF:gebruiker'    => '',
        ];
    }

    public function getReceiverEdc(): array
    {
        return [
            'StUF:organisatie'  => 'KING',
            'StUF:applicatie'   => 'KING',
            'StUF:gebruiker'    => '',
        ];
    }

    public function getEdcLa01Skeleton($document): array
    {
        $now = new DateTime('now');

        return [
            '@xmlns:s'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            's:Body'    => [
                'edcLa01'   => [
                    '@xmlns:StUF'   => 'http://www.egem.nl/StUF/StUF0301',
                    '@xmlns:xlink'  => 'http://www.w3.org/1999/xlink',
                    '@xmlns:xsi'    => 'http://www.w3.org/2001/XMLSchema-instance',
                    '@xmlns:ZKN'    => 'http://www.egem.nl/StUF/sector/zkn/0310',
                    '@xmlns:BG'     => 'http://www.egem.nl/StUF/sector/bg/0310',
                    '@xmlns:gml'    => 'http://www.opengis.net/gml',
                    'stuurgegevens' => [
                        'StUF:berichtcode'      => 'La01',
                        'StUF:zender'           => $this->getSenderEdc(),
                        'StUF:ontvanger'        => $this->getReceiverEdc(),
                        'StUF:tijdstipBericht'  => $now->format('YmdHisv'),
                        'StUF:entiteittype'     => 'EDC',
                    ],
                    'parameters'    => [
                        'StUF:indicatorVervolgvraag'    => 'false',
                    ],
                    'melding'       => 'melding',
                    'antwoord'      => [],
                ],
            ],
        ];
    }

    public function getDetailedEdcObject(array $document, string $identifier, string $data, string $location): array
    {
        $filenameArray = explode('.', $document['filename']);
        $creationDate = new DateTime($document['entryDateTime']);
        $identifierArray = explode('.', $identifier);

        return [
            'object' => [
                '@StUF:entiteittype'        => 'EDC',
                'identificatie'             => $identifier,
                'dct.omschrijving'          => $document['title'],
                'dct.categorie'             => 'dct.categorie',
                'creatieDatum'              => $creationDate->format('Ymd'),
                'ontvangstDatum'            => $creationDate->format('Ymd'),
                'titel'                     => $document['title'],
                'beschrijving'              => $document['title'],
                'formaat'                   => end($filenameArray),
                'taal'                      => 'nld',
                'versie'                    => '1.0',
                'status'                    => 'in bewerking', //@TODO: find correct status
                'verzenddatum'              => $creationDate->format('Ymd'),
                'vertrouwelijkAanduiding'   => 'ZEER GEHEIM',
                'auteur'                    => 'SIM',
                'link'                      => $location,
                'inhoud'                    => [
                    '@StUF:bestandsnaam'    => $document['filename'],
                    '#'                     => $data,
                ],
                'isRelevantVoor'            => [
                    '@StUF:entiteittype'    => 'EDCZAK',
                    'gerelateerde'          => [
                        '@StUF:entiteittype'    => 'ZAK',
                        'identificatie'         => $identifierArray[0],
                    ],
                ],
            ],
        ];
    }

    public function getDocumentById(string $identifier): array
    {
        $identifierArray = explode('.', $identifier);
        $documents = $this->getDocumentsByCaseId($identifierArray[0]);
        foreach ($documents as $document) {
            if ($document['id'] == end($identifierArray)) {
                return $document;
            }
        }

        return [];
    }

    public function getDocumentData(string $identifier): array
    {
        $identifierArray = explode('.', $identifier);
        $result = $this->commonGroundService->callService(
            $this->commonGroundService->getComponent('vrijbrp-dossier'),
            $this->commonGroundService->getComponent('vrijbrp-dossier')['location']."/api/v1/dossiers/{$identifierArray[0]}/documents/{$identifierArray[1]}",
            '',
            [],
            ['accept' => 'application/octet-stream']
        );
        $body = $result->getBody()->getContents();
        if ($result->getStatusCode() != 200) {
            return [];
        } else {
            return [
                'location' => $this->commonGroundService->getComponent('vrijbrp-dossier')['location']."/api/v1/dossiers/{$identifierArray[0]}/documents/{$identifierArray[1]}",
                'data'     => base64_encode($body),
            ];
        }
    }

    public function getFo02Message(): array
    {
        return [
            '@xmlns:s'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            's:Body'    => [
                's:Fault'   => [
                    '@xmlns:s'          => 'http://schemas.xmlsoap.org/soap/envelope/',
                    'faultcode'         => 'soap:Server',
                    'faultstring'       => 'Proces voor afhandelen bericht geeft fout',
                    'ns1:Fo02Bericht'   => [
                        '@xmlns:xmime'      => 'http://www.w3.org/2005/05/xmlmime',
                        '@xmlns:ns8'        => 'http://www.w3.org/2001/SMIL20/Language',
                        '@xmlns:ns7'        => 'http://www.w3.org/2001/SMIL20/',
                        '@xmlns:ns6'        => 'http://www.egem.nl/StUF/sector/bg/0310',
                        '@xmlns:ns5'        => 'http://www.w3.org/1999/xlink',
                        '@xmlns:ns2'        => 'http://www.egem.nl/StUF/sector/zkn/0310',
                        '@xmlns:ns1'        => 'http://www.egem.nl/StUF/StUF0301',
                        'ns1:stuurgegevens' => [
                            'ns:berichtcode'    => 'Fo02',
                        ],
                        'ns1:body'          => [
                            'ns1:code'          => 'StUF058',
                            'ns1:plek'          => 'server',
                            'ns1:omschrijving'  => 'Proces voor afhandelen bericht geeft fout',
                            'ns1:details'       => 'Bestand kan niet opgehaald worden. Documentnummer kan niet bepaald worden.',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getEdcLa01MessageForIdentifier(string $identifier): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        $document = $this->getDocumentById($identifier);
        $data = $this->getDocumentData($identifier);
        if ($document) {
            $result = $this->getEdcLa01Skeleton($document);
            $result['s:Body']['edcLa01']['antwoord'] = $this->getDetailedEdcObject($document, $identifier, $data['data'], $data['location']);

            return $xmlEncoder->encode($result, 'xml', ['remove_empty_tags' => false]);
        }

        return $xmlEncoder->encode($this->getFo02Message(), 'xml', ['remove_empty_tags' => false]);
    }

    public function getBv03Message(): array
    {
        $now = new DateTime('now');

        return [
            '@xmlns:s'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            's:Body'    => [
                'StUF:Bv03Bericht' => [
                    '@xmlns:StUF'           => 'http://www.egem.nl/StUF/StUF0301',
                    'StUF:stuurgegevens'    => [
                        'StUF:berichtcode'      => 'Bv03',
                        'StUF:zender'           => ['StUF:applicatie'   => 'CGS'],
                        'StUF:ontvanger'        => ['StUF:applicatie'   => 'SIMform'],
                        'StUF:referentienummer' => 'S15163644391',
                        'StUF:tijdstipBericht'  => $now->format('YmdHisv'),
                        'StUF:crossRefnummer'   => '1572191056',
                    ],
                ],
            ],
        ];
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
            return $this->getLa01MessageForBSN($message["$caseNamespace:gelijk"]["$caseNamespace:heeftAlsInitiator"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"], $data);
        } elseif (
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"]) &&
            isset($message["$caseNamespace:scope"]["$caseNamespace:object"]["$caseNamespace:heeftRelevant"]["$caseNamespace:gerelateerde"]["@$stufNamespace:entiteittype"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == 'ZAK' &&
            $message["$caseNamespace:scope"]["$caseNamespace:object"]["@$stufNamespace:entiteittype"] == 'ZAK' &&
            $message["$caseNamespace:scope"]["$caseNamespace:object"]["$caseNamespace:heeftRelevant"]["@$stufNamespace:entiteittype"] == 'ZAKEDC' &&
            $message["$caseNamespace:scope"]["$caseNamespace:object"]["$caseNamespace:heeftRelevant"]["$caseNamespace:gerelateerde"]["@$stufNamespace:entiteittype"] == 'EDC'
        ) {
            return $this->getZakLa01MessageForIdentifier($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"], $data, true);
        } elseif (
            isset($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"]) &&
            $message["$caseNamespace:gelijk"]["@$stufNamespace:entiteittype"] == 'ZAK'
        ) {
            return $this->getZakLa01MessageForIdentifier($message["$caseNamespace:gelijk"]["$caseNamespace:identificatie"], $data, false);
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

    public function processEdcLk01(array $data, array $namespaces, Request $request): string
    {
        $item = $this->cache->getItem(md5($request->getClientIp()));
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        if (
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces))
        ) {
            throw new BadRequestException('STuF and/or case namespaces missing ');
        }
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $message = $data["$env:Body"]["$caseNamespace:edcLk01"];
        $resource = [
            'title'     => $message["$caseNamespace:object"]["$caseNamespace:dct.omschrijving"],
            'filename'  => $message["$caseNamespace:object"]["$caseNamespace:inhoud"]["@$stufNamespace:bestandsnaam"],
            'content'   => $message["$caseNamespace:object"]["$caseNamespace:inhoud"]['#'],
        ];
        $url = $this->commonGroundService->getComponent('vrijbrp-dossier')['location']."/api/v1/dossiers/{$message["$caseNamespace:object"]["$caseNamespace:isRelevantVoor"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"]}/documents";

        $result = $this->commonGroundService->callService(
            $this->commonGroundService->getComponent('vrijbrp-dossier'),
            $url,
            json_encode($resource),
            [],
            [],
            false,
            'POST'
        );
        if ($result->getStatusCode() != 201) {
            return $xmlEncoder->encode($this->getFo02Message(), 'xml');
        } else {
            $item->set("{$message["$caseNamespace:object"]["$caseNamespace:isRelevantVoor"]["$caseNamespace:gerelateerde"]["$caseNamespace:identificatie"]}.".json_decode($result->getBody()->getContents(), true)['id']);
            $item->expiresAfter(new \DateInterval('PT1H'));
            $this->cache->save($item);

            return $xmlEncoder->encode($this->getBv03Message(), 'xml');
        }
    }

    public function generateDu02(?string $id, string $type, string $entityType): array
    {
        $now = new DateTime('now');

        return [
            '@xmlns:s'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            's:Body'    => [
                "ZKN:{$type}_Du02"    => [
                    '@xmlns:ZKN'        => 'http://www.egem.nl/StUF/sector/zkn/0310',
                    'ZKN:stuurgegevens' => [
                        'StUF:berichtcode'      => [
                            '@xmlns:StUF'   => 'http://www.egem.nl/StUF/StUF0301',
                            '#'             => 'Du02',
                        ],
                        'StUF:zender'           => [
                            '@xmlns:StUF'       => 'http://www.egem.nl/StUF/StUF0301',
                            'ns1:organisatie'   => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            ],
                            'ns1:applicatie'    => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                                '#'             => 'CGS',
                            ],
                            'ns1:administratie'   => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            ],
                            'ns1:gebruiker'   => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            ],
                        ],
                        'StUF:ontvanger'        => [
                            '@xmlns:StUF'       => 'http://www.egem.nl/StUF/StUF0301',
                            'ns1:organisatie'   => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            ],
                            'ns1:applicatie'    => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                                '#'             => 'SIMform',
                            ],
                            'ns1:administratie'   => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            ],
                            'ns1:gebruiker'   => [
                                '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            ],
                        ],
                        'ns1:referentienummer'  => [
                            '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            '#'             => '2093235189',
                        ],
                        'ns1:tijdstipBericht'  => [
                            '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            '#'             => $now->format('YmdHis'),
                        ],
                        'StUF:crossRefnummer'  => [
                            '@xmlns:StUF'   => 'http://www.egem.nl/StUF/StUF0301',
                            '#'             => '2093235189',
                        ],
                        'ns1:functie'            => [
                            '@xmlns:ns1'    => 'http://www.egem.nl/StUF/StUF0301',
                            '#'             => $type,
                        ],
                    ],
                    "ZKN:$entityType"      => [
                        '@xmlns:StUF'           => 'http://www.egem.nl/StUF/StUF0301',
                        '@StUF:functie'         => 'entiteit',
                        '@StUF:entiteittype'    => $entityType == 'zaak' ? 'ZAK' : 'EDC',
                        'ZKN:identificatie'     => $id,
                    ],
                ],
            ],
        ];
    }

    public function processDi02(array $data, array $namespaces, string $type, Request $request): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        if (
            !($stufNamespace = array_search('http://www.egem.nl/StUF/StUF0301', $namespaces)) ||
            !($caseNamespace = array_search('http://www.egem.nl/StUF/sector/zkn/0310', $namespaces))
        ) {
            throw new BadRequestException('STuF and/or case namespaces missing ');
        }
        $env = array_search('http://schemas.xmlsoap.org/soap/envelope/', $namespaces);
        $message = $data["$env:Body"]["$caseNamespace:{$type}_Di02"];

        if ($message["$caseNamespace:stuurgegevens"]["$stufNamespace:functie"] == 'genereerDocumentidentificatie') {
            $item = $this->cache->getItem(md5($request->getClientIp()));
            if ($item->isHit()) {
                return $xmlEncoder->encode($this->generateDu02($item->get(), $type, 'document'), 'xml');
            } else {
                return $xmlEncoder->encode($this->generateDu02(null, $type, 'document'), 'xml');
            }
        }

        if ($message["$caseNamespace:stuurgegevens"]["$stufNamespace:functie"] == 'genereerZaakidentificatie') {
            return $xmlEncoder->encode($this->generateDu02(Uuid::uuid4()->toString(), $type, 'zaak'), 'xml');
        }

        return $xmlEncoder->encode($this->getFo02Message(), 'xml');
    }


    /**
     * This function handles generic SOAP INCOMMING SOAP calls based on the soap entity
     *
     * @param Soap $soap
     * @param array $data
     * @param array $namespaces
     * @param Request $request
     * @return string
     */
    public function handleRequest(Soap $soap, array $data, array $namespaces, Request $request): string
    {

        // Lets make the entity call
        //$entity = $this->soapToEntity($soap, $data);

        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'SOAP-ENV:Envelope', 'xml_encoding' => 'utf-8', 'encoder_ignored_node_types' => [\XML_CDATA_SECTION_NODE]]);
        $entity = $this->translationService->dotHydrator($soap->getRequest() ? $xmlEncoder->decode($soap->getRequest(), 'xml') : [],$data,$soap->getRequestHydration());

        $requestBase = [
            "path" => $soap->getToEntity()->getRoute(),
            "id" => null,
            "extension" => "xml",
            "renderType" => "xml",
            "result" => null,
        ];


        if($soap->getType() !== 'npsLv01-prs-GezinssituatieOpAdresAanvrager')
            $object = $this->eavService->generateResult($request, $soap->getToEntity(), $requestBase, $entity)['result'];
        else
            $object = $this->getLa01Hydration($entity, $soap);

        // Lets hydrate the returned data into our reponce, with al little help from https://github.com/adbario/php-dot-notation
        return $this->translationService->parse(
            $xmlEncoder->encode($this->translationService->dotHydrator($soap->getResponse() ? $xmlEncoder->decode($soap->getResponse(), 'xml') : [], $object, $soap->getResponseHydration()), 'xml'), true);
    }

    private function parseDate(string $date): string
    {
        $date = strlen($date) == 6 ? new \DateTime("20".$date) : new \DateTime($date);

        return $date->format("Y-m-d");
    }

    private function getValue(array $extraElementen, string $name)
    {
        foreach($extraElementen as $extraElement){
            if($extraElement['@naam'] == $name){
                return $extraElement['#'];
            }
        }
        return null;
    }

    private function flattenExtraElements(array $extraElementen): array
    {
        $result = [];
        foreach ($extraElementen as $extraElement){
            $value = $extraElement['#'];
            $key = $extraElement['@naam'];
            if(strpos($key, 'datum') !== false || strpos($key, 'Datum') !== false){
                $value = $this->parseDate($value);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function getIndiener(string $firstnames, string $lastname, string $dateOfBirth): string
    {
        $bsn = '';

        $this->commonGroundService->getResourceList(['component' => 'brp', 'resource' => 'ingeschrevenPersonen'], ['geboorte__datum' => $dateOfBirth, 'naam__geslachtsnaam' => $lastname, 'naam__voornamen' => $firstnames]);

        return $bsn;
    }

    private function translateValueType(string $valueType)
    {
        switch($valueType){
            case 'voornamen':
                return 'firstname';
            case 'voorvoegselGeslachtsnaam':
                return 'prefix';
            case 'geslachtsnaam':
                return 'lastname';
            case 'geboortedatum':
                return 'birthdate';
            case 'naam':
                return 'name';
            case 'geslachtsaanduiding':
                return 'gender';
            default:
                return $valueType;
        }
    }

    private function getPersonDetail($key, $value, $array, array $oneOf): array
    {
        $valueTypes = $oneOf;
        foreach($valueTypes as $valueType) {
            if(strpos($key, $valueType) !== false && is_numeric(substr($key, strlen($valueType)))){
                if($valueType == 'geboortedatum'){
                    $date = new \DateTime($value);
                    $value = $date > new DateTime() ? (int) $date->modify('-100 years')->format('Ymd') : (int) $date->format('Ymd');
                }
                $array[substr($key, strlen($valueType))-1][$this->translateValueType($valueType)] = $value;
                break;
            }
        }
        return $array;
    }

    private function getWitnesses(array $data): array
    {
        $result = ['chosen' => []];

        foreach($data as $key=>$datum){
            $result['chosen'] = $this->getPersonDetail($key, $datum, $result['chosen'], ['voornamen', 'voorvoegselGeslachtsnaam', 'geslachtsnaam', 'geboortedatum', 'bsn']);
        }
        $result['numberOfMunicipalWitnesses'] = isset($data['verzorgdgem']) ? $data['verzorgdgem'] : 0;

        return $result;
    }

    private function getOfficials(array $data): array
    {
        $result = ['preferences' => []];

        foreach($data as $key=>$datum){
            $result['preferences'] = $this->getPersonDetail($key, $datum, $result['preferences'], ['naam']);
        }

        return $result;
    }


    private function getPersonDetails(array $data, array $valueTypes, array $result): array
    {
        foreach($data as $key=>$datum){
            $result = $this->getPersonDetail($key, $datum, $result, $valueTypes);
        }

        return $result;
    }

    private function filterChildren(array $children): array
    {
        foreach($children as &$child){
            $time = new \DateTime($child['geboortetijd']);
            $date = new \DateTime($child['birthdate']);
            $dateTime = new \DateTime($date->format('Y-m-d\T').$time->format('H:i:sO'));
            $child['birthDateTime'] = $dateTime->format('Y-m-d\TH:i:s');

            unset($child['geboortetijd'], $child['birthdate']);
        }
        return $children;
    }

    /**
     * Finds specific values and parses them.
     *
     * @param array $data
     * @param array $namespaces
     * @param string $messageType
     * @param string|null $zaaktype
     * @return array
     */
    public function preRunSpecificCode(array $data, array $namespaces, string $messageType, ?string $zaaktype = null): array
    {
        $permissionRequired = ['inwonend'];
        if($messageType == 'zakLk01'){
            $extraElementen = $data['SOAP-ENV:Body']['ns2:zakLk01']['ns2:object']['ns1:extraElementen']['ns1:extraElement'];
        }
        $data = new \Adbar\Dot($data);

        // Huwelijk
        if($messageType == 'zakLk01' && $zaaktype == 'B0337')
        {
            $data->set('date', $this->parseDate($data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:registratiedatum")));
            $data->merge($this->flattenExtraElements($extraElementen));
            $time = new \DateTime($data->get('verbintenisTijd'));
            $dateTime = new \DateTime($data->get('verbintenisDatum').'T'.$time->format('H:i:s'));
            $data->set('commitmentDateTime', $dateTime->format('Y-m-d\TH:i:s\Z'));
            $data->set('witnesses', json_encode($this->getWitnesses($data->flatten())));
            $data->set('officials', json_encode($this->getOfficials($data->flatten())));
        }

        // Birth
        if($messageType == 'zakLk01' && $zaaktype == 'B0237')
        {
            $data->set('date', $this->parseDate($data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:registratiedatum")));
            $data->merge($this->flattenExtraElements($extraElementen));
            $data->set('children', json_encode($this->filterChildren($this->getPersonDetails($data->flatten(), ['voornamen', 'geboortedatum', 'geboortetijd', 'geslachtsaanduiding'], []))));

            if($data->has('inp.bsn')){
                $data->set('parent2Bsn', $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"]);
            } else {
                $data->set('inp.bsn', $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"]);
            }
        }



        // Emigratie
        if($messageType == 'zakLk01' && $zaaktype == 'B1425')
        {
            $data->set(
                "SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.3.#",
                $this->parseDate($data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.3.#")));

            if($data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.8.#") == 'Ja'){
                $relocators = explode(',', $data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.9.#"));
            }
            $relocators[] = $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"];
            $relocatorsString = '<ns10:MeeEmigranten xmlns:ns10="urn:nl/procura/gba/v1.5/diensten/emigratie">';
            foreach($relocators as $relocator){
                $relocatorsString .= "<ns10:MeeEmigrant>
                    <ns10:Burgerservicenummer>$relocator</ns10:Burgerservicenummer>
                    <ns10:OmschrijvingAangifte>G</ns10:OmschrijvingAangifte>
                        <ns10:Duur>k</ns10:Duur>
                </ns10:MeeEmigrant>";
            }
            $relocatorsString .= '</ns10:MeeEmigranten>';
            $data->set('relocators', $relocatorsString);
        }

        // Verhuizing
        if($messageType == 'zakLk01' && ($zaaktype == 'B0366' || $zaaktype == 'B0367'))
        {
            $wijzeBewoning = $this->getValue($extraElementen, 'wijzeBewoning');
            if(in_array($wijzeBewoning, $permissionRequired)){
                $data->set('liveIn', json_encode([
                    'liveInApplicable'  => true,
                    'consent'           => "PENDING",
                    'consenter'         => ['bsn' => $this->getValue($extraElementen, 'inp.bsn')],
                ]));
                $data->set('mainOccupant', json_encode([
                    'bsn' => $this->getValue($extraElementen, 'inp.bsn'),
                ]));
            } else {
                $data->set('liveIn', json_encode([
                    'liveInApplicable'  => false,
                    'consent'           => "NOT_APPLICABLE",
                ]));
            }
            $data->set(
            "date",
            $this->parseDate($this->getValue($extraElementen, 'datumVerhuizing')));

            $relocators = [['bsn' => $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"], 'declarationType' => 'REGISTERED']];
            if($this->getValue($extraElementen,"indMeeverhuizers") !== 'nee'){
                foreach(explode(',', $this->getValue($extraElementen, 'geselecteerd')) as $relocator){
                    $relocators[] = ['bsn' => $relocator, 'declarationType' => 'REGISTERED'];
                }
            }
            $data->set('relocators', json_encode($relocators));
            $data->set('numberOfResidents', $this->getValue($extraElementen, 'aantalPersonenOpNieuweAdres'));
        }

        // Geheimhouding
        if($messageType == 'zakLk01' && $zaaktype == 'B0328')
        {
            $data->set('requesterBsn',$data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"]);

            if($data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.3.#") == 'mijzelf')
                $data->set('bsn', $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"]);
            else{
                $data->set('bsn', $data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.4.#"));
                $data->set("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.4.#", $data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.5.#"));
            }
        }

        // Uittreksel
        if($messageType == 'zakLk01' && $zaaktype == 'B0255')
        {
            $data->set('requesterBsn', $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"]);

            if($data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.4.#") == 'mijzelf')
                $data->set('bsn', $data->flatten()["SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns2:heeftAlsInitiator.ns2:gerelateerde.ns2:natuurlijkPersoon.ns3:inp.bsn"]);
            else{
                $data->set('bsn', $data->get("SOAP-ENV:Body.ns2:zakLk01.ns2:object.ns1:extraElementen.ns1:extraElement.5.#"));
            }
        }

        if($messageType == 'OntvangenIntakeNotificatie' && ($zaaktype == 'B0366' || $zaaktype == 'B0367'))
        {
            $wijzeBewoning = $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.Body.SIMXML.ELEMENTEN.WIJZE_BEWONING");
            if(in_array($wijzeBewoning, $permissionRequired)){
                $data->set('liveIn', json_encode([
                    'liveInApplicable'  => true,
                    'consent'           => "PENDING",
                    'consenter'         => ['bsn' => $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.Body.SIMXML.ELEMENTEN.BSN_HOOFDBEWONER")],
                ]));
                $data->set('mainOccupant', json_encode([
                    'bsn' => $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.Body.SIMXML.ELEMENTEN.BSN_HOOFDBEWONER"),
                ]));
            } else {
                $data->set('liveIn', json_encode([
                    'liveInApplicable'  => false,
                    'consent'           => "NOT_APPLICABLE",
                ]));
            }

            $relocators[] = ['bsn' => $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.Body.SIMXML.ELEMENTEN.BSN"), 'declarationType' => 'REGISTERED'];

            if(
                isset($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"]) &&
                !$this->isAssoc($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"])
            ) {
                foreach ($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"] as $coMover) {
                    $relocators[] = ['bsn' => $coMover['BSN'], 'declarationType' => 'REGISTERED'];
                }
            } elseif (isset($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"])) {
                $coMover = $data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"];
                $relocators[] = ['bsn' => $coMover['BSN'], 'declarationType' => 'REGISTERED'];
            }
            $data->set('relocators', json_encode($relocators));
        }

        if($messageType == 'OntvangenIntakeNotificatie' && $zaaktype == 'B1425') {

            $relocators = '<ns10:MeeEmigranten xmlns:ns10="urn:nl/procura/gba/v1.5/diensten/emigratie">';
            $relocators .= "<ns10:MeeEmigrant>
                                        <ns10:Burgerservicenummer>{$data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.Body.SIMXML.ELEMENTEN.BSN")}</ns10:Burgerservicenummer>
                                        <ns10:OmschrijvingAangifte>G</ns10:OmschrijvingAangifte>
                                            <ns10:Duur>k</ns10:Duur>
                                    </ns10:MeeEmigrant>";

            if (
                isset($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"]) &&
                !$this->isAssoc($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"]))
            {
                foreach ($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"] as $coMover) {
                    $relocators .= "<ns10:MeeEmigrant>
                                        <ns10:Burgerservicenummer>{$coMover['BSN']}</ns10:Burgerservicenummer>
                                        <ns10:OmschrijvingAangifte>G</ns10:OmschrijvingAangifte>
                                            <ns10:Duur>k</ns10:Duur>
                                    </ns10:MeeEmigrant>";
                }
            } elseif (isset($data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"])){
                $coMover = $data->all()["SOAP-ENV:Body"]["ns2:OntvangenIntakeNotificatie"]["Body"]["SIMXML"]["ELEMENTEN"]["MEEVERHUIZENDE_GEZINSLEDEN"]["MEEVERHUIZEND_GEZINSLID"];
                $relocators .= "<ns10:MeeEmigrant>
                                        <ns10:Burgerservicenummer>{$coMover['BSN']}</ns10:Burgerservicenummer>
                                        <ns10:OmschrijvingAangifte>G</ns10:OmschrijvingAangifte>
                                            <ns10:Duur>k</ns10:Duur>
                                    </ns10:MeeEmigrant>";
            }

            $relocators .= '</ns10:MeeEmigranten>';
            $data->set('relocators', $relocators);
        }
        return $data->all();
    }

    public function postRunSpecificCode(array $data, array $namespaces, string $messageType, ?string $zaaktype = null, ?Gateway $gateway)
    {
        $data = new \Adbar\Dot($data);
        if($messageType == 'OntvangenIntakeNotificatie'){
            $resource = [
                'title'     => $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.ns2:Bijlagen.ns2:Bijlage.ns2:Omschrijving"),
                'filename'  => $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.ns2:Bijlagen.ns2:Bijlage.ns2:Naam"),
                'content'   => $data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.ns2:Bijlagen.ns2:Bijlage.ns2:Inhoud.#"),
            ];

            $post = json_encode($resource);
            $component = $this->gatewayService->gatewayToArray($gateway);
            $url = "{$gateway->getLocation()}/api/v1/dossiers/{$data->get("SOAP-ENV:Body.ns2:OntvangenIntakeNotificatie.Body.SIMXML.FORMULIERID")}/documents";

            $result = $this->commonGroundService->callService($component, $url, $post, [], [], false, 'POST');
        }

    }

    private function isAssoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function translateName(string $haalcentraal): string
    {
        $translateTable = [
            'eigen'  => 'E',
            'partner'  => 'P',
            'partner_eigen'  => 'V',
            'eigen_partner'  => 'N',
        ];
        return isset($translateTable[$haalcentraal]) ? $translateTable[$haalcentraal] : $haalcentraal;
    }

    private function translateGender(string $haalcentraal): string
    {
        $translateTable = [
            'vrouw' => 'V',
            'man'   => 'M'
        ];
        return $translateTable[$haalcentraal] ?? 'O';
    }

    public function getKinderen(array $person, array $result, array $component): array
    {
        if(isset($person['_embedded']['kinderen'])){
            foreach($person['_embedded']['kinderen'] as $kind){
                isset($kind['burgerservicenummer']) ? $result[] = $kind['burgerservicenummer'] : null;
            }
        } elseif(isset($person['kinderen'])) {
            foreach($person['kinderen'] as $kind){
                isset($kind['bsn']) ? $result[] = $kind['bsn'] : null;
            }
        } elseif(isset($person['_links']['kinderen'])) {
            foreach($person['_links']['kinderen'] as $kind) {
                $kindData = $this->commonGroundService->callService($component, $kind['href'], '');
                if(!is_array($kindData)){
                    $result[] = json_decode($kindData->getBody()->getContents(), true)['burgerservicenummer'];
                }
            }
        }
        return $result;
    }

    public function getOuders(array $person, array $result, array $component): array
    {
        if(isset($person['_embedded']['ouders'])) {
            foreach ($person['_embedded']['ouders'] as $ouder) {
                isset($ouder['burgerservicenummer']) ? $result[] = $ouder['burgerservicenummer'] : null;
            }
        } elseif(isset($person['ouders'])) {
            foreach($person['ouders'] as $ouder){
                isset($ouder['bsn']) ? $result[] = $ouder['bsn'] : null;
            }
        } elseif(isset($person['_links']['ouders'])) {
            foreach($person['_links']['ouders'] as $kind) {
                $ouderData = $this->commonGroundService->callService($component, $kind['href'], '');
                if(!is_array($ouderData)){
                    $result[] = json_decode($ouderData->getBody()->getContents(), true)['burgerservicenummer'];
                }
            }
        }
        return $result;
    }

    public function getPartners(array $person, array $result, array $component): array
    {
        if(isset($person['_embedded']['partners'])){
            foreach($person['_embedded']['partners'] as $partner){
                isset($partner['burgerservicenummer']) ? $result[] = $partner['burgerservicenummer'] : null;
            }
        } elseif(isset($person['partners'])) {
            foreach($person['partners'] as $partner){
                isset($partner['bsn']) ? $result[] = $partner['bsn'] : null;
            }
        } elseif(isset($person['_links']['partners'])) {
            foreach($person['_links']['partners'] as $kind) {
                $partnerData = $this->commonGroundService->callService($component, $kind['href'], '');
                if(!is_array($partnerData)){
                    $result[] = json_decode($partnerData->getBody()->getContents(), true)['burgerservicenummer'];
                }
            }
        }
        return $result;
    }

    private function getRelativesQuery(array $person, array $relatives): array
    {
        $query = ['burgerservicenummer' => implode(',', $relatives), ];
//        if(isset($person['verblijfplaats']['nummeraanduidingIdentificatie'])){
//            $query['verblijfplaats__nummeraanduidingIdentificatie'] = $person['verblijfplaats']['nummeraanduidingIdentificatie'];
//        } else {
        isset($person['verblijfplaats']['postcode']) ? $query['verblijfplaats__postcode'] = $person['verblijfplaats']['postcode'] : null;
        isset($person['verblijfplaats']['huisnummer']) ? $query['verblijfplaats__huisnummer'] = $person['verblijfplaats']['huisnummer'] : null;
        isset($person['verblijfplaats']['huisnummertoevoeging']) ? $query['verblijfplaats__huisnummertoevoeging'] = $person['verblijfplaats']['huisnummertoevoeging'] : null;
        isset($person['verblijfplaats']['huisletter']) ? $query['verblijfplaats__huisletter'] = $person['verblijfplaats']['huisletter'] : null;
//        }


        return $query;
    }

    private function getLa01Hydration(array $entity, Soap $soap): array
    {
        $url = "{$soap->getToEntity()->getGateway()->getLocation()}/{$soap->getToEntity()->getEndpoint()}/{$entity['burgerservicenummer']}";
        $component = $this->gatewayService->gatewayToArray($soap->getToEntity()->getGateway());

        $response = $this->commonGroundService->callService($component, $url, '', ['expand' => 'partners,ouders,kinderen']);
        if(is_array($response)){
            $response = $this->commonGroundService->callService($component, $url, '');
        }
        $data = json_decode($response->getBody()->getContents(), true);

        $entity['geslachtsnaam'] = isset($data['naam']['geslachtsnaam']) ? $data['naam']['geslachtsnaam'] : null;
        $entity['voorvoegselGeslachtsnaam'] = isset($data['naam']['voorvoegsel']) ? $data['naam']['voorvoegsel'] : null;
        $entity['voorletters'] = isset($data['naam']['voorletters']) ? $data['naam']['voorletters'] : null;
        $entity['voornamen'] = isset($data['naam']['voornamen']) ? $data['naam']['voornamen'] : null;
        $entity['aanduidingNaamgebruik'] = isset($data['naam']['aanduidingNaamgebruik']) ? $this->translateName($data['naam']['aanduidingNaamgebruik']) : null;



        //@TODO: Geslachtsnaam partner -> bij verwerken partner

        $entity['geslachtsaanduiding'] = isset($data['geslachtsaanduiding']) ? $this->translateGender( $data['geslachtsaanduiding']) : null;
        if(isset($data['overlijden']['datum']['datum']))
            $overlijdensdatum = new \DateTime($data['geboorte']['datum']['datum']);
        $entity['overlijdensdatum'] = isset($overlijdensdatum) ? $overlijdensdatum->format('Ymd') : null;

        if(isset($data['geboorte']['datum']['datum'])){
            $geboortedatum = new \DateTime($data['geboorte']['datum']['datum']);
        }
        $entity['geboortedatum'] = isset($geboortedatum) ? $geboortedatum->format('Ymd') : null;
        $entity['geboorteplaats'] = isset($data['geboorte']['plaats']['code']) ? $data['geboorte']['plaats']['code'] : (isset($data['geboorte']['plaats']['omschrijving']) ? $data['geboorte']['plaats']['omschrijving'] : null);
        $entity['geboorteland'] = isset($data['geboorte']['land']['code']) ? $data['geboorte']['land']['code'] : (isset($data['geboorte']['land']['omschrijving']) ? $data['geboorte']['land']['omschrijving'] : null);

        $entity['verblijfsadres'] =
            "<verblijfsadres xmlns:StUF=\"http://www.egem.nl/StUF/StUF0301\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
                <aoa.identificatie>".(isset($data['verblijfplaats']['adresseerbaarObjectIdentificatie']) ? $data['verblijfplaats']['adresseerbaarObjectIdentificatie'] : null)."</aoa.identificatie>
                <wpl.identificatie  xsi:nil=\"true\"
                                StUF:noValue=\"geenWaarde\"></wpl.identificatie>
                <wpl.woonplaatsNaam>". (isset($data['verblijfplaats']['woonplaats']) ? $data['verblijfplaats']['woonplaats'] : null) ."</wpl.woonplaatsNaam>
                <gor.openbareRuimteNaam>".(isset($data['verblijfplaats']['straat']) ? $data['verblijfplaats']['straat'] : null) ."</gor.openbareRuimteNaam>
                <gor.straatnaam>".(isset($data['verblijfplaats']['straat']) ? $data['verblijfplaats']['straat'] : null) ."</gor.straatnaam>
                <aoa.postcode>".(isset($data['verblijfplaats']['postcode']) ? $data['verblijfplaats']['postcode'] : null) ."</aoa.postcode>
                <aoa.huisnummer>".(isset($data['verblijfplaats']['huisnummer']) ? $data['verblijfplaats']['huisnummer'] : null)."</aoa.huisnummer>
                <aoa.huisletter>".(isset($data['verblijfplaats']['huisletter']) ? $data['verblijfplaats']['huisletter'] : null)."</aoa.huisletter>
                <aoa.huisnummertoevoeging>".(isset($data['verblijfplaats']['huisnummertoevoeging']) ? $data['verblijfplaats']['huisnummertoevoeging'] : null)."</aoa.huisnummertoevoeging>
                <inp.locatiebeschrijving xsi:nil=\"true\"
                                         StUF:noValue=\"nietOndersteund\"></inp.locatiebeschrijving>
          </verblijfsadres>";

        $entity['correspondentiesadres'] =
            "<sub.correspondentieAdres xmlns:StUF=\"http://www.egem.nl/StUF/StUF0301\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
                <aoa.identificatie>".(isset($data['verblijfplaats']['adresseerbaarObjectIdentificatie']) ? $data['verblijfplaats']['adresseerbaarObjectIdentificatie'] : null)."</aoa.identificatie>
                <wpl.identificatie  xsi:nil=\"true\"
                                StUF:noValue=\"geenWaarde\"></wpl.identificatie>
                <wpl.woonplaatsNaam>". (isset($data['verblijfplaats']['woonplaats']) ? $data['verblijfplaats']['woonplaats'] : null) ."</wpl.woonplaatsNaam>
                <gor.openbareRuimteNaam>".(isset($data['verblijfplaats']['straat']) ? $data['verblijfplaats']['straat'] : null) ."</gor.openbareRuimteNaam>
                <gor.straatnaam>".(isset($data['verblijfplaats']['straat']) ? $data['verblijfplaats']['straat'] : null) ."</gor.straatnaam>
                <aoa.postcode>".(isset($data['verblijfplaats']['postcode']) ? $data['verblijfplaats']['postcode'] : null) ."</aoa.postcode>
                <aoa.huisnummer>".(isset($data['verblijfplaats']['huisnummer']) ? $data['verblijfplaats']['huisnummer'] : null)."</aoa.huisnummer>
                <aoa.huisletter>".(isset($data['verblijfplaats']['huisletter']) ? $data['verblijfplaats']['huisletter'] : null)."</aoa.huisletter>
                <aoa.huisnummertoevoeging>".(isset($data['verblijfplaats']['huisnummertoevoeging']) ? $data['verblijfplaats']['huisnummertoevoeging'] : null)."</aoa.huisnummertoevoeging>
          </sub.correspondentieAdres>";

        $entity['heeftAlsEchtgenootPartner'] = $this->getRelativesOnAddress($component, $soap, $data, $this->getPartners($data, [], $component), 'partners', $entity['geslachtsnaamPartner'], $entity['voorvoegselGeslachtsnaamPartner']);
        $entity['heeftAlsKinderen'] = $this->getRelativesOnAddress($component, $soap, $data, $this->getKinderen($data, [], $component), 'children');
        $entity['heeftAlsOuders'] = $this->getRelativesOnAddress($component, $soap, $data, $this->getOuders($data, [], $component), 'parents');

        return $entity;
    }

    private function getRelativesOnAddress(array $component, Soap $soap, array $person, array $relativeBsns, string $type, ?string &$lastname = null, ?string &$lastnamePrefix = null): string
    {

        $return = '';
        if(!$relativeBsns){
            return $return;
        }

        try{
            $relatives = $this->commonGroundService->callService(
                $component,
                "{$soap->getToEntity()->getGateway()->getLocation()}/{$soap->getToEntity()->getEndpoint()}",
                '',
                $this->getRelativesQuery($person, $relativeBsns)
            );
        } catch (ServerException $exception) {
            $relatives = $this->commonGroundService->callService(
                $component,
                "{$soap->getToEntity()->getGateway()->getLocation()}/{$soap->getToEntity()->getEndpoint()}",
                '',
                ['burgerservicenummer' => implode(',', $relativeBsns), ]
            );
        }
        $relatives = json_decode($relatives->getBody()->getContents(), true);
        if(isset($relatives['_embedded']['ingeschrevenpersonen'])){
            $relatives = $relatives['_embedded']['ingeschrevenpersonen'];
        }
        foreach($relatives as $relative){
            if(
                (
                    isset($relative['geheimhoudingPersoonsgegevens']) &&
                    $relative['geheimhoudingPersoonsgegevens']
                ) ||
                !in_array($relative['burgerservicenummer'], $relativeBsns) ||
                !(
                    isset($relative['verblijfplaats']['postcode']) &&
                    isset($relative['verblijfplaats']['huisnummer']) &&
                    isset($person['verblijfplaats']['postcode']) &&
                    isset($person['verblijfplaats']['huisnummer']) &&
                    $relative['verblijfplaats']['postcode'] == $person['verblijfplaats']['postcode'] &&
                    $relative['verblijfplaats']['huisnummer'] == $person['verblijfplaats']['huisnummer']
                )
            ){
                continue;
            }
            $lastname = isset($relative['naam']['geslachtsnaam']) ? $relative['naam']['geslachtsnaam'] : null;
            $lastnamePrefix = isset($relative['naam']['voorvoegsel']) ? $relative['naam']['voorvoegsel'] : null;
            if(isset($relative['geboorte']['datum']['datum'])){
                $geboortedatum = new \DateTime($relative['geboorte']['datum']['datum']);
            }
            $relativeString = "<gerelateerde StUF:entiteittype=\"NPS\">
                      <inp.bsn>".(isset($relative['burgerservicenummer']) ? $relative['burgerservicenummer'] : null)."</inp.bsn>
                      <geslachtsnaam>".(isset($relative['naam']['geslachtsnaam']) ? $relative['naam']['geslachtsnaam'] : null)."</geslachtsnaam>
                      <voorvoegselGeslachtsnaam>".(isset($relative['naam']['voorvoegsel']) ? $relative['naam']['voorvoegsel'] : null)."</voorvoegselGeslachtsnaam>
                      <voorletters>".(isset($relative['naam']['voorletters']) ? $relative['naam']['voorletters'] : null)."</voorletters>
                      <voornamen>".(isset($relative['naam']['voornamen']) ? $relative['naam']['voornamen'] : null)."</voornamen>
                      <geslachtsaanduiding>". (isset($relative['geslachtsaanduiding']) ? $this->translateGender($relative['geslachtsaanduiding']) : null) ."</geslachtsaanduiding>
                      <geboortedatum>".(isset($geboortedatum) ? $geboortedatum->format('Ymd') : null)."</geboortedatum>
                  </gerelateerde>";
            if($type == 'parents'){
                $return .= "<inp.heeftAlsOuders StUF:entiteittype=\"NPSNPSOUD\">$relativeString</inp.heeftAlsOuders>";
            } elseif($type == 'children') {
                $return .= "<inp.heeftAlsKinderen StUF:entiteittype=\"NPSNPSKND\">$relativeString</inp.heeftAlsKinderen>";
            } elseif($type == 'partners'){
                $return .= "<inp.heeftAlsEchtgenootPartner StUF:entiteittype=\"NPSNPSHUW\">$relativeString</inp.heeftAlsEchtgenootPartner>";
            }
            unset($geboortedatum);
        }



        return $return;
    }
}
