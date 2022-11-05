<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\BijlagenArrayService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class BijlagenArrayHandler implements ActionHandlerInterface
{
    private BijlagenArrayService $bijlagenArrayService;

    /**
     * Wrapper function to prevent service loading on container autowiring
     *
     * @param BijlagenArrayService $bijlagenArrayService
     * @return BijlagenArrayService
     */
    private function getBijlagenArrayService(BijlagenArrayService $bijlagenArrayService):BijlagenArrayService {
        if(isset($this->bijlagenArrayService)) {$this->bijlagenArrayService = $bijlagenArrayService;}
        return  $this->bijlagenArrayService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://example.com/person.schema.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'BijlagenArrayHandler',
            'description' => 'This handler customly maps sim xml to zgw zaak and document ',
            'required'    => [],
            'properties'  => [],
        ];
    }

    /**
     * This function runs the service for validating cases.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->getbijlagenArrayService()->bijlagenArrayHandler($data, $configuration);
    }
}
