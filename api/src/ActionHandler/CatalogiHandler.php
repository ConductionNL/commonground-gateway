<?php

namespace App\ActionHandler;

use App\Service\CatalogiService;

class CatalogiHandler implements ActionHandlerInterface
{
    private CatalogiService $catalogiService;

    /**
     * Wrapper function to prevent service loading on container autowiring
     *
     * @param CatalogiService $catalogiService
     * @return CatalogiService
     */
    private function getCatalogiService(CatalogiService $catalogiService):CatalogiService {
        if(isset($this->catalogiService)) {$this->catalogiService = $catalogiService;}
        return  $this->catalogiService;
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'CatalogiHandler',
            'required'   => ['entity', 'location', 'componentsEntity', 'componentsLocation'],
            'properties' => [
                'entity' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the Catalogi entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'location' => [
                    'type'        => 'string',
                    'description' => 'The location where we can find Catalogi',
                    'example'     => '/api/oc/catalogi',
                    'required'    => true,
                ],
                'componentsEntity' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the Component entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'componentsLocation' => [
                    'type'        => 'string',
                    'description' => 'The location where we can find Components',
                    'example'     => '/api/oc/components',
                    'required'    => true,
                ],
            ],
        ];
    }

    /**
     * This function runs the email service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->getcatalogiService()->catalogiHandler($data, $configuration);
    }
}
