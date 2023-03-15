<?php

namespace App\Service;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\SamlBundle\Security\User\AuthenticationUser;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class UserService
{
    private CommonGroundService $commonGroundService;
    private ObjectEntityService $objectEntityService;

    public function __construct(CommonGroundService $commonGroundService, ObjectEntityService $objectEntityService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->objectEntityService = $objectEntityService;
    }

    public function getPersonForUser(UserInterface $user): array
    {
        if (!($user instanceof AuthenticationUser)) {
            return [];
        }
        if ($user->getPerson() && $person = $this->objectEntityService->getObjectByUri($user->getPerson())) {
            return $person;
        } elseif ($user->getPerson()) {
            try {
                $id = substr($user->getPerson(), strrpos($user->getPerson(), '/') + 1);

                if (!$this->commonGroundService->getComponent('cc') ||
                    !$person = $this->commonGroundService->getResource($user->getPerson())) {
                    $person = $this->objectEntityService->getPersonObject($id);
                }
                if (empty($person)) {
                    throw new Exception();
                }
            } catch (Exception $exception) {
                $person = $this->objectEntityService->getUserObjectEntity($user->getUsername());
            }
        } else {
            $person = $this->objectEntityService->getUserObjectEntity($user->getUsername());
        }

        return $person;
    }

    public function getOrganizationForUser(UserInterface $user): array
    {
        if (!($user instanceof AuthenticationUser)) {
            return [];
        }
        if (!$user->getOrganization()) {
            return [];
        } else {
            $organizationFields = [
                'name'               => true, 'type' => true, 'addresses' => true, 'emails' => true, 'telephones' => true,
                'parentOrganization' => [
                    'name' => true, 'type' => true, 'addresses' => true, 'emails' => true, 'telephones' => true,
                ],
            ];
            if (!($organization = $this->objectEntityService->getObjectByUri($user->getOrganization(), $organizationFields, $organizationFields))) {
                try {
                    $id = substr($user->getOrganization(), strrpos($user->getOrganization(), '/') + 1);

                    if (!$this->commonGroundService->getComponent('cc') ||
                        !$organization = $this->commonGroundService->getResource($user->getOrganization())) {
                        $organization = $this->objectEntityService->getOrganizationObject($id);
                    }
                    if (empty($organization)) {
                        throw new Exception();
                    }
                } catch (Exception $exception) {
                    return [];
                }
            }
        }

        return $organization;
    }
}
