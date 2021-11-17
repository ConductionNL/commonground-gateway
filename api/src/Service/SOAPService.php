<?php

namespace App\Service;

use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class SOAPService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;

    /**
     * Translation table for case type descriptions
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
     * Translation table for case statuses
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

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
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
        if($result->getStatusCode() != 200 || !isset(json_decode($body, true)['result']['content'])){
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
        if($result->getStatusCode() != 200 || !isset(json_decode($body, true)['result']['content'])){
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
        if($result->getStatusCode() != 200){
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
            'StUF:gebruiker'        => 'Systeem'
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
            'StUF:entiteittype'     => isset($data["$env:Body"]["$caseNamespace:zakLv01"]["$caseNamespace:stuurgegevens"]["$stufNamespace:entiteittype"]) ? $data["$env:Body"]["$caseNamespace:zakLv01"]["$caseNamespace:stuurgegevens"]["$stufNamespace:entiteittype"] : "ZAK",];
    }

    public function getLa01Skeleton(array $data): array
    {
        $namespaces = $this->getNamespaces($data);
        return [
            '@xmlns:s' => "http://schemas.xmlsoap.org/soap/envelope/",
            's:Body' => [
                'zakLa01' => [
                    '@xmlns'        => "http://www.egem.nl/StUF/sector/zkn/0310",
                    '@xmlns:StUF'   => "http://www.egem.nl/StUF/StUF0301",
                    '@xmlns:xsi'    => "http://www.w3.org/2001/XMLSchema-instance",
                    '@xmlns:BG'     => "http://www.egem.nl/StUF/sector/bg/0310",
                    'stuurgegevens' => $this->getSendData($data, $namespaces),
                    'parameters'    => ['StUF:indicatorVervolgvraag' => 'false',],
                ]
            ]
        ];
    }

    public function translateType(array $case): ?string
    {
        if(!isset($case['type']['code'])){
            return null;
        } else {
            return self::TRANSLATE_TYPE_TABLE[$case['type']['code']] ?? null;
        }
    }

    public function translateStatus(array $case): array
    {
        if(!isset($case['status']['code'])){
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
                'code'                  => $case['type']['code'] ?? ''
            ]
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
            'datumStatusGezet'          =>  $endDate->format('YmdHisv'),
            'indicatieLaatsteStatus'    => isset($status['endStatus']) ? ($status['endStatus'] ? 'J': 'N') : 'N',
        ];
    }

    public function getResult(array $case): array{
        return [
            'omschrijving'  => '',
            'toelichting'   => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde'],
        ];
    }

    public function addCaseTypeDetails(array $case, array &$result): void
    {
        $details = [
            'omschrijvingGeneriek'      => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'zaakcategorie'             => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'trefwoord'                 => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'doorlooptijd'              => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'servicenorm'               => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'archiefcode'               => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'vertrouwelijkAanduiding'   => 'ZAAKVERTROUWELIJK',
            'publicatieIndicatie'       => 'N',
            'publicatieTekst'           => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'ingangsdatumObject'        => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'einddatumObject'           => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
        ];

        $result['isVan']['gerelateerde'] = array_merge($result['isVan']['gerelateerde'], $details);
    }

    public function addStatusDetails(array $case, array &$result): void
    {
        $details = [
            'gerelateerde'  => [
                'ingangsdaumObject' => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde']
            ],
            'isGezetDoor'   => [
                '@StUF:entiteittype'    => 'ZAKSTTBTR',
                'gerelateerde'  => [
                    'organisatorischeEenheid'   => [
                        '@StUF:entiteittype'    => 'OEH',
                        'identificatie' => '1910',
                        'naam'          => 'CZSDemo',
                    ]
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
            'opschorting'               => ['indicatie' => 'N', 'reden' => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde']],
            'verlenging'                => ['duur' => '0',      'reden' => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde']],
            'betalingsIndicatie'        => 'N.v.t.',
            'laatsteBetaalDatum'        => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'archiefnominatie'          => 'N',
            'datumVernietigingDossier'  => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
            'zaakniveau'                => '1',
            'deelzakenIndicatie'        => 'N',
            'StUF:extraElementen'       => [
                'StUF:extraElement' => ['@naam' => 'kanaalcode', '#' => 'web'],
            ]
        ];
        if(!isset($result['toelichting']) || !$result['toelichting']){
            $result['toelichting'] = ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenwaarde'];
        }
        $result = array_slice($result, 0, array_search('isVan', array_keys($result)), true) + $details + array_slice($result, array_search('isVan', array_keys($result)), null, true);
        $this->addCaseTypeDetails($case, $result);

        $details = [
            'heeftBetrekkingOp'         => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKOBJ'],
            'heeftAlsBelanghebbende'    => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRBLH'],
            'heeftAlsGemachtigde'       => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRGMC'],
            'heeftAlsInitiator'         => [
                '@StUF:entiteittype'    => 'ZAKBTRINI',
                'gerelateerde'          => [
                    'natuurlijkPersoon' => [
                        '@StUF:entiteittype'            => 'NPS',
                        'BG:inp.bsn'                    => '144209007',
                        'BG:authentiek'                 => ['@StUF:metagegeven' => 'true', '#' => 'J'],
                        'BG:geslachtsnaam'              => 'Aardenburg',
                        'BG:voorvoegselGeslachtsnaam'   => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                        'BG:voorletters'                => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                        'BG:voornamen'                  => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                        'BG:geslachtsaanduiding'        => 'V',
                        'BG:geboortedatum'              => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                        'BG:verblijfsadres'             => [
                            'BG:aoa.identificatie'          => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                            'BG:wpl.woonplaatsNaam'         => 'Ons Dorp',
                            'BG:gor.openbareRuimteNaam'     => '',
                            'BG:gor.straatnaam'             => 'Beukenlaan',
                            'BG:aoa.postcode'               => '5665DV',
                            'BG:aoa.huisnummer'             => '14',
                            'BG:aoa.huisletter'             => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                            'BG:aoa.huisnummertoevoeging'   => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                            'BG:inp.locatiebeschrijving'    => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                        ],
                    ],
                ],
                'code'                  => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                'omschrijving'          => 'Initiator',
                'toelichting'           => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                'heeftAlsAanspreekpunt' => [
                    '@StUF:entiteittype' => 'ZAKBTRINICTP',
                    'gerelateerde' => [
                        '@StUF:entiteittype'    => 'CTP',
                        'telefoonnummer'        => '0624716603',
                        'emailadres'            => 'r.schram@simgroep.nl',
                    ]
                ]
            ],
            'heeftAlsUitvoerende'       => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRUTV'],
            'heeftAlsVerantwoordelijke' => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBTRVRA'],
            'heeftAlsOverigBetrokkene'  => [
                '@StUF:entiteittype'    => 'ZAKBTROVR',
                'gerelateerde'          => [
                    'organisatorischeEenheid'   => [
                        '@StUF:entiteittype'    => 'OEH',
                        'identificatie' => '1910',
                        'naam'          => 'CZSDemo',
                    ],
                ],
                'code'                  => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                'omschrijving'          => 'Overig',
                'toelichting'           => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde'],
                'heeftAlsAanspreekPunt' => [
                    '@StUF:entiteittype'    => 'ZAKBTROVRCTP',
                    'gerelateerde'          => ['@StUF:entiteittype' => 'CTP']
                ]
            ],
            'heeftAlsHoofdzaak' => ['@xsi:nil' => "true", '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKZAKHFD'],
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
            'leidtTot' => ['@xsi:nil' => 'true', '@StUF:noValue' => 'geenWaarde', '@StUF:entiteittype' => 'ZAKBSL'],
        ];
        if($details){
            $this->addCaseDetails($case, $result);
        }

        return $result;
    }

    public function casesToObjects(array $cases, bool $details = false): array
    {
        $result = ['object' => []];
        foreach($cases as $case){
            $result['object'][] = array_filter($this->caseToObject($case, $details), function($value){return $value !== null && $value !== false && $value !== [];});
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
        foreach($documents as $document){
            $results[] = $this->getDocumentObject($document, $identifier);
        }
        return $results;
    }

    public function getZakLa01MessageForIdentifier(string $identifier, array $data, bool $documents): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        $result = $this->getLa01Skeleton($data);
        if(!$documents) {
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
                ]
            ]
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
                    ]
                ]
            ]
        ];
    }

    public function getDocumentById(string $identifier): array
    {
        $identifierArray = explode('.', $identifier);
        $documents = $this->getDocumentsByCaseId($identifierArray[0]);
        foreach($documents as $document){
            if($document['id'] == end($identifierArray)){
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
        if($result->getStatusCode() != 200){
            return [];
        } else {
            return [
                'location' => $this->commonGroundService->getComponent('vrijbrp-dossier')['location']."/api/v1/dossiers/{$identifierArray[0]}/documents/{$identifierArray[1]}",
                'data' => base64_encode($body)
            ];
        }
    }

    public function getFo02Message(): array
    {
        return [
            '@xmlns:s'  => 'http://schemas.xmlsoap.org/soap/envelope/',
            's:Body'    => [
                's:Fault'   => [
                    '@xmlns:s'   => 'http://schemas.xmlsoap.org/soap/envelope/',
                    'faultcode'     => 'soap:Server',
                    'faultstring'   => 'Proces voor afhandelen bericht geeft fout',
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
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getEdcLa01MessageForIdentifier(string $identifier): string
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 's:Envelope']);
        $document = $this->getDocumentById($identifier);
        $data = $this->getDocumentData($identifier);
        if($document){
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
                        'StUF:crossRefnummer'   => '1572191056'
                    ]
                ]
            ]
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

    public function processEdcLk01(array $data, array $namespaces): string
    {
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
        if($result->getStatusCode() != 201){
            return $xmlEncoder->encode($this->getFo02Message(), 'xml');
        } else {
            return $xmlEncoder->encode($this->getBv03Message(), 'xml');
        }
    }
}
