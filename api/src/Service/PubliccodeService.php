<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml;

class PubliccodeService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private ObjectEntityService $objectEntityService;
    private GithubApiService $githubService;
    private GitlabApiService $gitlabService;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService,
        ObjectEntityService $objectEntityService,
        GithubApiService $githubService,
        GitlabApiService $gitlabService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
        $this->objectEntityService = $objectEntityService;
        $this->githubService = $githubService;
        $this->gitlabService = $gitlabService;
        $this->configuration = [];
    }

    /**
     * @param ObjectEntity $repository the repository where we want to find an organisation for
     *
     * @throws \Exception
     */
    public function getOrganisationFromRepository(ObjectEntity $repository): ?ObjectEntity
    {
        $source = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('source'))->getStringValue();
        $url = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('url'))->getStringValue();
        $organisationEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['organisationEntityId']);

        switch ($source) {
            case 'github':
                // lets get the repository data
                $github = $this->githubService->getRepositoryFromUrl(trim(parse_url($url, PHP_URL_PATH), '/'));

                $existingOrganisations = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($organisationEntity, ['github' => $github['organisation']['github']]);
                // lets see if we have an organisations // even uitzoeken
                if (count($existingOrganisations) > 0 && $existingOrganisations[0] instanceof ObjectEntity) {
                    return $existingOrganisations[0];
                }

                $organisation = new ObjectEntity();
                $organisation->setEntity($organisationEntity);
                $organisation = $this->synchronizationService->setApplicationAndOrganization($organisation);

                return $this->synchronizationService->populateObject($github['organisation'], $organisation, 'POST');
            case 'gitlab':
                // hetelfde maar dan voor gitlab
            default:
                // error voor onbeknd type
        }

        return null;
    }

    /**
     * @param ObjectEntity $repository
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *
     * @return void dataset at the end of the handler
     */
    public function saveOrganisationToRepository(ObjectEntity $repository): void
    {
        $organisation = $this->getOrganisationFromRepository($repository);
        $repo['organisation'] = $organisation ? $organisation->getId()->toString() : null;
        $this->objectEntityService->saveObject($repository, $repo);
    }

    /**
     * @param ObjectEntity $repository
     *
     * @return bool dataset at the end of the handler
     */
    public function checkRepositoryOrganisation(ObjectEntity $repository): bool
    {
        // Set see if we have an org // CHECK DIT! Returnd dit false als niks is gevonden
        $existingOrganisationId = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('organisation'))->getStringValue();
        if ($existingOrganisationId && $this->entityManager->getRepository('App:ObjectEntity')->find($existingOrganisationId)) {
            // There is alread an orangisation so we dont need to do anything
            return true;
        }

        return false;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeFindOrganisationsTroughRepositoriesHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);

        // Let if we have a single repository
        if (!empty($data)) { // it is one organisation

            try {
                $repository = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
            } catch (Exception $exception) {
                return $data;
            }

            if ($this->checkRepositoryOrganisation($repository)) {
                return $data;
            }
            $this->saveOrganisationToRepository($repository);

            return $data;
        }

        // If we want to do it for al repositories
        foreach ($entity->getObjectEntities() as $repository) {
            if ($this->checkRepositoryOrganisation($repository)) {
                continue;
            }
            $this->saveOrganisationToRepository($repository);
        }

        return $data;
    }

    /**
     * @param Gateway      $source
     * @param Entity       $entity
     * @param ObjectEntity $githubOrganisations
     *
     * @throws GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return void dataset at the end of the handler
     */
    public function syncRepositoriesFromOrganisation(Gateway $source, Entity $entity, ObjectEntity $githubOrganisations): void
    {
        // Even kijken of dit klopt met github object
        foreach ($githubOrganisations as $repository) {
            // Creat a sync trough not finding it
            $sync = $this->synchronizationService->findSyncBySource($source, $entity, $repository['id']);

            // activate sync to pull in data
            $sync = $this->synchronizationService->handleSync($sync, $repository);

            $this->entityManager->persist($sync);
            $this->entityManager->flush();
        }
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeFindRepositoriesThroughOrganisationsHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        // Load from config
        $source = $this->entityManager->getRepository('App:Entity')->find($this->configuration['sourceId']);
        $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['organisationEntityId']);

        $githubRepositoryActionId = $this->entityManager->getRepository('App:Entity')->get($this->configuration['githubRepositoryActionId']);
        $gitlabRepositoryActionId = $this->entityManager->getRepository('App:Entity')->get($this->configuration['gitlabRepositoryActionId']);

        // Let see if it is one or alle organisations
        if (!empty($data)) { // it is one organisation
            // Heb ik een id?

            // trycatch
            $organisation = $this->objectEntityService->getObject($data['id']);
            // Get organisation from github
            $githubOrganisation = $this->githubService->getOrganisationOnUrl($organisation['github']);
            $this->syncRepositoriesFromOrganisation($source, $entity, $githubOrganisation['owns']);

            return $data;
        }

        // If we want to do it for al repositiries
        foreach ($entity->getObjectEntities() as $organisation) {
            // Get organisation from github
            $githubOrganisation = $this->githubService->getOrganisationOnUrl($organisation['github']);
            $this->syncRepositoriesFromOrganisation($source, $entity, $githubOrganisation['owns']);
        }

        return $data;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeCheckRepositoriesForPublicCodeHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $componentEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['componentEntityId']);
        $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);

        if (!empty($data)) { // it is one organisation
            // Heb ik een id?

            // trycatch
            $repository = $this->objectEntityService->getObject($data['id']);

            if (!$component = $repository->GetValueOnAtribute('component')) {
                $component = new ObjectEntity();
                $component->setEntity($componentEntity);
                $component = $this->synchronizationService->populateObject(null, $component, 'POST');
            }

            switch ($repository['source']) {
                case 'github':
                    //@todo zouden we via een sync moeten willen. Dus na hier negeren
                    if ($file = $this->githubService->getRepositoryFileFromUrl($repository['url'], 'publiccode.yaml'));

                        $yamlPubliccode = decode($file['content']);
                        $component = $this->parsePubliccodeToComponent(Yaml::parse($yamlPubliccode), $repository->GetValueOnAtribute('component'));
                        // @todosave component

                case 'gitlab':

                default:
                    //@todo gooi error
            }
        }

        // If we want to do it for al repositories
        foreach ($entity->getObjectEntities() as $repository) {
            $existingComponentId = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('component'))->getStringValue();
            if ($existingComponentId && $existingComponent = $this->entityManager->getRepository('App:ObjectEntity')->find($existingComponentId)) {
                $component = new ObjectEntity();
                $component->setEntity($componentEntity);
                $component = $this->synchronizationService->populateObject($existingComponent, $component, 'POST');
            }
        }
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeRatingHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        if (!empty($data)) { // it is one organisation
            // Heb ik een id?

            // trycatch
            $component = $this->objectEntityService->getObject($data['id']);

            $component = $this->rateComponent($component);

            // @todosave component
        }

        // If we want to do it for al repositiries
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Component']);
        foreach ($entity->getObjectEntities() as $component) {
            $component = $this->rateComponent($component);

            // @todosave component
        }
    }

    /*
     * Concerts publiccodefiles to components
     */
    public function parsePubliccodeToComponent(array $publicode, ObjectEntity $component): ObjectEntity
    {
    }

    /*
     * Rates a component
     */
    public function rateComponent(ObjectEntity $component): ObjectEntity
    {
        $component = $component->toArray();
        $rating = 1;

//        if(in_array['name'],$component)){
//            $rating ++;
//        }

        //@todo checks doornemen

        return $component;
    }
}
