<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use mysql_xdevapi\Exception;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;
use App\Service\TranslationService;
use App\Service\ObjectEntityService;
use App\Service\EavService;
use App\Service\SynchronizationService;

class MapZaakTypeService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private EavService $eavService;
    private SynchronizationService $synchronizationService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        TranslationService $translationService,
        ObjectEntityService $objectEntityService,
        EavService $eavService,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->translationService = $translationService;
        $this->objectEntityService = $objectEntityService;
        $this->eavService = $eavService;
        $this->synchronizationService = $synchronizationService;
    }

    public function mapZaakTypeHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Find ZGW ZaakType entity by name
        $zaakTypeEntity = $this->entityManager->getRepository(Entity::class)->findOneBy(['name' => 'ZaakType']);

        if (!isset($zaakTypeEntity)) {
            throw new \Exception('ZaakType entity could not be found');
        }
        $mappingIn = [
            'identificatie' => 'embedded.instance.embedded.legacy.zaaktype_id|string',
            'onderwerp' => 'embedded.instance.title',
            'indicatieInternOfExtern' => 'embedded.instance.trigger',
            'doorlooptijd' => 'embedded.instance.embedded.properties.lead_time_legal.weken',
            'servicenorm' => 'embedded.instance.embedded.properties.lead_time_service.weken',
            'vertrouwelijkheidaanduiding' => 'embedded.instance.embedded.properties.designation_of_confidnetiality',
            'verlengingMogelijk' => 'embedded.instance.embedded.properties.extension',
            'trefwoorden' => 'embedded.instance.subject_types',
            'publicatieIndicatie' => 'embedded.instance.embedded.properties.publication|bool',
            'verantwoordingsrelatie' => 'embedded.instance.embedded.properties.supervisor_relation|array',
            'omschrijving' => 'embedded.instance.title',
            'opschortingEnAanhoudingMogelijk' => 'embedded.instance.embedded.properties.suspension|bool',
            // 'resultaattypen.omschrijving' => 'instance.results.comments',
            // 'resultaattypen.omschrijvingGeneriek' => 'instance.results.label',
            // 'resultaattypen.resultaattypeomschrijving' => 'instance.results.type',
            // 'resultaattypen.selectielijstklasse' => 'instance.results.selection_list',
            // 'resultaattypen.archiefactietermijn' => 'instance.results.period_of_preservation',
        ];

        $skeletonIn = [
            'handelingInitiator' => 'indienen',
            'beginGeldigheid' => '1970-01-01',
            'versieDatum' => '1970-01-01',
            'doel' => 'Overzicht hebben van de bezoekers die aanwezig zijn',
            'versiedatum' => '1970-01-01',
            'handelingBehandelaar' => 'Hoofd beveiliging',
            'aanleiding' => 'Er is een afspraak gemaakt met een (niet) natuurlijk persoon',
        ];

        $xxllncCaseType = $data['response'];

        // find already existing zgwZaakType by $xxllncCaseType['reference']
        // if (!$objectEntity = $this->objectEntityService->findObjectBy($zaakTypeEntity, ['reference' => $xxllncCaseType['reference']])) {
        // create new empty objectentity
        $objectEntity = $this->eavService->getObject(null, 'POST', $zaakTypeEntity);
        // }

        // Map and set default values from xxllnc casetype to zgw zaaktype
        $zgwZaakType = $this->translationService->dotHydrator(isset($skeletonIn) ? array_merge($xxllncCaseType, $skeletonIn) : $xxllncCaseType, $xxllncCaseType, $mappingIn);

        // Manually map results to resultaattypen
        if (isset($xxllncCaseType['embedded']['instance']['embedded']['results'])) {
            $zgwZaakType['resultaattypen'] = [];
            foreach ($xxllncCaseType['embedded']['instance']['embedded']['results'] as $result) {
                $resultaatType = [];
                $result['type'] && $resultaatType['omschrijving'] = $result['type'];
                $result['label'] && $resultaatType['toelichting'] = $result['label'];
                $resultaatType['selectielijstklasse'] = $result['selection_list'] ?? 'http://localhost';
                $result['type_of_archiving'] && $resultaatType['archiefnominatie'] = $result['type_of_archiving'];
                $result['period_of_preservation'] && $resultaatType['archiefactietermijn'] = $result['period_of_preservation'];

                $zgwZaakType['resultaattypen'][] = $resultaatType;
            }
        }

        // Manually map phases to statustypen
        if (isset($xxllncCaseType['embedded']['instance']['embedded']['phases'])) {
            $zgwZaakType['statustypen'] = [];
            foreach ($xxllncCaseType['embedded']['instance']['embedded']['phases'] as $phase) {
                // var_dump($phase);
                // die;
                $statusType = [];
                $phase['name'] && $statusType['omschrijving'] = $phase['name'];
                $phase['embedded']['fields'][0]['label'] && $statusType['omschrijvingGeneriek'] = $phase['embedded']['fields'][0]['label'];
                $phase['embedded']['fields'][0]['help'] && $statusType['statustekst'] = $phase['embedded']['fields'][0]['help'];
                $phase['seq'] && $statusType['volgnummer'] = $phase['seq'];

                $zgwZaakType['statustypen'][] = $statusType;
            }
        }

        // Manually map ? to eigenschappen
        if (isset($xxllncCaseType['embedded']['instance']['embedded']['eigenschappen'])) {
            // $zgwZaakType['statustypen'] = [];
            // foreach ($xxllncCaseType['embedded']['instance']['embedded']['phases'] as $phase) {
            //     var_dump($phase);
            //     // die;
            //     $statusType = [];
            //     $phase['name'] && $resultaatType['omschrijving'] = $phase['name'];
            //     $phase['fields'][0]['label'] && $resultaatType['omschrijvingGeneriek'] = $phase['fields'][0]['label'];
            //     $phase['fields'][0]['help'] && $resultaatType['statustekst'] = $phase['fields'][0]['help'];
            //     $phase['seq'] && $resultaatType['volgnummer'] = $phase['seq'];

            //     $zgwZaakType['statustypen'][] = $statusType;
            // }
        }

        // Manually map ? to roltypen
        if (isset($xxllncCaseType['embedded']['instance']['embedded']['roltypen'])) {
            // $zgwZaakType['statustypen'] = [];
            // foreach ($xxllncCaseType['embedded']['instance']['embedded']['phases'] as $phase) {
            //     var_dump($phase);
            //     // die;
            //     $statusType = [];
            //     $phase['name'] && $resultaatType['omschrijving'] = $phase['name'];
            //     $phase['fields'][0]['label'] && $resultaatType['omschrijvingGeneriek'] = $phase['fields'][0]['label'];
            //     $phase['fields'][0]['help'] && $resultaatType['statustekst'] = $phase['fields'][0]['help'];
            //     $phase['seq'] && $resultaatType['volgnummer'] = $phase['seq'];

            //     $zgwZaakType['statustypen'][] = $statusType;
            // }
        }

        // var_dump($zgwZaakType['statustypen']);
        $zgwZaakType['embedded'] = null;
        // die;

        $objectEntity = $this->synchronizationService->setApplicationAndOrganization($objectEntity);
        $objectEntity = $this->objectEntityService->saveObject($objectEntity, $zgwZaakType);
        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        return $this->data;
    }
}
