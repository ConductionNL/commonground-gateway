# Features
The web gateway is designed as an feature rich solution to quickly developing progressive web applications. 
## Sources
Sources form the beating heart of the gateway. A source represents an external API (be it either registered or microservice in nature) that might be exposed through the web gateway. Adding an API as a source WILL NOT cause that API to be exposed. API’s might be added manually our through discovery, discovery methods currently on the roadmap are NLX, Autorisatie Component and Generic Kubernetes services.

Properties
*name*:
*description* 
## Authentication
The web gateway provides several out of the box authentication mechanisms that are aimed at lowering the dependency on external services. The include DigiD and ADFS and also come with local and online mock environments. Creating an easy and straightforward flow to setting up application focuses on governmental services. 

## Authorisation
The web gateway can either provide an authorisation scheme on its own or deffer to other components like ADFS or het user component. 

## Multi-application
It is possible to split authorisation based on application profiles, providing basic framework for running multiple applications from the same gateway. 

## Multi-tenancy
The web gateway provides multi-tenancy by separating all the data it holds (both configuration and actual usage) along the lines of organizational id’s. This is hardcoded into a database query’s and thereby provides a save an fool proof way of running an  multi tenancy setop from an single installation. If you however prefer an seperation of databases on tenant base we advise you to use a multi-installation setup.
## NL Design System Ready
The wab gateway provides an ease (and inline scripting save) way of providing application with NL Design System token files based on the organisation that is currently served (see multy-tenency). This allows applications to easily implement NL Design by using nl Design tokens (a full list of which can be viewed here). The only actions an application need to take is to include the following line before loading other ccs files `/api/organisation/{id}/nl-design-tokens.css`. Keep in mind that from a security perspective the API needs to be provided from the same domain as the actual application.

## Haven ready
ad

## GDPR compliance and logging
The web gateway support logging all data requests and there ‘doelbinding’ to a gdpr registry (or in case of ducth governmental agencies the vng doelbindings register), be aware do that activating this functionality can lead to double registrations in said registry if the receiving api is also already logging all requests. Therefore please check the documentation of the api you are connecting before activating this function 

##N otifications and event driven
The web gateway can send notification on its own behalf when receiving and processing request (both on successful and unsuccessful results) and thereby trigger event driven applications (like a busnes engine), it can however also trigger events on the behalf of other api’s thereby providing event driven architecture to ecosystems that normally do not support such a scheme. In both cases the gateway requires an notification api to be present under its sources and configurations for the others sources or entities that should us this api.

## Smart cashing
The web gateway relies on smart caching to quickly serve required data, it does this by taking active subscriptions on any resources that it cashes. E.g. when cashing a case from zgw it will take an notificatie subscription, any time the case is changed the web gateway will drop it from it cache and (if cache warmup is activated) replace it with a new copy). This simple process means that any api that supports the notificatie or berichten standaard will be served to the client lightning fast. It also allows the gateway to share cache between applications (do not organisations) to speed the proses up even more. 

## Field limiting
The web gateway supports limiting the returned data by use of the fields query parameter. When supplied the gateway wil only return the requested attributes (even if the entity contains more attributes) and wil only log the requested fields in the GDPR registry. Fields can be supplied as comma separated or in an array as in ?fields=field1,field2,field2 or ?fields[]=field1&fields[]=field2. When dealing with sub entities simply listing the attribute containing the entity will result in returning the complete entity when only specific fields are required then a dot notation array can be used to filter those e.g. ?fields[]field1.subfield1. Field limitation is only supported to a field depth of 5. 

## Entity extension
Occasionally you might encounter a linked data uri to a different api in a commonground registry (zgw api’s are notorious examples) if sufficient rights have been provided (the references api is a valid gateway source and the user/application combination has right to an entity matching the uri’s endpoint) you can use the gateway to include the linked data object in the result if though it where one result (keep in mind do that this provides worse performance than just setting up the attribute properly). To do this you can use the extend query parameters e.g. `?extend[]=field1` which can also be used on sub entities by using dot annotation eg.  `?extend[]=field1.subfield1`. The gateway will try to use smart cashing to quickly deliver the desired sub entity but this will require additional internal calls at best, or an external api call at the worst. Severely impacting performance.
### Metadata extension
In addition to the way we can extend objects as described above, there is also a way to use the extend query parameter to show more metadata about objects. This can be done by using `?extend[]=x-commongateway-metadata` and if you just want one or a few specific metadata fields you could also do the following: `?extend[]=x-commongateway-metadata.dateCreated&extend[]=x-commongateway-metadata.dateModified`. Please do note that using the Accept headers application/json+ld or application/json+hal will always result in a response with metadata included (except for the field dateRead).

## Smart search 
The web gateway allows searching on any attribute that is marked as  ‘searchable’ even if the original API dosn’t allow for searching on that specific attribute or property. It also support the searching of sub entities by the way of dot annotation e.g. `?field1.subfield1=searchme. All searchable fields of an endpoint are automatically collected for fuzzy search under the search query parameter allows you to also use `?search=searchme` be aware tho that this comes with a severe performance drain and that specific searches are almost always preferable. Wildcard search is only supported on string type attributes. 

## Ordering results
adas

## Pagination
The web gateway supports two different approaches to pagination, allowing the developer choose what fits him best. When making a GET call to a collection endpoint e.g. `/pet` instead of `/pet/{petId}` the result will always be an array of objects in the results property of the response (do the array might consist of zero items).  Additionally a start(default 1), limit (default 25), page, pages and total property will be returned allowing you to slice the result any way you want. Basic slicing can be done using the start and limit properties, advanced slicing to the page end limit property (pagination). Lets assume that you have an collection containing a 125 result. A besic get would then get you something like 
```json
{
    "results": .. ,#array containing 25 items
    "total":125,
    "start":1,
    "limit":25,
    "page":1,
    "pages":5
}
```

Lets say that you are displaying the data in an table and want more results to start with so you set the limit to `?limit=100`, the expected result would then be
```json
{
    "results": .. , #array containing 100 items
    "total":125,
    "start":1,
    "limit":100,
    "page":1,
    "pages":2
}
```
You can now provide pagination buttons to you user based directly on the pages property, lets say you make a button for each page and the user presses page 2 (or next) your following call will then be  `?limit=100&page=2` and the result 
```json
{
    "results": .. , #array containing 25 items
    "total":125,
    "start":101,
    "limit":100,
    "page":2,
    "pages":2
}
```
Alternatively you could also query `?limit=100&start=101` for the same result

## Exporting files
At some points you might want to provide a user a download option for an export of file containing a data set, for GDPR reasons it is preferable to create these through the web gateway (so that the data transfer can be properly logged in the gdpr registry). For this reason all the collection endpoints support returning downloadable files besides returning json objects. To initiate a file download simply set the ‘accept’ head to the prefferd file format. We current support `text/csv`,`application/ms-excel`,`application/pdf` keep in mind that you can use the field, extend and pagination query functionality to influence the result/data set. 
## Entities and Attributes
Object storage and persistens to other api’s is handled trough an eav model. This means that any object from an underlying source can be made available through an mapping this mapping is necessary because you may not want all the properties of an api object to be externally available trough an ajax request, or you might want to provide additional validation or staked objects through the API. 

Generally speaking on underlying API will provide `endpoints` containing (collections of) `objects`. In case of the swagger petstore example that the `/pet` endpoint  contains a list of pets in an results array and the  `pet/{petId}` contains single pet `object` identified by id.

An pet object looks something like this
```json
{
  "id": "715d0656-55b3-481e-bacb-69eddabec373",
  "category": {
    "id": "715d0656-55b3-481e-bacb-69eddabec373",
    "name": "string"
  },
  "name": "doggie",
  "photoUrls": [
    "https://images.app.goo.gl/SPSCpXWePUpMGh4MA"
  ],
  "tags": [
    {
      "id": "715d0656-55b3-481e-bacb-69eddabec373",
      "name": "Golden retriever"
    }
  ],
  "status": "available"
}
```

So what does that mean for EAV mapping? Well simply put our objects become entities and our properties become attributes. So in this case we will have an entity called Pet that has the following attributes: ‘category’,’name’,’photoUrls’,’tags’ and ’status’/. Both entities and attributes can contain additional configuration, meaning the can do MORE than the original api object but never less. For example is the pet object requires you to provide a name for a pet (it does) you might chose to ignore that requirement on the name attribute but that will just cause the underlying api to throw an error any time you try to process a pet without a name. 


### Entity
Entities represent objects that you want to communicate to underlaying sources, to do this they require a source and an endpoint on that source to send the object to. You may however choose to ignore this. And just ‘store’ you objects with the gateway, do this might be a good way to mock api’s in development environments it isn’t recommended for production environments.

#### Properties
An entity consists of the following properties that can be configured

*name*: ‘required’ An unique name for this entity
*description*:  ‘optional’ The description for this entity that wil be shown in de API documentation
*source*: ‘optional’ The source where this entity resides
*endpoint*: ‘required if an source is provided’ The endpoint within the source that this entity should be posted to as an object
*file*: ‘required’ Whethers this entity might contain file uploads, if present this should either be set to ‘no’,’yes’ or ‘multiple’. Where yes wil allow a single file to be uploaded through this entity and multiple wil allow multiple files to be uploaded through this entity. Read more under ‘Uploading files through the gateway’.
*fileRequirements*: ‘required if file is either yes or multiple’ Read more under ‘Uploading files through the gateway’.

### Attribute
Attributes represent properties on objects that you want to communicatie to underlying sources. In a normal setup and attribute should at leas apply the same restrictions as the onderlying property (e.g. required) to prevent errors when pushing the entity to its source. It can however provide additional validations to an properte, for a example the source aiu might simply require the property ‘email’  to be an unique string but you could set the formt to ‘email’ causing the input to be validated as an iso compatible email adres. 


#### Properties
*name*: ’required’  An name for this attribute MUST be unique on an entity level and MAY NOT be ‘id’,’file’,‘files’, ’search’,’fields’,’start’,’page’,’limit’,’extend’,’organization’
*type*:  ’required’ See types
*format*:  ’optional’ See formats

#### Types
The type of an attribute provides basic validations and an way for the gateway to store and cahse values in an efficient manner. Types are derived from the OAS3 specification. Currently available types are:

*string*:
*int*
*date*
*date-time*

#### Formats
A format defines a way an value should be formated, and is directly connected to an type, for example an string MAY BE an format of email, but an integer cannot be an valid email. Formats are derived from the OAS3 specification but supplemented with formats that are generally needed in governmental applications (like bsn) . Currently available formats are:

*bsn*:
#### Validations
Besides validations on type and string you can also use specific validations, these are contained in the validation array. Validation might be specific to certain types or formats e.g. minValue can only be applied values that can be turned into numeric value. And other validations might be of an more general nature e.g. required.

## Mapping
Mapping is the process of changing the structure of an object. For example when an object is either send to or retrieved from an external source. This always follows in Output <- mapping <- Input model. Mapping is especially useful when source don’t match to the data model that you want to uses.

The gateway performs mapping as a serrie of mapping rules, handled in order. Mapping rules are always writen in a To <- From

### Simple mapping
In its most simple form a mapping consists of changing the position of value within an object, this can be done with a simple To <- From order. Or in other words you describe the object you want using the object (or other data) you have

So lets see a simple mapping rule, let say we have tree object like this
```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white three",
  "description": "Chestnut", 
  "location":"Orvil’s farm"
}
```

And we come to the conclusion that we need to move the data in the `description` field to a `species` field in order to free up the description field for more generic data. Wich we than also want to fill. In short move a value to a new position and insert a new value in the old position. We can do that with the following to mapping rules.

```json
{
    "species":"description",	
    "description":"This is the tree that granny planted when she and grams god married",
}
```

Wich wil give us 

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white three",
  "description": "This is the tree that granny planted when she and grams god married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

So what happend under the hood? And why is one value executed as a movement rule and the second as a string? Let take a look at the first rule

```json
{
    "species":"description"	
}
```

Rules are carried out as a `To <- From` pair. So in this case we are filling the `species` position with `description`. When interpeting what discription is the mapping service has two options. 
- The value is iether a dot notation array pointing to a another position in the object (see dot notation), then the value is of that position is copied to the new position
- The value is not a dot notation array to a another position in the object (see dot notation), then the value is renderd as a twig template 

### Twig mapping
Oke so what is this twig and how do we use it to transform or map out data?  let take a look a bit more complex mapping example. We haven an tree object in our datalayer  looking like
```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white three",
  "description": "This is the tree that granny planted when she and grams god married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```
Now the municipality opened up a county wide tree register and we would like to register our tree there. But the have decided too move locations and species of the tree into a metadata aray data, and thus expect an object like this
```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big old three",
  "description": "This is the tree that granny planted when she and grams god married", 
  "metadata":{
    "location":"Orvil’s farm", 
    "species":"Chestnut"
  }
}
```
Oke so lets put our mapping to the rescue!

A mapping always consist of an array where the array key’s are a dot notation of where we want our something to go. And an value representing what we want to go there. Thar value is a string that may contain twig logic. In this twig logic our original object is represented under de source object. So in this case we could do a mapping like

```json
{
    "metadata.location":"{{source.location }}",	
    "metadata.species":"{{source.species }}",
}
```
We would then end up wit a new object (after mapping) looking like
 ```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white three",
    "description": "This is the tree that granny planted when she and grams god married", 
    "location":"Orvil’s farm", 
    "species":"Chestnut", 
    "metadata":{
        "location":"Orvil’s farm",
        "species":"Chestnut"
    }
}
```

Keep in mind that both dotnation and twig based mapping can be used to move value's around in an object. But performance wise a dotnotation is preffered when is can be used

### Dropping key’s
Oke so that’s better but not exactly what we want. That’s because mapping alters the source object rather than replacing it. So we need to tell to mapping to drop tha data that we won’t need. We can do that under the “_drop” key. That accepts an array of dot notations to drop. Lets change the mapping to
```json
{
    "metadata.location":"{{source.location }}",	
    "metadata.species":"{{source.species }}",
    "_drop":[
        "location",
        "species"
    ]
}
```
We now have an object that’s
```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white three",
    "description":"This is the tree that granny planted when she and grams god married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut"
  }
}
```

Note that droping keys is always the last action perfomed in the mapping procces.

### Adding key’s
The mapping setup allows you to add key’s and values to objects simply by declaring them, lets look at the above example and assume that the county wants us to enter the primary color of the three. A value that we simply do not have in our object. But we assume al our threes to be brown.  We could then edit our mapping to

```json
{
    "metadata.location":"{{source.location }}",	
    "metadata.species":"{{source.species }}",
    "metadata.color":"Brown",
    "_drop":[
        "location",
        "species"
  ]
}
```

Witch would give us

```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white three",
    "description": "This is the tree that granny planted when she and grams god married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut", 
        "color":"Brown"
  }
}
```

Even trough we didn’t have a color value originally. Als node that we used a simple string value here instead of a twig code. Thats because twig template may contain strings.

### Working with conditional data
Twig nativly support a lot of [logical operators](https://twig.symfony.com/doc/3.x/templates.html) but a few of those are exeptionally handy when dealing with mappings the are  concating 
strings e.g. {{ 'string 1' ~ 'string 2' }} wich can also be used the source data inside mapping

```json
{
    "color": "{{ \"The color is \" ~ source.color }}"
}
```

The same can hower also be achieved with [string interpolation](https://twig.symfony.com/doc/1.x/templates.html#string-interpolation) via

```json
{
    "color": "{{ \"The color is {source.color}\" }}"
}
```

So both of the above notations would provide the same result

Another usesfull twig take is the if statement. This can be used both to check if a values exists in the first place

```json
{
    "color": "{% if source.color %} source.color {{ else }} unknown {{ endif }} }}"
}
```

Or to check for specific values

```json
{
    "color": "{% if source.color == \"violet\" %} pink {{ endif }} }}"
}
```

### Conditional mapping (beta)
There are cases when a mapping rule should only be run if certain conditions are met. This is done trough conditional mapping. To make a mapping conditional add a | to the mappings key followd bij a JSON Condition description. The mapping wil then only be executed if the JSON condition is met (results in true)

E.g

```json
{
    "color|{\"source.color\" : [\"blue\"]}": "{{ source.color }}",
    "metadata.color|{\"source.color\" : [\"blue\"]}": "{{ source.color }}"
}
```

At this moment in time it is not posible to build if/or/else cases with mappings. A single mapping rule is iether run or not

### Working with array
Its currently not possibe to re-map array (change the keys within an array) but you can map an array into its entirty into a key that expexcts an array by bypassing the twig handerl e.g.

```json
{
    "metadata":{
        "branches": [
            {
                "id":"475342cd-3551-4d55-aa4e-b734c1fda3f7",
                "name":"One"
                
            },
            {
                "id":"18e9d8be-32e5-4bec-93f2-55d60444fc4e",
                "name":"two"
            },
            {
                "id":"3ba4f1be-be2e-4e1a-ae83-04fed18fdf29",
                "name":"thre"
            }
        ]
    }
}
```

Under mapping 

```json
{
  "roots": "metadata.branches",
  "_drop": ["metadata.branches"]
}
```

Would become
```json
{
    "roots": [
        {
            "id":"475342cd-3551-4d55-aa4e-b734c1fda3f7",
            "name":"One"
            
        },
        {
            "id":"18e9d8be-32e5-4bec-93f2-55d60444fc4e",
            "name":"two"
        },
        {
            "id":"3ba4f1be-be2e-4e1a-ae83-04fed18fdf29",
            "name":"thre"
        }
    ]
}
```

### Forcing the type/format of values
Due to twig rendering, the output of a mapping will always change al the values to string. For internal gateway travel this isn’t problematic as the datalayer will cast value’s to the appropriate outputs anyway. But when sending data to an external source it might be bothersome to have al your Booleans cast to string. Fortunately you can force the datatype of your values yourself by adding |[format] behind your mapping value e.g. |string or |integer. Be ware of what php functions are used to map cast these values and the the cast should be possible (or else an error wil be thrown).

| Cast           	| Function                                                 	| Twig 	 |
|----------------	|----------------------------------------------------------	|--------|
| string         	| https://www.php.net/manual/en/function.strval.php        	| No  	  |
| bool / boolean 	| https://www.php.net/manual/en/function.boolval.php       	| No  	  |
| int / integer  	| https://www.php.net/manual/en/function.intval.php        	| No  	  |
| float          	| https://www.php.net/manual/en/function.floatval          	|  No  	  |
| array          	|                                                          	| No  	  |
| date           	| https://www.php.net/manual/en/function.date              	|  No  	  |
| url            	| https://www.php.net/manual/en/function.urlencode.php     	| 	Yes   |
| rawurl         	| https://www.php.net/manual/en/function.rawurlencode.php  	| 	Yes   |
| base64         	| https://www.php.net/manual/en/function.base64-encode.php 	| 	Yes   |
| json           	| https://www.php.net/manual/en/function.json-encode.php   	| 	Yes   |
| xml            	|                                                          	|  No  	  | 

Example a mapping of

```json
{
  ..
    "metadata.hasFruit": "Yes|bool",
  ..
}
```
Would result in

```json
{
  ...
    "metadata":{
      ...
      "hasFruit":true
  }
}
```

Keep in mind that format adjustment should be done outside the `{{`and `}}` twig brackets to overwrite the twig string output

### Translating values
Twigg naturaly supoort translations (full docs can be read [here](https://symfony.com/doc/current/translation.html)) but you should remember that translations are an active filter `|trans`. And thus should be specifacally called on values that you want to translate. Translations are perfomed against an translation table, you can read moet about configuring your transaltion table here. Keep in mind the base for translations is the locale as profided in the localisation header of a request. Or when sending data in the default setting of a gateway environment. You can also translate from an specif table and language by configuring the translation filter e.g. {{ 'greeting' | trans({}, `[table_name]`, `[language]`) }}

Original object

```json
{
    "color":"brown"
}
```

With mapping

```json
{
    "color":"{{source.color|trans({},\"colors\") }}"
}
```
gives us (on locale nl)

```json
{
    "color":"bruin"
}
```

If we would like to force german (even if the requester asked for a difrend language) we could map like 

```json
{
    "color":"{{source.color|trans({},\"colors\".\"de\") }}" 
}
```

And get

```json
{
    "color":"braun"
}
```


### Renaming Keys
The mapping dosn't support the renaming of keys directly but can rename key's indirectly by moving the data to a new position and dropping the old position

Original object

```json
{
    "name":"The big old three"
}
```

With mapping

```json
{
    "naam":"{{source.name }}",
  "_drop":[
      "name"
  ]
}
```
gives us

```json
{
    "naam":"The big old three"
}
```

### What if i can't map?
Even with al the above options it might be posible that the obejcts that you are looking at are simply to differend to map. In that case the go to sollution is don't. If objects A and B are to differend just add both of them to datalayer and write a plugin to keep them in sync bassed on actions.


## API Documentation
ad


## Translation table
Translation tables are specific by subject, and contain translations from the gateway base language (default en) to otherlanguages by iso language code e.g. fr (france) or nl (dutch). Translations are loaded and cashed from both plugins files, gatway files and the transaltion table. 

t


## API Documentation
ad

##U ploading files through the gateway
ad

## Easy configuration

### Dashboard
gjgh
### API
fgfhfgh
###Y AML
sdfs
