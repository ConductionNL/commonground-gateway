# App\Entity\ObjectEntity  

An (data) object that resides within the datalayer of the gateway.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#objectentity__construct)||
|[addError](#objectentityadderror)|Adds ans error to the error stack of this object.|
|[addObjectValue](#objectentityaddobjectvalue)||
|[addPromise](#objectentityaddpromise)||
|[addRequestLog](#objectentityaddrequestlog)||
|[addResponseLog](#objectentityaddresponselog)||
|[addSubresourceOf](#objectentityaddsubresourceof)||
|[addSynchronization](#objectentityaddsynchronization)||
|[addUsedIn](#objectentityaddusedin)||
|[changeCascade](#objectentitychangecascade)|Cascades a 'is changed' upwards, with other words notifies objects that us this object has changed so that they to ara changes.|
|[checkConditionlLogic](#objectentitycheckconditionllogic)|Checks conditional logic on values.|
|[findSubresourceOf](#objectentityfindsubresourceof)|Try to find a Value this ObjectEntity is a child of. Searching/filtering these values by a specific Attribute.|
|[getAllErrors](#objectentitygetallerrors)||
|[getAllPromises](#objectentitygetallpromises)||
|[getAllSubresources](#objectentitygetallsubresources)||
|[getApplication](#objectentitygetapplication)||
|[getAttributeObject](#objectentitygetattributeobject)|Gets a Attribute object based on the attribute string name.|
|[getDateCreated](#objectentitygetdatecreated)||
|[getDateModified](#objectentitygetdatemodified)||
|[getEntity](#objectentitygetentity)||
|[getErrors](#objectentitygeterrors)||
|[getExternalId](#objectentitygetexternalid)||
|[getExternalResult](#objectentitygetexternalresult)||
|[getHasErrors](#objectentitygethaserrors)||
|[getHasPromises](#objectentitygethaspromises)||
|[getId](#objectentitygetid)||
|[getName](#objectentitygetname)||
|[getObjectValues](#objectentitygetobjectvalues)||
|[getOrganization](#objectentitygetorganization)||
|[getOwner](#objectentitygetowner)||
|[getPromises](#objectentitygetpromises)||
|[getReadableSyncDataArray](#objectentitygetreadablesyncdataarray)|Adds the most important data of all synchronizations this Object has to an array and returns this array or null if this Object has no Synchronizations.|
|[getRequestLogs](#objectentitygetrequestlogs)||
|[getResponseLogs](#objectentitygetresponselogs)||
|[getSelf](#objectentitygetself)||
|[getSubresourceIndex](#objectentitygetsubresourceindex)||
|[getSubresourceOf](#objectentitygetsubresourceof)||
|[getSubresources](#objectentitygetsubresources)|Function to get al the subresources of this object entity.|
|[getSynchronizations](#objectentitygetsynchronizations)||
|[getUri](#objectentitygeturi)||
|[getUsedIn](#objectentitygetusedin)||
|[getValue](#objectentitygetvalue)|Gets the value of the Value object based on the attribute string name or attribute object.|
|[getValueObject](#objectentitygetvalueobject)|Gets a Value object based on the attribute string name or attribute object.|
|[hydrate](#objectentityhydrate)|Populate this object with an array of values, where attributes are diffined by key.|
|[includeEmbeddedArray](#objectentityincludeembeddedarray)|This function will check if the given array has an embedded array, if so it will move all objects from the embedded array to the keys outside this embedded array and (by default) unset the entire embedded array.|
|[prePersist](#objectentityprepersist)|Set name on pre persist.|
|[removeObjectValue](#objectentityremoveobjectvalue)||
|[removeRequestLog](#objectentityremoverequestlog)||
|[removeResponseLog](#objectentityremoveresponselog)||
|[removeSubresourceOf](#objectentityremovesubresourceof)||
|[removeSynchronization](#objectentityremovesynchronization)||
|[removeUsedIn](#objectentityremoveusedin)||
|[setApplication](#objectentitysetapplication)||
|[setDateCreated](#objectentitysetdatecreated)||
|[setDateModified](#objectentitysetdatemodified)||
|[setDefaultValues](#objectentitysetdefaultvalues)||
|[setEntity](#objectentitysetentity)||
|[setErrors](#objectentityseterrors)||
|[setExternalId](#objectentitysetexternalid)||
|[setExternalResult](#objectentitysetexternalresult)||
|[setHasErrors](#objectentitysethaserrors)||
|[setHasPromises](#objectentitysethaspromises)||
|[setId](#objectentitysetid)||
|[setName](#objectentitysetname)||
|[setOrganization](#objectentitysetorganization)||
|[setOwner](#objectentitysetowner)||
|[setPromises](#objectentitysetpromises)||
|[setSelf](#objectentitysetself)||
|[setSubresourceIndex](#objectentitysetsubresourceindex)||
|[setUri](#objectentityseturi)||
|[setValue](#objectentitysetvalue)|Sets a value based on the attribute string name or atribute object.|
|[toArray](#objectentitytoarray)|Convienance API for throwing an data object and is children into an array.|




### ObjectEntity::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addError  

**Description**

```php
public addError (string $attributeName, string $error)
```

Adds ans error to the error stack of this object. 

 

**Parameters**

* `(string) $attributeName`
: the atribute that throws the error  
* `(string) $error`
: the error message  

**Return Values**

`array`

> all of the errors so far


<hr />


### ObjectEntity::addObjectValue  

**Description**

```php
 addObjectValue (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addPromise  

**Description**

```php
 addPromise (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addRequestLog  

**Description**

```php
 addRequestLog (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addResponseLog  

**Description**

```php
 addResponseLog (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addSubresourceOf  

**Description**

```php
 addSubresourceOf (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addSynchronization  

**Description**

```php
 addSynchronization (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::addUsedIn  

**Description**

```php
 addUsedIn (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::changeCascade  

**Description**

```php
public changeCascade (\DateTimeInterface $dateModified)
```

Cascades a 'is changed' upwards, with other words notifies objects that us this object has changed so that they to ara changes. 

 

**Parameters**

* `(\DateTimeInterface) $dateModified`

**Return Values**

`$this`




<hr />


### ObjectEntity::checkConditionlLogic  

**Description**

```php
public checkConditionlLogic (void)
```

Checks conditional logic on values. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`$this`




<hr />


### ObjectEntity::findSubresourceOf  

**Description**

```php
public findSubresourceOf (\Attribute $attribute)
```

Try to find a Value this ObjectEntity is a child of. Searching/filtering these values by a specific Attribute. 

 

**Parameters**

* `(\Attribute) $attribute`

**Return Values**

`\ArrayCollection`




<hr />


### ObjectEntity::getAllErrors  

**Description**

```php
 getAllErrors (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getAllPromises  

**Description**

```php
 getAllPromises (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getAllSubresources  

**Description**

```php
 getAllSubresources (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getApplication  

**Description**

```php
 getApplication (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getAttributeObject  

**Description**

```php
public getAttributeObject (string $attributeName)
```

Gets a Attribute object based on the attribute string name. 

 

**Parameters**

* `(string) $attributeName`

**Return Values**

`\Attribute|false`

> Returns an Attribute if its found or false when its not found.


<hr />


### ObjectEntity::getDateCreated  

**Description**

```php
 getDateCreated (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getDateModified  

**Description**

```php
 getDateModified (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getEntity  

**Description**

```php
 getEntity (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getErrors  

**Description**

```php
 getErrors (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getExternalId  

**Description**

```php
 getExternalId (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getExternalResult  

**Description**

```php
 getExternalResult (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getHasErrors  

**Description**

```php
 getHasErrors (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getHasPromises  

**Description**

```php
 getHasPromises (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getId  

**Description**

```php
 getId (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getName  

**Description**

```php
 getName (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getObjectValues  

**Description**

```php
 getObjectValues (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getOrganization  

**Description**

```php
 getOrganization (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getOwner  

**Description**

```php
 getOwner (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getPromises  

**Description**

```php
 getPromises (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getReadableSyncDataArray  

**Description**

```php
public getReadableSyncDataArray (void)
```

Adds the most important data of all synchronizations this Object has to an array and returns this array or null if this Object has no Synchronizations. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array|null`




<hr />


### ObjectEntity::getRequestLogs  

**Description**

```php
 getRequestLogs (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getResponseLogs  

**Description**

```php
 getResponseLogs (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getSelf  

**Description**

```php
 getSelf (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getSubresourceIndex  

**Description**

```php
 getSubresourceIndex (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getSubresourceOf  

**Description**

```php
 getSubresourceOf (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getSubresources  

**Description**

```php
public getSubresources (void)
```

Function to get al the subresources of this object entity. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`\ArrayCollection`

> the subresources of this object entity


<hr />


### ObjectEntity::getSynchronizations  

**Description**

```php
 getSynchronizations (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getUri  

**Description**

```php
 getUri (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getUsedIn  

**Description**

```php
 getUsedIn (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::getValue  

**Description**

```php
public getValue (string|\Attribute $attribute)
```

Gets the value of the Value object based on the attribute string name or attribute object. 

 

**Parameters**

* `(string|\Attribute) $attribute`

**Return Values**

`array|bool|string|int|object`

> Returns a Value if its found or false when its not found.


<hr />


### ObjectEntity::getValueObject  

**Description**

```php
public getValueObject (string|\Attribute $attribute)
```

Gets a Value object based on the attribute string name or attribute object. 

 

**Parameters**

* `(string|\Attribute) $attribute`

**Return Values**

`\Value|bool`

> Returns a Value if its found or false when its not found.


<hr />


### ObjectEntity::hydrate  

**Description**

```php
public hydrate (array $array, bool $unsafe)
```

Populate this object with an array of values, where attributes are diffined by key. 

 

**Parameters**

* `(array) $array`
: the data to set  
* `(bool) $unsafe`
: unset atributes that are not inlcuded in the hydrator array  

**Return Values**

`\ObjectEntity`




**Throws Exceptions**


`\Exception`


<hr />


### ObjectEntity::includeEmbeddedArray  

**Description**

```php
public includeEmbeddedArray (array $array, bool $unsetEmbeddedArray)
```

This function will check if the given array has an embedded array, if so it will move all objects from the embedded array to the keys outside this embedded array and (by default) unset the entire embedded array. 

 

**Parameters**

* `(array) $array`
: The array to move and unset embedded array from.  
* `(bool) $unsetEmbeddedArray`
: Default=true. If false the embedded array will not be removed from $array.  

**Return Values**

`array`

> The updated/changed $array. Or unchanged $array if it did not contain an embedded array.


<hr />


### ObjectEntity::prePersist  

**Description**

```php
public prePersist (void)
```

Set name on pre persist. 

This function makes sure that each and every oject alwys has a name when saved 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::removeObjectValue  

**Description**

```php
 removeObjectValue (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::removeRequestLog  

**Description**

```php
 removeRequestLog (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::removeResponseLog  

**Description**

```php
 removeResponseLog (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::removeSubresourceOf  

**Description**

```php
 removeSubresourceOf (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::removeSynchronization  

**Description**

```php
 removeSynchronization (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::removeUsedIn  

**Description**

```php
 removeUsedIn (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setApplication  

**Description**

```php
 setApplication (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setDateCreated  

**Description**

```php
 setDateCreated (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setDateModified  

**Description**

```php
 setDateModified (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setDefaultValues  

**Description**

```php
 setDefaultValues (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setEntity  

**Description**

```php
 setEntity (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setErrors  

**Description**

```php
 setErrors (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setExternalId  

**Description**

```php
 setExternalId (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setExternalResult  

**Description**

```php
 setExternalResult (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setHasErrors  

**Description**

```php
 setHasErrors (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setHasPromises  

**Description**

```php
 setHasPromises (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setId  

**Description**

```php
 setId (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setName  

**Description**

```php
 setName (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setOrganization  

**Description**

```php
 setOrganization (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setOwner  

**Description**

```php
 setOwner (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setPromises  

**Description**

```php
 setPromises (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setSelf  

**Description**

```php
 setSelf (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setSubresourceIndex  

**Description**

```php
 setSubresourceIndex (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setUri  

**Description**

```php
 setUri (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ObjectEntity::setValue  

**Description**

```php
public setValue (string|\Attribute $attribute,  $value, bool $unsafe)
```

Sets a value based on the attribute string name or atribute object. 

 

**Parameters**

* `(string|\Attribute) $attribute`
* `() $value`
* `(bool) $unsafe`

**Return Values**

`false|\Value`




**Throws Exceptions**


`\Exception`


<hr />


### ObjectEntity::toArray  

**Description**

```php
public toArray (array $configuration)
```

Convienance API for throwing an data object and is children into an array. 

 

**Parameters**

* `(array) $configuration`
: The configuration for this function  

**Return Values**

`array`

> the array holding all the data     *


<hr />

