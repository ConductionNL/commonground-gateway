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

class MapZaakTypeService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        TranslationService $translationService
    ) {
        $this->entityManager = $entityManager;
        $this->translationService = $translationService;
    }

    public function mapZaakTypeHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        var_dump('MapZaakType plugin works');

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
            'verlengingMogelijk' => 'embedded.instance.embedded.properties.extension\'',
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

        // Map and set default values from xxllnc casetype to zgw zaaktype
        $zgwZaakType = $this->translationService->dotHydrator(isset($skeletonIn) ? array_merge($xxllncCaseType, $skeletonIn) : $xxllncCaseType, $xxllncCaseType, $mappingIn);

        // Manually map results to resultaattypen
        if (isset($xxllncCaseType['embedded']['instance']['embedded']['results'])) {
            $zgwZaakType['resultaattypen'] = [];
            foreach ($xxllncCaseType['embedded']['instance']['embedded']['results'] as $result) {
                $resultaatType = [];

                $zgwZaakType['resultaattypen'][] = $resultaatType;
            }
        }

        // Manually map phases to statustypen
        if (isset($xxllncCaseType['embedded']['instance']['embedded']['phases'])) {
            $zgwZaakType['statustypen'] = [];
            foreach ($xxllncCaseType['embedded']['instance']['embedded']['phases'] as $phase) {
                $statusType = [];

                $zgwZaakType['statustypen'][] = $statusType;
            }
        }

        $zgwZaakType['embedded'] = null;
        var_dump($zgwZaakType);
        die;

        return $this->data;
    }
}
