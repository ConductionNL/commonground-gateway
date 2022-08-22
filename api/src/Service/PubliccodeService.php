<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use App\Service\SynchronizationService;
use App\Service\GithubService;
use App\Service\GitlabService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class PubliccodeService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;
    private ObjectEntityService $objectEntityService;
    private GithubService $githubService;
    private GitlabService $gitlabService;
    private array $configuration;

    public function __construct(ContainerInterface $container) {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $synchronizationService = $container->get('synchronizationservice');
        $objectEntityService = $container->get('objectentityService');
        $githubService = $container->get('githubservice');
        $gitlabService = $container->get('gitlabservice');
        $configuration = [];
    }

    /**
     *
     *
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeFindOrganisationsTroughRepositoriesHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        // Let if we have a single repository
        if(!empty($data)){ // it is one organisation
            // Heb ik een id?

            // trycatch
            $repository = $this->objectEntityService->getObject($data['id']);

            // Set see if we have an org // CHECK DIT! Returnd dit false als niks is gevonden
            if($repository->getValueByAttribute('organisation')){
                // There is alread an orangisation so we dont need to do anything
                return $data;
            }

            $organisation = $this->getOrganistionFromRepository($repository);
            $repository->setValue('organisation', $organisation);
            $this->objectEntityService->saveObject($repository);
        }

        // If we want to do it for al repositiries
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Repository']);
        foreach ($entity->getObjectEntities() as $repository) {

            // Set see if we have an org // CHECK DIT! Returnd dit false als niks is gevonden
            if($repository->getValueByAttribute('organisation')){
                // There is alread an orangisation so we dont need to do anything
                continue;
            }

            $organisation = $this->getOrganistionFromRepository($repository);
            $repository->setValue('organisation', $organisation);
            $this->objectEntityService->saveObject($repository);
        }

        return $data;
    }

    /**
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeFindRepositoriesThroughOrganisationsHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

        // Let see if it is one or alle organisations
        if(!empty($data)){ // it is one organisation

        }
    }

    /**
     * @param array $data data set at the start of the handler
     * @param array $configuration configuration of the action
     *
     * @return array dataset at the end of the handler
     */
    public function publiccodeCheckForPublicCodeHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;

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

    }


    /**
     * @param ObjectEntity $repository the repository where we want to find an organisation for
     */
    public function getOrganistionFromRepository(ObjectEntity $repository): ObjectEntity
    {
        $source = $repository->getValueByAttribute('type');
        $url = $repository->getValueByAttribute('url');

        switch ($source){
            case 'github':
                // lets get the repository data
                $github = $this->githubService->getRepositoryFromUrl($url);

                // checken of er een RepositoryEntityId in de config zit
                $entity = $this->entityManager->getRepository('Entity')->get($this->configuration['RepsoitoryEntityId']); // get from config
                // even checken of we de enituy ook hebben

                // lets see if we have an organisations // even uitzoeken
                if($organisation === $this->objectEntityService->findOneBy($entity, ['github' => $github['organisation']['url']])){
                    return $organisation;
                }

                $organisation = New ObjectEntity();
                $organisation->setEntity($entity);
                $organisation->setValue('name',$github['organisation']['name']);
                 /// etc voor logoe bla bla
                $organisation->setValue('github',$github['organisation']['url']);

                return $organisation;
            case 'gitlab':
                // hetelfde maar dan voor gitlab
            default:
                // error voor onbeknd type
        };
    }
}
