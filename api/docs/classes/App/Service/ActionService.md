# App\Service\ActionService  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#actionservice__construct)||
|[getAllActionHandlers](#actionservicegetallactionhandlers)|Generates a list of all action handlers.|
|[getHandlerForAction](#actionservicegethandlerforaction)|Get the action handler for an action.|




### ActionService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### ActionService::getAllActionHandlers  

**Description**

```php
public getAllActionHandlers (void)
```

Generates a list of all action handlers. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array`




**Throws Exceptions**


`\Exception`


<hr />


### ActionService::getHandlerForAction  

**Description**

```php
public getHandlerForAction (\Action $action)
```

Get the action handler for an action. 

 

**Parameters**

* `(\Action) $action`

**Return Values**

`object|null`




<hr />

