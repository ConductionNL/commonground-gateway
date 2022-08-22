<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Service\ObjectEntityService;
use App\Service\SynchronizationService;
use App\Service\GithubService;
use App\Service\GitlabService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml;

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
        $entity = $this->entityManager->getRepository('App:Entity')->get(this->configuration['repositoryEntityId']);;
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

        // Load from config
        $gateway = $this->entityManager->getRepository('App:Entity')->get($this->configuration['sourceId']);
        $githubRepositoryActionId = $this->entityManager->getRepository('App:Entity')->get($this->configuration['githubRepositoryActionId']);
        $gitlabRepositoryActionId = $this->entityManager->getRepository('App:Entity')->get($this->configuration['gitlabRepositoryActionId']);
        $entity = $this->entityManager->getRepository('App:Entity')->get(this->configuration['repositoryEntityId']);

        // Let see if it is one or alle organisations
        if(!empty($data)){ // it is one organisation
            // Heb ik een id?

            // trycatch
            $organisation = $this->objectEntityService->getObject($data['id']);

            // Get organisation from github
            $organisation = $this->githubService->getOrganisationOnUrl($organisation['github']);

            // Even kijken of dit kliopt met github object
            foreach($organisation['repositories'] as $repository){
                // Creat a sync trough not finding it
                $sync = $this->findSyncBySource($gateway, $entity ,$repository['id']);

                // activate sync to pull in data
                $sync = $this->handleSync($sync, $githubRepositoryActionId->getConfiguration(), $repository);
            }

        }

        // If we want to do it for al repositiries
        $entity = $this->entityManager->getRepository('App:Entity')->get($this->configuration['organisationEntityId']);
        foreach ($entity->getObjectEntities() as $organisation) {

            // Get organisation from github
            $organisation = $this->githubService->getOrganisationOnUrl($organisation['github']);

            // Even kijken of dit kliopt met github object
            foreach($organisation['repositories'] as $repository){
                // Creat a sync trough not finding it
                $sync = $this->findSyncBySource($gateway, $entity ,$repository['id']);

                // activate sync to pull in data
                $sync = $this->handleSync($sync, $githubRepositoryActionId->getConfiguration(), $repository);
            }
        }
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

        $componentEntity = $this->entityManager->getRepository('App:Entity')->get(this->configuration['componentEntityId']);

        if(!empty($data)){ // it is one organisation
            // Heb ik een id?

            // trycatch
            $repository = $this->objectEntityService->getObject($data['id']);

            if(!$component = $repository->GetValueOnAtribute('component')){
                $component = New ObjectEntity();
                $component->setEntity($componentEntity);
                $component->setValue('repository',$repository);
                $component->setValue('name',$repository['name']);
                $component->setValue('url',$repository['url']);
            }

            switch ($repository['source']){
                case 'github':
                    //@todo zouden we via een sync moeten willen. Dus na hier negeren
                    if($file = $this->githubService->getRepositoryFileFromUrl($repository['url'], 'publiccode.yaml'));
                    {
                        $yamlPubliccode = decode($file['content']);
                        $component = $this->parsePubliccodeToComponent(Yaml::parse($yamlPubliccode), $repository->GetValueOnAtribute('component'));
                        // @todosave component
                    }
                case 'gitlab':

                default:
                    //@todo gooi error
            }

        }

        // If we want to do it for al repositiries
        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['name' => 'Repository']);
        foreach ($entity->getObjectEntities() as $repository) {

        }

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
        if(!empty($data)){ // it is one organisation
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
                 /// etc voor logo en andere waarden bla bla
                $organisation->setValue('github',$github['organisation']['url']);

                return $organisation;
            case 'gitlab':
                // hetelfde maar dan voor gitlab
            default:
                // error voor onbeknd type
        };
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

        if(in_array['name'],$component)){
            $rating ++;
        }

        //@todo checks doornemen

        return $component;
    }

}
