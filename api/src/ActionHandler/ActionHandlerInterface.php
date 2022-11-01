<?php

namespace App\ActionHandler;
use App\Entity\ActionLog;

/**
 * The action handeler interface supports the developmant of action handles and makes sure that they comply to action handeler standards
 */
class ActionHandlerInterface
{

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [];
    }

    /**
     * This function runs the email service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     * @param ActionLog $actionLog The action log where a report can be logged to
     *
     * @return array
     */
    public function run(array $data, array $configuration, ActionLog $actionLog): array
    {
        return $data;
    }
}
