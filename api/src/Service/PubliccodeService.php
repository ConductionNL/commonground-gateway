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
        ObjectEntityService    $objectEntityService,
        GithubApiService       $githubService,
        GitlabApiService       $gitlabService
    )
    {
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOrganisationFromRepository(ObjectEntity $repository): ?ObjectEntity
    {
        $source = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('source'))->getStringValue();
        $url = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('url'))->getStringValue();
        $organisationEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['organisationEntityId']);

        if ($source == null) {
            $domain = parse_url($url, PHP_URL_HOST);
            $domain == 'github.com' && $source = 'github';
            $domain == 'gitlab.com' && $source = 'gitlab';
        }

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
     * @return void dataset at the end of the handler
     * @throws \Psr\Cache\InvalidArgumentException
     *
     */
    public function saveOrganisationToRepository(ObjectEntity $repository): void
    {
        $organisation = $this->getOrganisationFromRepository($repository);
        $repo['organisation'] = $organisation ? $organisation->getId()->toString() : null;
        $repo['organisation'] !== null && $this->synchronizationService->populateObject($repo, $repository, 'PUT');
    }

    /**
     * @param ObjectEntity $repository
     *
     * @return bool dataset at the end of the handler
     */
    public function checkRepositoryOrganisation(ObjectEntity $repository): bool
    {
        // Set see if we have an org
        try {
            $existingOrganisationId = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('organisation'))->getId();
            $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['id' => $existingOrganisationId]);
        } catch (Exception $exception) {
            return false;

        }
            // There is already an organisation, so we don't need to do anything
        return true;
    }

    /**
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     * @throws \Psr\Cache\InvalidArgumentException
     *
     */
    public function publiccodeFindOrganisationsTroughRepositoriesHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);

        // Let see if we have a single repository
        if (!empty($data)) {
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
     * @param Gateway $source
     * @param Entity $entity
     * @param ObjectEntity $githubOrganisations
     *
     * @return void dataset at the end of the handler
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @throws GatewayException
     */
    public function syncRepositoriesFromOrganisation(Gateway $source, Entity $entity, ObjectEntity $githubOrganisations): void
    {
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
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     */
    public function publiccodeFindRepositoriesThroughOrganisationsHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        // Load from config
        $sourceEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['sourceEntityId']);
        $organisationEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['organisationEntityId']);
        $repositoryEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);


//        $githubRepositoryActionId = $this->entityManager->getRepository('App:Entity')->get($this->configuration['githubRepositoryActionId']);
//        $gitlabRepositoryActionId = $this->entityManager->getRepository('App:Entity')->get($this->configuration['gitlabRepositoryActionId']);

        if (!empty($data)) { // it is one organisation

            var_dump('handler 2 org id '.$data['response']['id']);
            try {
                $organisation = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
            } catch (Exception $exception) {
                return $data;
            }

            // Get organisation from github
            $githubOrganisation = $this->githubService->getOrganisationOnUrl($organisation['github']);
            var_dump($githubOrganisation);
            $this->syncRepositoriesFromOrganisation($sourceEntity, $organisationEntity, $githubOrganisation['owns']);

            return $data;
        }

        foreach ($organisationEntity->getObjectEntities() as $organisation) {
            // Get organisation from github
            $githubOrganisation = $this->githubService->getOrganisationOnUrl($organisation['github']);
            $this->syncRepositoriesFromOrganisation($sourceEntity, $organisationEntity, $githubOrganisation['owns']);
        }

        return $data;
    }

    /**
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeCheckRepositoriesForPublicCodeHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $componentEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['componentEntityId']);
        $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);

        // get all repositories on github with a publiccode
        // get the publiccode with the custom endpoint
        // sync the publiccode to components with the repository in it

        if (!empty($data)) { // it is one repository
            // Heb ik een id?

            try {
                $repository = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
            } catch (Exception $exception) {
                return $data;
            }

            $existingComponentId = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('component'))->getStringValue();
            if (!$existingComponent = $this->entityManager->getRepository('App:ObjectEntity')->find($existingComponentId)) {
                $component = new ObjectEntity();
                $component->setEntity($componentEntity);
                $component = $this->synchronizationService->populateObject(null, $component, 'POST');

                return $data;
            }
        }

        // If we want to do it for al repositories
        foreach ($entity->getObjectEntities() as $repository) {

            $existingComponentId = $repository->getValueByAttribute($repository->getEntity()->getAttributeByName('component'))->getStringValue();
            if (!$existingComponent = $this->entityManager->getRepository('App:ObjectEntity')->find($existingComponentId)) {
                $component = new ObjectEntity();
                $component->setEntity($componentEntity);
                $component = $this->synchronizationService->populateObject($existingComponent, $component, 'POST');
            }
        }

        return $data;
    }

    /**
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeRatingHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['componentEntityId']);

        if (!empty($data)) { // it is one component
            try {
                $component = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
            } catch (Exception $exception) {
                return $data;
            }

            $ratingComponent['rating'] = $this->rateComponent($component);
            $component = new ObjectEntity();
            $component->setEntity($entity);
            $component = $this->synchronizationService->populateObject($ratingComponent, $component, 'PUT');
        }

        // If we want to do it for al components
        foreach ($entity->getObjectEntities() as $component) {
            $ratingComponent['rating'] = $this->rateComponent($component);
            $component = new ObjectEntity();
            $component->setEntity($entity);
            $component = $this->synchronizationService->populateObject($ratingComponent, $component, 'PUT');
        }

        return $data;
    }

//    /*
//     * Concerts publiccodefiles to components
//     */
//    public function parsePubliccodeToComponent(array $publicode, ObjectEntity $component): ObjectEntity
//    {
//    }

    /*
     * Rates a component
     */
    public function rateComponent(ObjectEntity $component): array
    {
        $component = $component->toArray();
        $rating = 1;
        $maxRating = 1;
        $description = [];

        if (in_array($component['name'], $component) && $component['name'] !== null) {
            var_dump($component['name'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the name because it is not set';
        }
        $maxRating++;

        if ($component['url'] && in_array($component['url']['url'], $component) && $component['url']['url'] !== null) {
            var_dump($component['url']['url'] . ' is aanwezig');
            $rating++;
        }
        else {
            $description[] = 'Cannot rate the url because it is not set';
        }
        $maxRating++;

        if (in_array($component['landingURL'], $component) && $component['landingURL'] !== null) {
            var_dump($component['landingURL'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the landingUrl because it is not set';
        }
        $maxRating++;

        if (in_array($component['softwareVersion'], $component) && $component['softwareVersion'] !== null ) {
            var_dump($component['softwareVersion'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the software version because it is not set';
        }
        $maxRating++;

        if (in_array($component['releaseDate'], $component) && $component['releaseDate'] !== null) {
            var_dump($component['releaseDate'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the release date because it is not set';
        }
        $maxRating++;

        if (in_array($component['logo'], $component) && $component['logo'] !== null) {
            var_dump($component['logo'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the logo because it is not set';
        }
        $maxRating++;

        if (in_array($component['platforms'], $component) && $component['platforms'] !== null) {
            var_dump($component['platforms'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the platforms because it is not set';
        }
        $maxRating++;

        if (in_array($component['categories'], $component) && $component['categories'] !== null) {

            var_dump($component['categories'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the categories because it is not set';
        }
        $maxRating++;

        if (in_array($component['roadmap'], $component) && $component['roadmap'] !== null) {
            var_dump($component['roadmap'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the roadmap because it is not set';
        }
        $maxRating++;

        if (in_array($component['developmentStatus'], $component) && $component['developmentStatus'] !== null ) {
            var_dump($component['developmentStatus'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the developmentStatus because it is not set';
        }
        $maxRating++;

        if (in_array($component['softwareType'], $component) && $component['softwareType'] !== null) {
            var_dump($component['softwareType'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the softwareType because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['localisedName'], $component) && $component['description']['localisedName'] !== null) {
            var_dump($component['description']['localisedName'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the localisedName because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['shortDescription'], $component) && $component['description']['shortDescription'] !== null) {
            var_dump($component['description']['shortDescription'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the shortDescription because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['longDescription'], $component) && $component['description']['longDescription'] !== null) {
            var_dump($component['description']['longDescription'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the longDescription because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['apiDocumentation'], $component) && $component['description']['apiDocumentation'] !== null) {
            var_dump($component['description']['apiDocumentation'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the apiDocumentation because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['features'], $component) && $component['description']['features'] !== null) {
            var_dump($component['description']['features'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the features because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['screenshots'], $component) && $component['description']['screenshots'] !== null) {
            var_dump($component['description']['screenshots'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the screenshots because it is not set';
        }
        $maxRating++;

        if (in_array($component['description']['videos'], $component) && $component['description']['videos'] !== null) {
            var_dump($component['description']['videos'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the videos because it is not set';
        }
        $maxRating++;

        if (in_array($component['legal']['license'], $component) && $component['legal']['license'] !== null) {
            var_dump($component['legal']['license'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the license because it is not set';
        }
        $maxRating++;

        if (in_array($component['legal']['mainCopyrightOwner'], $component) && $component['legal']['mainCopyrightOwner'] !== null) {
            var_dump($component['legal']['mainCopyrightOwner'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the mainCopyrightOwner because it is not set';
        }
        $maxRating++;

        if (in_array($component['legal']['repoOwner'], $component) && $component['legal']['repoOwner'] !== null) {
            var_dump($component['legal']['repoOwner'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the repoOwner because it is not set';
        }
        $maxRating++;

        if (in_array($component['legal']['authorsFile'], $component) && $component['legal']['authorsFile'] !== null) {
            var_dump($component['legal']['authorsFile'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the authorsFile because it is not set';
        }
        $maxRating++;

        if (in_array($component['maintenance']['type'], $component) && $component['maintenance']['type'] !== null) {
            var_dump($component['maintenance']['type'] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the type because it is not set';
        }
        $maxRating++;

        if (in_array($component['maintenance']['contractors'], $component) && $component['maintenance']['contractors'] !== null) {
            var_dump($component['maintenance']['contractors'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the contractors because it is not set';
        }
        $maxRating++;

        if (in_array($component['maintenance']['contacts'], $component) && $component['maintenance']['contacts'] !== null) {
            var_dump($component['maintenance']['contacts'][0] . ' is aanwezig');
            $rating++;
        } else {
            $description[] = 'Cannot rate the contacts because it is not set';
        }

        return [
            'rating' => $rating,
            'maxRating' => $maxRating,
            'results' => $description
        ];
    }
}
