<?php

namespace App\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;

/**
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class AuthorizationService
{
    private ParameterBagInterface $parameterBag;
    private Security $security;
    private SessionInterface $session;
    private CacheInterface $cache;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ParameterBagInterface $parameterBag,
        Security $security,
        SessionInterface $session,
        CacheInterface $cache,
        EntityManagerInterface $entityManager
    ) {
        $this->parameterBag = $parameterBag;
        $this->security = $security;
        $this->session = $session;
        $this->cache = $cache;
        $this->entityManager = $entityManager;
    }

    /**
     * Returns an $info array with the correct keys for other functions that want to use this $info array. Tries to find values using these same keys in the $info array input, if not found a default value will be set for that key.
     *
     * @param array $info An array that can contain the following "keys"(=>Default): "method"=>"GET", "entity"=>null, "attribute"=>null, "object"=>null & "value"=>null.
     *
     * @return array
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function getValidInfoArray(array $info): array
    {
        $result = [
            'method'    => $info['method'] ?? 'GET',
            'entity'    => $info['entity'] ?? null,
            'attribute' => $info['attribute'] ?? null,
            'object'    => $info['object'] ?? null,
        ];
        if (array_key_exists('value', $info)) {
            $result['value'] = $info['value'];
        }

        return $result;
    }

    /**
     * Checks if a user is allowed to view or edit a resource (Entity) or a property of a resource (Attribute). This is done by getting the scopes (/roles) from the user and comparing these scopes with the data from the $info array input.
     *
     * @param array $info Can contain the following "keys"(=>Default): "method"=>"GET", "entity"=>null, "attribute"=>null, "object"=>null & "value"=>null. Should always, at least contain Entity or Attribute, else the check only passes if the user has admin scopes. Can also contain an Object (with Entity or Attribute) OR a Value (with Attribute) in order to check for 'valueScopes'.
     *
     * @throws AccessDeniedException
     *
     * @return void
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    public function checkAuthorization(array $info): void
    {
        if (!$this->parameterBag->get('app_auth')) {
            return;
        }

        $info = $this->getValidInfoArray($info);
        $requiredScopes = $this->getRequiredScopes($info);

        try {
            $grantedScopes = $this->getGrantedScopes();
        } catch (InvalidArgumentException|CacheException $e) {
            throw new AccessDeniedException('Something went wrong, please contact IT for help');
        }

        if (in_array($requiredScopes['admin_scope'], $grantedScopes)) {
            return;
        }

        if ($this->handleValueScopes($grantedScopes, $info)
            || in_array($requiredScopes['base_scope'], $grantedScopes)
            || (array_key_exists('sub_scope', $requiredScopes) && in_array($requiredScopes['sub_scope'], $grantedScopes))
            || (array_key_exists('sub_scopes', $requiredScopes) && array_intersect($requiredScopes['sub_scopes'], $grantedScopes))) {
            return;
        }
        if (array_key_exists('sub_scopes', $requiredScopes)) {
            $subScopes = '['.implode(', ', $requiredScopes['sub_scopes']).']';

            throw new AccessDeniedException("Insufficient Access, scope {$requiredScopes['base_scope']} or one of {$subScopes} is required");
        } elseif (array_key_exists('sub_scope', $requiredScopes)) {
            throw new AccessDeniedException("Insufficient Access, scope {$requiredScopes['base_scope']} or {$requiredScopes['sub_scope']} is required");
        }

        throw new AccessDeniedException("Insufficient Access, scope {$requiredScopes['base_scope']} is required");
    }

    /**
     * Gets the scopes required for viewing or editing a resource (Entity) or a property of a resource (Attribute).
     *
     * @param array $info Can contain the following "keys": "method", "entity", "attribute". Should contain Method and either Entity or Attribute.
     *
     * @return array The scopes required to {method} the given Entity or Attribute.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function getRequiredScopes(array $info): array
    {
        //TODO: maybe strtolower entity & attribute ->getName() here?

        $method = strtolower($info['method']);
        $entity = $info['entity'];
        $attribute = $info['attribute'];

        $requiredScopes['admin_scope'] = $method.'.admin';
        if ($entity) {
            $requiredScopes['base_scope'] = $method.'.'.strtolower($entity->getName());
            if ($method == 'get') { //TODO: maybe for all methods? but make sure to implement BL for it first!
                $requiredScopes['sub_scopes'] = [];
                $requiredScopes['sub_scopes'][] = $requiredScopes['base_scope'].'.id';
                if ($entity->getAvailableProperties()) {
                    $attributes = $entity->getAttributes()->filter(function (Attribute $attribute) use ($entity) {
                        // do not strtolower this name!
                        return in_array($attribute->getName(), $entity->getAvailableProperties());
                    });
                }
                foreach ($attributes ?? $entity->getAttributes() as $attribute) {
                    $requiredScopes['sub_scopes'][] = $requiredScopes['base_scope'].'.'.strtolower($attribute->getName());
                }
            }
        } elseif ($attribute) {
            $requiredScopes['base_scope'] = $method.'.'.strtolower($attribute->getEntity()->getName());
            $requiredScopes['sub_scope'] = $requiredScopes['base_scope'].'.'.strtolower($attribute->getName());
        } else {
            //todo maybe throw an error here?
        }

        return $requiredScopes;
    }

    /**
     * Gets the scopes of the current user. This can also be no user, in that case we try to find anonymous scopes.
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array|string[] The scopes granted to the current user.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function getGrantedScopes(): array
    {
        // First check if we have these scopes in cache (this gets removed from cache everytime we start a new api call, see eavService ->handleRequest)
        $item = $this->cache->getItem('grantedScopes');

        if ($item->isHit()) {
            $grantedScopes = $item->get();
        } else {
            $this->session->set('anonymous', false);

            if ($this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
                $grantedScopes = $this->getScopesFromRoles($this->security->getUser()->getRoles());
            } else {
                $grantedScopes = $this->getScopesForAnonymous();

                $this->session->set('anonymous', true);
            }
            $grantedScopes = array_merge($grantedScopes, $this->getContractScopes());
            $item->set($grantedScopes);
            $item->tag('grantedScopes');
            $this->cache->save($item);
        }

        return $grantedScopes;
    }

    /**
     * Gets the scopes of the current user, using the given $roles array with all the roles this user has.
     *
     * @param array $roles An array with all the roles of a user.
     *
     * @return string[] The scopes that match the given roles.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function getScopesFromRoles(array $roles): array
    {
        $scopes = [];

        foreach ($roles as $role) {
            if (strpos($role, 'scope') !== null) {
                $scopes[] = strtolower(substr($role, strlen('ROLE_scope.')));
            }
        }

        return $scopes;
    }

    /**
     * Gets the scopes for when there is no user logged in: anonymous scopes.
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array The scopes of an anonymous user.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    public function getScopesForAnonymous(): array
    {
        // First check if we have these scopes in cache (this gets removed from cache when a userGroup with name ANONYMOUS is changed, see FunctionService)
        $item = $this->cache->getItem('anonymousScopes');
        $itemOrg = $this->cache->getItem('anonymousOrg');
        if ($item->isHit() && $itemOrg->isHit()) {
            $this->session->set('organization', $itemOrg->get());

            return $item->get();
        }

        // Get the ANONYMOUS userGroup
        $groups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['name' => 'ANONYMOUS'], false)['hydra:member'];
        $scopes = [];
        if (count($groups) == 1) {
            foreach ($groups[0]['scopes'] as $scope) {
                $scopes[] = strtolower($scope['code']);
            }
            $this->session->set('organization', $groups[0]['organization']);
            $itemOrg->set($groups[0]['organization']);
            $itemOrg->tag('anonymousOrg');
            $this->cache->save($itemOrg);
        }
        if (count($scopes) > 0) {
            $scopes = array_map('strtolower', $scopes);

            // Save in cache
            $item->set($scopes);
            $item->tag('anonymousScopes');
            $this->cache->save($item);

            return $scopes;
        } else {
            throw new AuthenticationException('Authentication Required');
        }
    }

    /**
     * @TODO
     *
     * @return array
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function getContractScopes(): array
    {
        $scopes = [];
        $parameters = $this->session->get('parameters');
        if (!empty($parameters) && array_key_exists('headers', $parameters) && array_key_exists('contract', $parameters['headers']) && Uuid::isValid($parameters['headers']['contract'][0])) {
            $contract = $this->entityManager->getRepository('App:Contract')->findOneBy(['id' => $parameters['headers']['contract'][0]]);
            if (!empty($contract) && !empty($contract->getGrants())) {
                foreach ($contract->getGrants() as $grant) {
                    $scopes[] = strtolower($grant);
                }
            }
        }

        return $scopes;
    }

    /**
     * This function calls and handles the result of the checkValueScopes() function (= Checks if the user has scopes with =value and if so, check if the user is allowed to view or edit a resource (Entity) or property of a resource (Attribute) because of these scopes).
     * This function might throw an AccessDeniedException as result.
     *
     * @param array $grantedScopes The scopes granted to the current user.
     * @param array $info          Can contain the following "keys": "method", "entity", "attribute", "object" & "value". Should always contain an Object (with an Entity or an Attribute) OR a Value (with an Attribute) in order to check for and handle 'valueScopes'.
     *
     * @return bool True if the current user is granted one or more 'valueScopes' AND the user is allowed to view or edit the given Entity or Attribute in combination with the given Object or Value. If the user is not allowed to view or edit an AccessDeniedException will be thrown. False if there are no 'valueScopes' found.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function handleValueScopes(array $grantedScopes, array $info): bool
    {
        // Check for Value scopes
        $checkValueScopes = $this->checkValueScopes($grantedScopes, $info);

        // Handle the result
        if ($checkValueScopes['hasValueScopes']) {
            if ($checkValueScopes['matchValueScopes']) {
                return true;
            } else {
                $message = $checkValueScopes['error']['message'] ?? '';
                $scope = $checkValueScopes['error']['failedScope'] ?? '';
                $attributeName = $info['attribute'] ? $info['attribute']->getName() : ($checkValueScopes['error']['attribute'] ? $checkValueScopes['error']['attribute']->getName() : '');
                $shouldMatchValues = '';
                if ($checkValueScopes['error']['shouldMatchValues']) {
                    $shouldMatchValues = count($checkValueScopes['error']['shouldMatchValues']) > 1 ? 'one of ' : '';
                    $shouldMatchValues = $shouldMatchValues.'['.implode(', ', $checkValueScopes['error']['shouldMatchValues']).']';
                }
                $failedValue = $checkValueScopes['error']['failedValue'] ?? '';

                throw new AccessDeniedException("{$message}, because of scope {$scope}. {$attributeName} set to {$shouldMatchValues} is required. [{$failedValue}] is not allowed.");
            }
        }

        return false;
    }

    /**
     * Returns an array with the default context for the return array of the checkValueScopes() & loopGrantedScopes() functions.
     *
     * @param array $errorArray       See and use the checkValueScopesReturnError() function.
     * @param bool  $hasValueScopes   True if there is at least one grantedScope with a = value.
     * @param bool  $matchValueScopes True if the given Object or Value match the required value(s) of the 'valueScopes' (grantedScope(s) with = value) for the given Entity or Attribute.
     *
     * @return array An array with the expected return context for the checkValueScopes() & loopGrantedScopes() functions.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function checkValueScopesReturn(array $errorArray = [], bool $hasValueScopes = false, bool $matchValueScopes = false): array
    {
        return [
            'error'            => $errorArray,
            'hasValueScopes'   => $hasValueScopes,
            'matchValueScopes' => $matchValueScopes,
        ];
    }

    /**
     * Returns an array with the default context for the error array of the checkValueScopesReturn() function.
     *
     * @param string         $message     An error message, default = 'Something went wrong'.
     * @param array|null     $scopeValues The values that are allowed by the $failedScope.
     * @param null           $failedValue The value that did not match one of the $scopeValues.
     * @param string|null    $failedScope The scope that the user failed to 'match'.
     * @param Attribute|null $attribute   The attribute of the scope that the user failed to 'match' $failedScope.
     *
     * @return array An array with the expected return context for the error array of the checkValueScopesReturn() function.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function checkValueScopesReturnError(string $message = 'Something went wrong', array $scopeValues = null, $failedValue = null, string $failedScope = null, ?Attribute $attribute = null): array
    {
        return [
            'message'           => $message,
            'shouldMatchValues' => $scopeValues,
            'failedValue'       => $failedValue,
            'failedScope'       => $failedScope,
            'attribute'         => $attribute,
        ];
    }

    /**
     * Makes sure if the given $info array has the correct data to continue checking 'valueScopes'. And if not, tries to fix this with the other given data in the $info array.
     *
     * @param array $info The $info array. Can contain the following "keys": "method", "entity", "attribute", "object" & "value". Should always contain an Object (with an Entity or an Attribute) OR a Value (with an Attribute) in order to check for and handle 'valueScopes'.
     *
     * @return array The updated $info array or a checkValueScopesReturn() array containing an error message (also see checkValueScopesReturnError() function).
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function handleInfoArray(array $info): array
    {
        // We need at least a value or an object for the valueScopes check to work
        if (empty($info['object']) && !array_key_exists('value', $info)) { //$info['method'] !== GET makes sure we don't throw this error on get Collection calls
            return $this->checkValueScopesReturn($this->checkValueScopesReturnError('checkValueScopes has no object & no value'), $info['method'] !== 'GET');
        }

        // And we always need at least an Entity from somewhere
        if (empty($info['entity'])) {
            if (empty($info['attribute'])) {
                if (empty($info['object'])) {
                    return $this->checkValueScopesReturn($this->checkValueScopesReturnError('checkValueScopes has no entity (, attribute or object)'), true);
                }
                $info['entity'] = $info['object']->getEntity();
            } else {
                $info['entity'] = $info['attribute']->getEntity();
            }
        }

        // If no Value is given, but Object & Attribute are given, get Value from the Object using the Attribute.
        if (!array_key_exists('value', $info) && !empty($info['object']) && !empty($info['attribute']) &&
            !in_array($info['attribute']->getType(), ['object', 'file', 'array']) &&
            !$info['attribute']->getMultiple()
        ) {
            $info['value'] = $info['object']->getValue($info['attribute']);
        }

        return $info;
    }

    /**
     * Checks if the user has scopes with =value and if so, check if the user is allowed to view or edit a resource (Entity) or property of a resource (Attribute) because of these scopes.
     *
     * @param array $grantedScopes The scopes granted to the current user.
     * @param array $info          The $info array. Can contain the following "keys": "method", "entity", "attribute", "object" & "value". Should always contain an Object (with an Entity or an Attribute) OR a Value (with an Attribute) in order to check for and handle 'valueScopes'.
     *
     * @return array An array generated with the checkValueScopesReturn() function.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function checkValueScopes(array $grantedScopes, array $info): array
    {
        //todo: cache this function somehow? or parts of it (, or the functions used in this function)?

        $info = $this->handleInfoArray($info);
        if (array_key_exists('error', $info)) {
            return $info;
        }

        // If we have an Attribute and a Value we are checking for a single scope
        if (!empty($info['attribute']) && array_key_exists('value', $info)) {
            if ($info['method'] === 'GET' || $info['method'] === 'DELETE') {
                $message = 'Do not check for ValueScopes on Attribute & Value level when doing a GET or DELETE, because we can and should check for ValueScopes on Entity & Object level when doing a GET or DELETE';

                return $this->checkValueScopesReturn($this->checkValueScopesReturnError($message)); // does not result in an AccessDeniedException, because hasValueScopes = false
            }

            // Loop through all scopes the user has to find and handle scopes with = value check
            return $this->loopGrantedScopes($grantedScopes, $info);
        }
        // Else we are checking for multiple scopes of an Entity, if we also have an object, continue... (never continue if we have an Attribute!)
        elseif (!empty($info['entity']) && !empty($info['object']) && empty($info['attribute'])) {
            if ($info['method'] === 'POST') {
                $message = 'Do not check for ValueScopes on Entity & Object level when doing a POST, because values of the Object are not set at this point';

                return $this->checkValueScopesReturn($this->checkValueScopesReturnError($message)); // does not result in an AccessDeniedException, because hasValueScopes = false
            }

            // Loop through all scopes the user has to find and handle scopes with = value check
            return $this->loopGrantedScopes($grantedScopes, $info);
        }

        return $this->checkValueScopesReturn();
    }

    /**
     * Loop through all scopes the user has to find and handle scopes with = value check.
     *
     * @param array $grantedScopes The scopes granted to the current user.
     * @param array $info          The $info array. Can contain the following "keys": "method", "entity", "attribute", "object" & "value". Should always contain an Entity, with an Object OR a Value and can contain an Attribute, used in order to check for and handle 'valueScopes'.
     *
     * @return array An array generated with the checkValueScopesReturn() function.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function loopGrantedScopes(array $grantedScopes, array $info): array
    {
        foreach ($grantedScopes as $grantedScope) {
            // example: POST.organizations.type=taalhuis,team
            $result = $this->checkForValueScope($grantedScope, $info);
            if (!empty($result)) {
                $hasValueScopes = true;
                if ($result['matchValueScopes']) {
                    $matchValueScopes = true;
                } else {
                    return $this->checkValueScopesReturn(
                        $this->checkValueScopesReturnError(
                            'Insufficient Access',
                            $result['scopeValues'],
                            $result['failedValue'],
                            $grantedScope,
                            $result['scopeAttribute']
                        ),
                        true
                    );
                }
            }
        }

        return $this->checkValueScopesReturn(
            $this->checkValueScopesReturnError(),
            $hasValueScopes ?? false,
            $matchValueScopes ?? false
        );
    }

    /**
     * Checks if a given grantedScope of the current user is a = value (valueScope). And if so compares this scope with the given data in $info array to determine if the user is authorized or not.
     *
     * @param string $grantedScope A scope granted to the current user.
     * @param array  $info         The $info array. Can contain the following "keys": "method", "entity", "attribute", "object" & "value". Should always contain an Entity, with an Object OR a Value and can contain an Attribute, used in order to check for and handle a 'valueScope'.
     *
     * @return array|null If this returns an array and not null: (hasValueScopes = true) we have a = value scope, and we are sure the given input ($info) Entity (+Object) or Attribute (+Value) matches this scope. If an array is returned it will always contain the bool matchValueScopes, if matchValueScopes == false this array will also contain the following keys: scopeValues, failedValue & scopeAttribute.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function checkForValueScope(string $grantedScope, array $info): ?array
    {
        // example: POST.organizations.type=taalhuis,team
        $grantedScopeExploded = explode('=', $grantedScope);

        // If count grantedScope == 2, the user has a scope with a = value check.
        if (count($grantedScopeExploded) == 2) {
            // Get the sub_scope for the given Entity and/or Attribute we are going to check
            $subScopeResult = $this->getSubScope($grantedScopeExploded, $info); // todo caching? if the result is always the same
            if ($subScopeResult === null || $grantedScopeExploded[0] != $subScopeResult['sub_scope']) {
                // If we didn't find a sub_scope or if the scope we are checking (the part before the =) does not match this sub_scope
                // Continue checking other $grantedScopes with foreach...
                return null;
            }
            if (!array_key_exists('value', $info) && $subScopeResult['scopeAttribute']) {
                // Get the value from the Object with the attribute of this scope
                $info['value'] = $info['object']->getValue($subScopeResult['scopeAttribute']);
                //todo, what to do if a $info['value'] is empty?
            }

            $scopeValues = explode(',', $grantedScopeExploded[1]);

            // Compare value with allowed values from the scope
            if (in_array($info['value'], $scopeValues)) {
                // Found an allowed value, continue checking other $grantedScopes with foreach...
                return ['matchValueScopes' => true];
            }

            // Not allowed value, break checking other $grantedScopes with foreach and return unauthorized.
            return [
                'matchValueScopes' => false,
                'scopeValues'      => $scopeValues,
                'failedValue'      => $info['value'],
                'scopeAttribute'   => $subScopeResult['scopeAttribute'],
            ];
        }

        return null;
    }

    /**
     * Gets a $sub_scope using the Entity and Attribute from the $info array, if Attribute is not present, use the $info array to find the $sub_scope and the $scopeAttribute and compare it with $grantedScopeExploded data.
     *
     * @param array $grantedScopeExploded A valueScope granted to the current user. But an array (Exploded string) version, containing the 2 parts of the scope left and right of the '='.
     * @param array $info                 The $info array. Can contain the following "keys": "method", "entity", "attribute". Should contain Method and Entity, can also contain an Attribute.
     *
     * @return array|null Null if $grantedScopeExploded data doesn't match the $info data or if data in $info array is incorrect. Will return an array with sub_scope & scopeAttribute (default=null) if successful.
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    private function getSubScope(array $grantedScopeExploded, array $info): ?array
    {
        // Get the base scope for the given Entity and/or Attribute we are going to check
        $base_scope = $info['method'].'.'.$info['entity']->getName();

        // If we have an Attribute
        if ($info['attribute']) {
            // Get the sub scope for the given Attribute we are going to check
            $sub_scope = $base_scope.'.'.$info['attribute']->getName();
        } else {
            // Get info out of the scope
            $grantedScopeName = explode('.', $grantedScopeExploded[0]);
            $grantedScopeMethod = $grantedScopeName[0];
            // Make sure we only check scopes with the correct method
            if ($grantedScopeMethod !== strtolower($info['method'])) {
                return null;
            }
            // Get the attribute name out of the scope
            $attributeName = end($grantedScopeName);

            // Find the attribute with the $entity and $attributeName
            $scopeAttribute = $this->entityManager->getRepository('App:Attribute')->findOneBy(['entity' => $info['entity'], 'name' => $attributeName]);
            if (empty($scopeAttribute) ||
                in_array($scopeAttribute->getType(), ['object', 'file', 'array']) ||
                $scopeAttribute->getMultiple()
            ) {
                // If we don't find an Attribute for that Entity with the attribute name, continue
                // If the attribute will return a value we can not compare to a string, continue
                return null;
            } else {
                // Get the sub scope for the given Attribute we are going to check
                $sub_scope = $base_scope.'.'.$scopeAttribute->getName();
            }
        }

        return [
            'sub_scope'      => strtolower($sub_scope),
            'scopeAttribute' => $scopeAttribute ?? null,
        ];
    }

    /**
     * Gets all value scopes of the user and turns them into filters, this can be used so that the user only sees what he/she is allowed to see when doing a get collection call.
     *
     * @param Entity $entity
     *
     * @throws AccessDeniedException
     *
     * @return array
     * @deprecated Make sure we do not lose any BL used here, we are just currently not using this function for checking authorization!
     */
    public function valueScopesToFilters(Entity $entity): array
    {
        $filters = [];

        try {
            $grantedScopes = $this->getGrantedScopes();
        } catch (InvalidArgumentException|CacheException $e) {
            throw new AccessDeniedException('Something went wrong, please contact IT for help');
        }

        foreach ($grantedScopes as $grantedScope) {
            // example: POST.organizations.type=taalhuis,team
            $grantedScopeExploded = explode('=', $grantedScope);

            // If count grantedScope == 2, the user has a scope with a = value check.
            if (count($grantedScopeExploded) == 2) {
                // Get the sub_scope for the given Entity we are going to check
                $subScopeResult = $this->getSubScope($grantedScopeExploded, $this->getValidInfoArray(['entity' => $entity])); // todo caching? if the result is always the same
                if ($subScopeResult === null || $grantedScopeExploded[0] != $subScopeResult['sub_scope']) {
                    // If we didn't find a sub_scope or if the scope we are checking (the part before the =) does not match this sub_scope
                    // Continue checking other $grantedScopes
                    continue;
                }

                $scopeValues = explode(',', $grantedScopeExploded[1]);
                $filters[$subScopeResult['scopeAttribute']->getName().'|valueScopeFilter'] = $scopeValues;
            }
        }

        return $filters;
    }

    /**
     * @TODO
     *
     * @param string                $contentType
     * @param SerializerService     $serializerService
     * @param AccessDeniedException $exception
     *
     * @return Response
     */
    public function serializeAccessDeniedException(string $contentType, SerializerService $serializerService, AccessDeniedException $exception): Response
    {
        return new Response(
            $serializerService->serialize(
                new ArrayCollection(['message' => $exception->getMessage()]),
                $serializerService->getRenderType($contentType),
                []
            ),
            $exception->getCode(),
            ['Content-Type' => $contentType]
        );
    }
}
