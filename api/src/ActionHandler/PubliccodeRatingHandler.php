<?php

namespace App\ActionHandler;

use App\Service\PubliccodeService;

class PubliccodeRatingHandler implements ActionHandlerInterface
{
    /**
     * Gets the PubliccodeService trough autowiring
     *
     * @param PubliccodeService $publiccodeService
     * @return PubliccodeService
     */
    private function getPubliccodeService(PubliccodeService $publiccodeService):PubliccodeService
    {
        return $publiccodeService;
    }

    public function getConfiguration()
    {
        return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'PubliccodeRatingHandler',
            'description'=> 'This handler sets the rating of a component',
            'required'   => ['componentEntityId', 'ratingEntityId'],
            'properties' => [
                'componentEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the component entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
                'ratingEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The uuid of the rating entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'required'    => true,
                ],
            ],
        ];
    }

    public function run(array $data, array $configuration): array
    {
        return $this->getPubliccodeService()->enrichComponentWithRating($data, $configuration);
    }
}
