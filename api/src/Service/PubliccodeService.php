<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Response;

class PubliccodeService
{
    private EntityManagerInterface $entityManager;
    private GithubApiService $githubService;
    private array $configuration;
    private array $data;

    public function __construct(
        EntityManagerInterface $entityManager,
        GithubApiService $githubService
    ) {
        $this->entityManager = $entityManager;
        $this->githubService = $githubService;
        $this->configuration = [];
        $this->data = [];
    }

    /**
     * @param array $content
     *
     * @throws GuzzleException
     *
     * @return Response dataset at the end of the handler
     */
    public function updateRepositoryWithEventResponse(array $content): Response
    {
        $repositoryEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Repository']);
        $componentEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Component']);
        $organisationEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Organisation']);
        $descriptionEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Description']);
        $ratingEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Rating']);

        $repositoryName = $content['repository']['name'];

        if (!$this->entityManager->getRepository('App:ObjectEntity')->findByEntity($repositoryEntity, ['name' => $repositoryName])) {
            $repository = new ObjectEntity();
            $repository->setEntity($repositoryEntity);
        } else {
            $repository = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($repositoryEntity, ['name' => $repositoryName])[0];
        }

        if ($publiccodeUrl = $repository->getValue('publiccode_url')) {
            if (is_array($publiccode = $this->githubService->getPubliccode($publiccodeUrl))) {
                $this->enrichRepositoryWithPubliccode($repository, $componentEntity, $descriptionEntity, $publiccode);
            }
        } elseif ($publiccode = $this->githubService->getPubliccodeForGithubEvent($content['organization']['login'], $content['repository']['name'])) {
            $this->enrichRepositoryWithPubliccode($repository, $componentEntity, $descriptionEntity, $publiccode);
        }

        $this->enrichRepositoryWithOrganisation($repository, $organisationEntity);

        if ($organisation = $repository->getValue('organisation')) {
            if ($organisation instanceof ObjectEntity) {
                $organisation = $this->enrichRepositoryWithOrganisationRepos($organisation, $repositoryEntity);
                $this->getOrganizationCatalogi($organisation);
            }
        }

        if ($component = $repository->getValue('component')) {
            $this->rateComponent($component, $ratingEntity);
        }

        $repository->setValue('name', $content['repository']['name']);
        $this->entityManager->persist($repository);
        $this->entityManager->flush();

        return new Response(json_encode($repository->toArray()), 200, ['content-type' => 'application/json']);
    }

    /**
     * @param ObjectEntity $repository
     * @param Entity       $componentEntity
     * @param Entity       $descriptionEntity
     * @param array        $publiccode
     *
     * @throws Exception
     *
     * @return ObjectEntity|null dataset at the end of the handler
     */
    public function enrichRepositoryWithPubliccode(ObjectEntity $repository, Entity $componentEntity, Entity $descriptionEntity, array $publiccode = []): ?ObjectEntity
    {
        if (!$repository->getValue('component')) {
            $component = new ObjectEntity();
            $component->setEntity($componentEntity);
        } else {
            $component = $repository->getValue('component');
        }

        if ($publiccode !== null) {
            $component->setValue('softwareVersion', $publiccode['publiccodeYmlVersion'] ?? null);
            $component->setValue('name', $publiccode['name'] ?? null);
            $component->setValue('softwareType', $publiccode['softwareType'] ?? null);
            $component->setValue('inputTypes', $publiccode['inputTypes'] ?? null);
            $component->setValue('outputTypes', $publiccode['outputTypes'] ?? null);
            $component->setValue('platforms', $publiccode['platforms'] ?? null);
            $component->setValue('categories', $publiccode['categories'] ?? null);
            $component->setValue('developmentStatus', $publiccode['developmentStatus'] ?? null);
            $repository->setValue('url', $publiccode['url'] ?? null);

//            $component->setValue('releaseDate', $publiccode['releaseDate']);
//            $component->setValue('dependsOn', $publiccode['dependsOn']['open']['name']);
//            $component->setValue('dependsOn', $publiccode['dependsOn']['open']['versionMin']);

            if (!$component->getValue('description')) {
                $description = new ObjectEntity();
                $description->setEntity($descriptionEntity);
            } else {
                $description = $component->getValue('description');
            }

            $description->setValue('shortDescription', $publiccode['description']['nl']['shortDescription'] ?? null);
            $description->setValue('documentation', $publiccode['description']['nl']['documentation'] ?? null);
            $description->setValue('apiDocumentation', $publiccode['description']['nl']['apiDocumentation'] ?? null);
            $description->setValue('shortDescription', $publiccode['description']['en']['shortDescription'] ?? null);
            $description->setValue('documentation', $publiccode['description']['en']['documentation'] ?? null);
            $description->setValue('apiDocumentation', $publiccode['description']['en']['apiDocumentation'] ?? null);

            $component->setValue('description', $description);
            $this->entityManager->persist($description);

            $component->setValue('url', $repository);
            $this->entityManager->persist($component);
            $repository->setValue('component', $component);
            $this->entityManager->persist($repository);
            $this->entityManager->flush();
        } else {
            $component->setValue('name', $repository->getValue('name'));
        }

        return $repository;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws GuzzleException
     *
     * @return array dataset at the end of the handler
     */
    public function enrichPubliccodeHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $repositoryEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);
        $componentEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['componentEntityId']);
        $descriptionEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['descriptionEntityId']);
        // If we want to do it for al repositories
        foreach ($repositoryEntity->getObjectEntities() as $repository) {
            if ($publiccodeUrl = $repository->getValue('publiccode_url')) {
                if (is_array($publiccode = $this->githubService->getPubliccode($publiccodeUrl))) {
                    $this->enrichRepositoryWithPubliccode($repository, $componentEntity, $descriptionEntity, $publiccode);
                }
            }
        }

        return $this->data;
    }

    /**
     * @param ObjectEntity $repository the repository where we want to find an organisation for
     *
     * @throws Exception
     */
    public function setRepositoryWithGithubInfo(ObjectEntity $repository, $github): ObjectEntity
    {
        $repository->setValue('source', 'github');
        $repository->setValue('name', $github['name']);
        $repository->setValue('url', $github['url']);
        $repository->setValue('avatar_url', $github['avatar_url']);
        $repository->setValue('last_change', $github['last_change']);
        $repository->setValue('stars', $github['stars']);
        $repository->setValue('fork_count', $github['fork_count']);
        $repository->setValue('issue_open_count', $github['issue_open_count']);
        $repository->setValue('programming_languages', $github['programming_languages']);

        $this->entityManager->persist($repository);

        return $repository;
    }

    /**
     * @param ObjectEntity $repository the repository where we want to find an organisation for
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function enrichRepositoryWithOrganisation(ObjectEntity $repository, Entity $organisationEntity): ?ObjectEntity
    {
        if (!$repository->getValue('url')) {
            return null;
        }
        $source = $repository->getValue('source');
        $url = $repository->getValue('url');

        if ($source == null) {
            $domain = parse_url($url, PHP_URL_HOST);
            $domain == 'github.com' && $source = 'github';
            $domain == 'gitlab.com' && $source = 'gitlab';
        }

        switch ($source) {
            case 'github':
                // let's get the repository data
                $github = $this->githubService->getRepositoryFromUrl(trim(parse_url($url, PHP_URL_PATH), '/'));
                if ($github !== null && array_key_exists('organisation', $github) && $github['organisation'] !== null) {
                    $repository = $this->setRepositoryWithGithubInfo($repository, $github);

                    if (!$this->entityManager->getRepository('App:ObjectEntity')->findByEntity($organisationEntity, ['github' => $github['organisation']['github']])) {
                        $organisation = new ObjectEntity();
                        $organisation->setEntity($organisationEntity);
                    } else {
                        $organisation = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($organisationEntity, ['github' => $github['organisation']['github']])[0];
                    }

                    $organisation->setValue('owns', $github['organisation']['owns']);
                    $organisation->hydrate($github['organisation']);
                    $repository->setValue('organisation', $organisation);
                    $this->entityManager->persist($organisation);
                    $this->entityManager->persist($repository);
                    $this->entityManager->flush();

                    return $repository;
                }
            case 'gitlab':
                // hetelfde maar dan voor gitlab
            default:
                // error voor onbeknd type
        }

        return null;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws GuzzleException
     *
     * @return array dataset at the end of the handler
     */
    public function enrichRepositoryWithOrganizationHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $repositoryEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);
        $organisationEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['organisationEntityId']);

        // If we want to do it for al repositories
        foreach ($repositoryEntity->getObjectEntities() as $repository) {
            $this->enrichRepositoryWithOrganisation($repository, $organisationEntity);
        }

        return $this->data;
    }

    /**
     * @param string $repositoryUrl
     * @param Entity $repositoryEntity
     *
     * @throws GuzzleException
     *
     * @return ObjectEntity|null
     */
    public function getOrganisationRepos(string $repositoryUrl, Entity $repositoryEntity): ?ObjectEntity
    {
        $source = null;
        $domain = parse_url($repositoryUrl, PHP_URL_HOST);
        $domain == 'github.com' && $source = 'github';
        $domain == 'gitlab.com' && $source = 'gitlab';

        switch ($source) {
            case 'github':
                // let's get the repository data
                $github = $this->githubService->getRepositoryFromUrl(trim(parse_url($repositoryUrl, PHP_URL_PATH), '/'));

                if ($github !== null) {
                    if (!$this->entityManager->getRepository('App:ObjectEntity')->findByEntity($repositoryEntity, ['url' => $github['url']])) {
                        $repository = new ObjectEntity();
                        $repository->setEntity($repositoryEntity);
                    } else {
                        $repository = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($repositoryEntity, ['url' => $github['url']])[0];
                    }

                    $repository = $this->setRepositoryWithGithubInfo($repository, $github);

                    $this->entityManager->flush();

                    return $repository;
                }
            case 'gitlab':
                // hetelfde maar dan voor gitlab
            default:
                // error voor onbeknd type
        }

        return null;
    }

    /**
     * @param ObjectEntity $organisation
     * @param Entity       $repositoryEntity
     *
     * @throws GuzzleException
     *
     * @return ObjectEntity|null
     */
    public function enrichRepositoryWithOrganisationRepos(ObjectEntity $organisation, Entity $repositoryEntity): ?ObjectEntity
    {
        $ownsRepositories = [];
        if ($owns = $organisation->getValue('owns')) {
            foreach ($owns as $repositoryUrl) {
                if (!$repository = $this->getOrganisationRepos($repositoryUrl, $repositoryEntity)) {
                    return $organisation;
                }
                $ownsRepositories[] = $repository->getId()->toString();
            }
        }
        $organisation->setValue('owns', $ownsRepositories);

        $usesRepositories = [];
        if ($uses = $organisation->getValue('uses')) {
            foreach ($uses as $repositoryUrl) {
                if (!$repository = $this->getOrganisationRepos($repositoryUrl, $repositoryEntity)) {
                    return $organisation;
                }
                $usesRepositories[] = $repository->getId()->toString();
            }
        }
        $organisation->setValue('uses', $usesRepositories);

        $supportsRepositories = [];
        if ($supports = $organisation->getValue('supports')) {
            foreach ($supports as $repositoryUrl) {
                if (!$repository = $this->getOrganisationRepos($repositoryUrl, $repositoryEntity)) {
                    return $organisation;
                }
                $supportsRepositories[] = $repository->getId()->toString();
            }
        }
        $organisation->setValue('supports', $supportsRepositories);

        $this->entityManager->persist($organisation);
        $this->entityManager->flush();

        return $organisation;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws GuzzleException
     *
     * @return array dataset at the end of the handler
     */
    public function enrichOrganizationWithRepositoriesHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $repositoryEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['repositoryEntityId']);
        // If we want to do it for al repositories
        foreach ($repositoryEntity->getObjectEntities() as $repository) {
            if ($organisation = $repository->getValue('organisation')) {
                if ($organisation instanceof ObjectEntity) {
                    $this->enrichRepositoryWithOrganisationRepos($organisation, $repositoryEntity);
                }
            }
        }

        return $this->data;
    }

    /**
     * @param ObjectEntity $organization
     *
     * @throws GuzzleException
     *
     * @return array|null dataset at the end of the handler
     */
    public function getOrganizationCatalogi(ObjectEntity $organization): ?array
    {
        if ($this->githubService->getGithubRepoFromOrganization($organization->getValue('name'))) {
            if ($catalogi = $this->githubService->getOpenCatalogiFromGithubRepo($organization->getValue('name'))) {
                try {
                    $organization->setValue('name', $catalogi['name']);
                    $organization->setValue('description', $catalogi['description']);
                    $organization->setValue('type', $catalogi['type']);
                    $organization->setValue('telephone', $catalogi['telephone']);
                    $organization->setValue('email', $catalogi['email']);
                    $organization->setValue('website', $catalogi['website']);
                    $organization->setValue('logo', $catalogi['logo']);
                    $organization->setValue('catalogusAPI', $catalogi['catalogusAPI']);
                    $organization->setValue('uses', $catalogi['uses']);
                    $organization->setValue('supports', $catalogi['supports']);
                } catch (Exception $exception) {
                    var_dump("Data error for {$organization->getValue('name')}, {$exception->getMessage()}");
                }

                $this->entityManager->persist($organization);
                $this->entityManager->flush();
            }
        }

        return null;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws GuzzleException
     *
     * @return array dataset at the end of the handler
     */
    public function enrichOrganizationWithCatalogi(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $organizationEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['organisationEntityId']);
        // If we want to do it for al repositories
        foreach ($organizationEntity->getObjectEntities() as $organization) {
            if ($organization->getValue('github')) {
                // get org name and search if the org has an .github repository
                $this->getOrganizationCatalogi($organization);
            }
        }

        return $this->data;
    }

    /**
     * @param array $data          data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @throws GuzzleException|Exception
     *
     * @return array dataset at the end of the handler
     */
    public function enrichComponentWithRating(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data = $data;

        $componentEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['componentEntityId']);
        $ratingEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['ratingEntityId']);

        foreach ($componentEntity->getObjectEntities() as $component) {
            $this->rateComponent($component, $ratingEntity);
        }

        return $this->data;
    }

    /**
     * @param ObjectEntity $component
     * @param Entity       $ratingEntity
     *
     * @throws Exception
     *
     * @return ObjectEntity|null dataset at the end of the handler
     */
    public function rateComponent(ObjectEntity $component, Entity $ratingEntity): ?ObjectEntity
    {
        $ratingComponent = $this->ratingList($component);

        if (!$component->getValue('rating')) {
            $rating = new ObjectEntity();
            $rating->setEntity($ratingEntity);
        } else {
            $rating = $component->getValue('rating');
        }

        $rating->setValue('rating', $ratingComponent['rating']);
        $rating->setValue('maxRating', $ratingComponent['maxRating']);
        $rating->setValue('results', $ratingComponent['results']);
        $this->entityManager->persist($rating);
        $this->entityManager->flush();

        $component->setValue('rating', $rating);
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        return $component;
    }

    /**
     * Rates a component.
     *
     * @param ObjectEntity $component
     *
     * @throws Exception|GuzzleException
     *
     * @return ObjectEntity|null dataset at the end of the handler
     */
    public function ratingList(ObjectEntity $component): ?array
    {
        $rating = 0;
        $maxRating = 0;
        $description = [];

        if ($component->getValue('name') !== null) {
            $description[] = 'The name: '.$component->getValue('name').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the name because it is not set';
        }
        $maxRating++;

        // @todo does not work yet
        if ($repository = $component->getValue('url')) {
            if ($repository->getValue('url') !== null) {
                $description[] = 'The url: '.$repository->getValue('url').' rated';
                $rating++;

                if ($this->githubService->checkPublicRepository($repository->getValue('url'))) {
                    $description[] = 'Rated the repository because it is public';
                    $rating++;
                } else {
                    $description[] = 'Cannot rated the repository because it is private';
                }
                $maxRating++;
            } else {
                $description[] = 'Cannot rate the url because it is not set';
            }
            $maxRating++;
        }
        $maxRating = $maxRating + 2;

        if ($component->getValue('landingURL') !== null) {
            $description[] = 'The landingURL: '.$component->getValue('landingURL').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the landingURL because it is not set';
        }
        $maxRating++;

        if ($component->getValue('softwareVersion') !== null) {
            $description[] = 'The softwareVersion: '.$component->getValue('softwareVersion').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the softwareVersion because it is not set';
        }
        $maxRating++;

        if ($component->getValue('releaseDate') !== null) {
            $description[] = 'The releaseDate: '.$component->getValue('releaseDate').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the releaseDate because it is not set';
        }
        $maxRating++;

        if ($component->getValue('logo') !== null) {
            $description[] = 'The logo: '.$component->getValue('logo').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the logo because it is not set';
        }
        $maxRating++;

        if ($component->getValue('roadmap') !== null) {
            $description[] = 'The roadmap: '.$component->getValue('roadmap').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the roadmap because it is not set';
        }
        $maxRating++;

        if ($component->getValue('developmentStatus') !== null) {
            $description[] = 'The developmentStatus: '.$component->getValue('developmentStatus').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the developmentStatus because it is not set';
        }
        $maxRating++;

        if ($component->getValue('softwareType') !== null) {
            $description[] = 'The softwareType: '.$component->getValue('softwareType').' rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the softwareType because it is not set';
        }
        $maxRating++;

        if (count($component->getValue('platforms')) > 0) {
            $description[] = 'The platforms are rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the platforms because it is not set';
        }
        $maxRating++;

        if (count($component->getValue('categories')) > 0) {
            $description[] = 'The categories are rated';
            $rating++;
        } else {
            $description[] = 'Cannot rate the categories because it is not set';
        }
        $maxRating++;

        if ($descriptionObject = $component->getValue('description')) {
            if ($descriptionObject->getValue('localisedName') !== null) {
                $description[] = 'The localisedName: '.$descriptionObject->getValue('localisedName').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the localisedName because it is not set';
            }
            $maxRating++;

            if ($descriptionObject->getValue('shortDescription') !== null) {
                $description[] = 'The shortDescription: '.$descriptionObject->getValue('shortDescription').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the shortDescription because it is not set';
            }
            $maxRating++;

            if ($descriptionObject->getValue('longDescription') !== null) {
                $description[] = 'The longDescription: '.$descriptionObject->getValue('longDescription').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the longDescription because it is not set';
            }
            $maxRating++;

            if ($descriptionObject->getValue('apiDocumentation') !== null) {
                $description[] = 'The apiDocumentation: '.$descriptionObject->getValue('apiDocumentation').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the apiDocumentation because it is not set';
            }
            $maxRating++;

            if (count($descriptionObject->getValue('features')) > 0) {
                $description[] = 'The features are rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the features because it is not set';
            }
            $maxRating++;

            if (count($descriptionObject->getValue('screenshots')) > 0) {
                $description[] = 'The screenshots are rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the screenshots because it is not set';
            }
            $maxRating++;

            if (count($descriptionObject->getValue('videos')) > 0) {
                $description[] = 'The videos are rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the videos because it is not set';
            }
            $maxRating++;
        } else {
            $description[] = 'Cannot rate the description object because it is not set';
            $maxRating = $maxRating + 7;
        }

        if ($legalObject = $component->getValue('legal')) {
            if ($legalObject->getValue('license') !== null) {
                $description[] = 'The license: '.$legalObject->getValue('license').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the license because it is not set';
            }
            $maxRating++;

            if ($mainCopyrightOwnerObject = $legalObject->getValue('mainCopyrightOwner')) {
                if ($mainCopyrightOwnerObject->getValue('mainCopyrightOwner') !== null) {
                    $description[] = 'The mainCopyrightOwner: '.$mainCopyrightOwnerObject->getValue('name').' rated';
                    $rating++;
                } else {
                    $description[] = 'Cannot rate the mainCopyrightOwner because it is not set';
                }
                $maxRating++;
            }

            if ($repoOwnerObject = $legalObject->getValue('repoOwner')) {
                if ($repoOwnerObject->getValue('repoOwner') !== null) {
                    $description[] = 'The repoOwner is rated';
                    $rating++;
                } else {
                    $description[] = 'Cannot rate the repoOwner because it is not set';
                }
                $maxRating++;
            }

            if ($legalObject->getValue('authorsFile') !== null) {
                $description[] = 'The authorsFile: '.$legalObject->getValue('authorsFile').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the authorsFile because it is not set';
            }
            $maxRating++;
        } else {
            $description[] = 'Cannot rate the legal object because it is not set';
            $maxRating = $maxRating + 2;
        }

        if ($maintenanceObject = $component->getValue('maintenance')) {
            if ($maintenanceObject->getValue('type') !== null) {
                $description[] = 'The type: '.$maintenanceObject->getValue('type').' rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the type because it is not set';
            }
            $maxRating++;

            if (count($maintenanceObject->getValue('contractors')) > 0) {
                $description[] = 'The contractors are rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the contractors because it is not set';
            }
            $maxRating++;

            if (count($maintenanceObject->getValue('contacts')) > 0) {
                $description[] = 'The contacts are rated';
                $rating++;
            } else {
                $description[] = 'Cannot rate the contacts because it is not set';
            }
            $maxRating++;
        } else {
            $description[] = 'Cannot rate the maintenance object because it is not set';
            $maxRating = $maxRating + 3;
        }

        return [
            'rating'    => $rating,
            'maxRating' => $maxRating,
            'results'   => $description,
        ];
    }
}
