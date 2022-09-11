#Features
The web gateway is designed as a feature rich's solution to quickly developing progressive web applications.
##Sources
Sources form the beating heart of the gateway. A source represents an external API (be it either registered or microservice in nature) that might be exposed through the web gateway. Adding an API as a source WILL NOT cause that API to be exposed. API’s might be added manually or through discovery, discovery methods currently on the roadmap are NLX, Autorisatie Component and Generic Kubernetes services.

Properties
*name*:
*description*
##Authentication
The web gateway provides several out of the box authentication mechanisms that are aimed at lowering the dependency on external services. Includes DigiD and ADFS and also come with local and online mock environments. Creating an easy and straightforward flow to setting up application focuses on governmental services.

##Authorisation
The web gateway can either provide an authorisation scheme on its own or deffer to other components like ADFS or the user component.

##Multi-application
It is possible to split authorisation based on application profiles, providing a basic framework for running multiple applications from the same gateway.

##Multi-tenancy
The web gateway provides multi-tenancy by separating all the data it holds (both configuration and actual usage) along the lines of organizational id’s. This is hardcoded into a database query’s and thereby provides a save a full proof way of running a multi-tenancy setup from a single installation. If you however prefer a separation of databases on tenant base, we advise you to use a multi-installation setup.
##NL Design System Ready
The web gateway provides an ease (and inline scripting save) way of providing an application with NL Design System token files based on the organization that is currently served (see multi-tenancy). This allows applications to easily implement NL Design by using NL Design tokens (a full list of which can be viewed here). The only actions an application need to take is to include the following line before loading other CCS files `/api/organization/{id}/nl-design-tokens.css`. Keep in mind that from a security perspective the API needs to be provided from the same domain as the actual application.

## Haven ready
ad

##GDPR compliance and logging
The web gateway support logging all data requests and there ‘doelbinding’ to a gdpr registry (or in case of Dutch governmental agencies the VNG doelbindings register), be aware that activating this functionality can lead to double registrations in said registry if the receiving API is also already logging all requests. Therefore please check the documentation of the API you are connecting before activating this function

##Notifications and event driven
The web gateway can send notification on its own behalf when receiving and processing request (both on successful and unsuccessful results) and thereby trigger event-driven applications (like a business engine), it can however also trigger events on the behalf of other API’s thereby providing event-driven architecture to ecosystems that normally do not support such a scheme. In both cases the gateway requires a notification API to be present under its sources and configurations for the others sources or entities that should use this API.

##Smart cashing
The web gateway relies on smart caching to quickly serve required data, it does this by taking active subscriptions on any resources that it cashes. E.g. when cashing a case from zgw it will take a notificatie subscription, any time the case is changed the web gateway will drop it from it cache and (if cache warmup is activated) replace it with a new copy). This simple process means that any api that supports the notificatie or berichten standaard will be served to the client lightning fast. It also allows the gateway to share cache between applications (do not organisations) to speed the proses up even more.

##Field limiting
The web gateway supports limiting the returned data by use of the field's query parameter. When supplied, the gateway will only return the requested attributes (even if the entity contains more attributes) and will only log the requested fields in the GDPR registry. Fields can be supplied as comma separated or in an array as in ?fields=field1,field2,field2 or ?fields[]=field1&fields[]=field2. When dealing with sub entities simply listing the attribute containing the entity will result in returning the complete entity when only specific fields are required then a dot notation array can be used to filter those e.g. ?fields[]field1.subfield1. Field limitation is only supported to a field depth of 5.

##Entity extension
Occasionally you might encounter a linked data URI to a different API in a common ground registry (ZGW API’s are notorious examples) if sufficient rights have been provided (the references API is a valid gateway source and the user/application combination has right to an entity matching the URI’s endpoint) you can use the gateway to include the linked data object in the result if through it where one result (keep in mind do that this provides worse performance than just setting up the attribute properly). To do this you can use the extend query parameters e.g. `?extend=field1` which can also be used on sub entities by way’s od dot annotation eg.  `?extend=field1.subfield1`. The gateway will try to use smart cashing to quickly deliver the desired sub entity but this will require additional internal calls at best or an external API call at the worst. Severely impacting performance.

##Smart search
The web gateway allows searching on any attribute that is marked as  ‘searchable’ even if the original API doesn’t allow for searching on that specific attribute or property. It also supports the searching of sub entities by the way of dot annotation e.g. `?field1.subfield1=searchme. All searchable fields of an endpoint are automatically collected for fuzzy search under the search query parameter allows you to also use `?search=searchme` be aware tho that this comes with a severe performance drain and that specific searches are almost always preferable. Wildcard search is only supported on string type attributes.

##Data formats
The web gateway defaults to the currently popular json data format, but supports other data formats out of the box, keep in mind that the data format determines how the data is presented to the consuming application. E.g it is possible to have the Gateway consume an json api but present its results as XML to a web application. The data format need to bes set on each call through the Accept header. The gateway currently supports the following Accept headers and corresponding formats.

`text/csv`,`text/yaml`,`application/json`,`application/ld+json`, `application/json+ld`,`application/hal+json`,`application/json+hal`,`application/xml`

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


###Entity
Entities represent objects that you want to communicate to underlying sources, to do this they require and source and an endpoint on that source to send the object to. You may however choose to ignore this. And just ‘store’ your objects with the gateway, this might be a good way to mock API’s in development environments it isn’t recommended for production environments.

####Properties
An entity consists of the following properties that can be configured

| Property | Required | Description |
|--------|-------------| -------------|
|name| yes |An unique name for this entity|
|description| no | The description for this entity that wil be shown in de API documentation|
|source| no |The source where this entity resides|
|endpoint| yes if an source is provided | The endpoint within the source that this entity should be posted to as an object|
|route| no | The route this entity can be found easier, should be a path |
|extend| no | Whether or not the properties of the original object are automatically included|


###Attribute
Attributes represent properties on objects that you want to communicate to underlying sources. In a normal setup and attribute should at leas apply the same restrictions as the underlying property (e.g. required) to prevent errors when pushing the entity to its source. It can however provide additional validations to a property, for example the source AIU might simply require the property ‘email’  to be a unique string, but you could set the form to ‘email’ causing the input to be validated as an ISO compatible email address.


####Properties

| Property | Required | Description |
|--------|-------------| -------------|
|name| yes  |`string` An name for this attribute. MUST be unique on an entity level and MAY NOT be ‘id’,’file’,‘files’, ’search’,’fields’,’start’,’page’,’limit’,’extend’ or ’organization’|
|description| no | The description for this attribute  that wil be shown in de API documentation|
|type|  yes |`string` See [types](#Types)|
|format|  no |`string` See [formats](#Formats)|
|validations|  no |`array of strings` See [validations](#Validations)|
|multiple|  no |`boolean` if this attribute expects an array of the given type |
|defaultValue|  no |`string` An default value for this value that will be used if a user doesn't supply a value|
|deprecated|  no |`boolean`  Whether or not this property has been deprecated and wil be removed in the future|
|required|  no |`boolean` whether or not this property is required to be in a POST or UPDATE|
|requiredIf| no |`array` a nested set of validations that will cause this attribute to become required |
|forbidden|  no |`boolean` whether or not this property is forbiden to be in a POST or UPDATE|
|forbiddenIf| no |`array`  a nested set of validations that will cause this attribute to become forbidden|
|example|  no |`string` An example of the value that should be supplied|
|persistToSource|  no |`boolean` Setting this property to true wil force the property to be saved in the gateway endpoint (default behafure is saving in the EAV)|
|searchable|  no |`boolean` Whether or not this property is searchable|
|cascade|  no |`boolean`  Whether or not this property kan be used to create new entities (versus when it can only be used to link exsisting entities)|

####Types
The type of attribute provides basic validations and a way for the gateway to store and cash values in an efficient manner. Types are derived from the OAS3 specification. Current available types are:

| Format | Description |
|--------|-------------|
|string| a text |
|integer| a full number without decimals|
|decimal| a number including decimals|
|boolean| a true/false |
|date| an ISO-??? date |
|date-time| an ISO-??? date |
|array| an array or list of values|
|object|Used to nest a Entity as atribute of antother Entity, read more about [nesting]()|
|file|Used to handle file uploads, an Entity SHOULD only contain one atribute of the type file, read more about [handling file uploads]() |

* you are allowed to use integer instead of int, boolean instead of bool, date-time or dateTime instead of datetime,

####Formats
A format defines a way a value should be formatted, and is directly connected to a type, for example a string MAY BE a format of email, but an integer cannot be a valid email. Formats are derived from the OAS3 specification, but supplemented with formats that are generally needed in governmental applications (like BSN) . Current available formats are:


General formats

| Format | Type(s) | Description |
|--------|---------|-------------|
|alnum|         |Validates whether the input is alphanumeric or not. Alphanumeric is a combination of alphabetic and numeric characters|
|alpha|         |Validates whether the input contains only alphabetic characters|
|numeric|         |Validates whether the input contains only numeric characters|
|uuid|string||
|base| 	|Validate numbers in any base, even with non reqular bases.| 
|base64|	| Validate if a string is Base64-encoded.|
|countryCode|string|Validates whether the input is a country code in ISO 3166-1 standard.|
|creditCard|string|Validates a credit card number.|
|currencyCode|string|Validates an ISO 4217 currency code like GBP or EUR.|
|digit|string|Validates whether the input contains only digits.|
|directory|string|Validates if the given path is a directory.|
|domain|string|Validates whether the input is a valid domain name or not.|
|url|string|Validates whether the input is a valid url or not.|
|email|string|Validates an email address.|
|phone|string|Validates an phone number.|
|fibonacci|integer|Validates whether the input follows the Fibonacci integer sequence.|
|file|string|Validates whether file input is as a reqular filename.|
|hexRgbColor|string|Validates whether the input is a hex RGB color or not.|
|iban|string|Validates whether the input is a valid IBAN (International Bank Account Number) or not.|
|imei|string|Validates if the input is a valid IMEI.|
|ip|string|Validates whether the input is a valid IP address.|
|isbn|string|Validates whether the input is a valid ISBN or not.|
|json|string|Validates if the given input is a valid JSON.|
|xml|string|Validates if the given input is a valid XML.|
|languageCode|string|Validates whether the input is language code based on ISO 639.|
|luhn|string|Validate whether a given input is a Luhn number.|
|macAddress|string|Validates whether the input is a valid MAC address.|
|nfeAccessKey|string|Validates the access key of the Brazilian electronic invoice (NFe).|

* Phone numbers should ALWAY be treated as a string since they MAY contain a leading zero.

*Country specific formats*

| Format | Type(s) | Description |
|--------|---------|-------------|
|bsn|string|Dutch social security number (BSN)|
|nip|string, integer|Polish VAT identification number (NIP)|
|nif|string, integer|Spanish fiscal identification number (NIF)| 
|cnh|string, integer|Brazilian driver’s licence|
|cpf|string, integer| Validates a Brazillian CPF number |
|cnpj|string, integer|Validates if the input is a Brazilian National Reqistry of Legal Entities (CNPJ) number | 

* Dutch BSN numbers should ALWAY be treated as a string since they MAY contain a leading zero.


####Validations
Besides validations on type and string you can also use specific validations, these are contained in the validation array. Validation might be specific to certain types or formats e.g. minValue can only be applied values that can be turned into numeric value. And other validations might be of a more general nature e.g. required.



| Validation | value | Description |
|--------|---------|-------------|
|between| |Validates whether the input is between two other values.|
|boolType| |Validates whether the type of the input is boolean.|
|boolVal| |Validates if the input results in a boolean value.|
|call| |Validates the return of a [callable][] for a given input.|
|callableType| |Validates whether the pseudo-type of the input is callable.|
|callback| |Validates the input using the return of a given callable.|
|charset| |Validates if a string is in a specific charset.|
|alwaysInvalid| | Validates any input as invalid|
|alwaysValid| | Validates any input as valid|
|anyOf| | This is a group validator that acts as an OR operator. AnyOf returns true if at least one inner validator passes.|
|arrayType| | Validates whether the type of an input is array|
|arrayVal| |Validates if the input is an array or if the input can be used as an array (instance of ArrayAcces or SimpleXMLElement.|
|attribute| | Validates an object attribute, even private ones.|
|consonant| | Validates if the input contains only consonants.|
|contains| | Validates if the input contains some value. |
|containsAny| | Validates if the input contains at least one of defined values.|
|control| | Validates if all of the characters in the provided string, are control characters. |
|countable| | Validates if the input is countable, in other words, if you’re allowed to use count() funtion on it. |
|decimal| | Validates whether the input matches the expected number or decimals.|
|each| | Validates whether each value in the input is valid according to another rule.|
|endsWith| | This validator is similar to Contains(), but validates only if the value is at the end of the input.|
|equals| | Validates if the input is equal to some value.|
|equivalent| | Validates if the input is equivalent to some value.|
|even| |  Validates whether the input is an even number or not.|
|executable| |Validates if a file is an executable.|
|exists| | Validates files or directories.|
|extension| | Validates if the file extension matches the expected one. This rule is case-sensitive.|
|factor| | Validates if the input is a factor of the defined dividend.|
|falseVal| | Validates if a value is considered as false.|
|file| | Validates whether file input is as a reqular filename.|
|image| | Validates if the file is a valid image by checking its MIME type.|
|filterVar| | Validates the input with the PHP’s filter_var() function.|
|finite| | Validates if the input is a firtine number.|
|floatType| | Validates whether the type of the input is float.|
|floatVal| |Validate whether the input value is float.|
|graph| | Validates if all characters in the input are printable and actually creates visible output (no white space).|
|greaterThen| | Validates whether the input is greater than a value.|
|identical| | Validates if the input is identical to some value.|
|in| | Validates if the input is contained in a specific haystack. |
|infinite | | Validates if the input is an infinite number.|
|instance| | Validates if the input is an instance of the given class or interface.|
|iterableType| | Validates whether the pseudo-type of the input is iterable or not, in other words, if you're able to iterate over it with foreach language construct.|
|key| | Validates an array key.|
|keyNested| | Validates an array key or an object property using . to represent nested data.|
|keySet| | Validates a keys in a defined structure.|
|keyValue| | |
|leapDate| |Validates if a date is leap.|
|leapYear| |Validates if a year is leap.|
|length| | |
|lessThan| |Validates whether the input is less than a value.|
|lowercase | |Validates whether the characters in the input are lowercase.|
|not| | |
|notBlank| |Validates if the given input is not a blank value (null, zeros, empty strings or empty arrays, recursively).|
|notEmoji| |Validates if the input does not contain an emoji.|
|no| |Validates if value is considered as “No”.|
|noWhitespace| |Validates if a string contains no whitespace (spaces, tabs and line breaks).|
|noneOf| |Validates if NONE of the given validators validate.|
|max| |Validates whether the input is less than or equal to a value.|
|maxAge| |Validates a maximum age for a given date. The $format argument should be in accordance to PHP's date() function.|
|mimetype| |Validates if the input is a file and if its MIME type matches the expected one.|
|min| |Validates whether the input is greater than or equal to a value.|
|minAge| |Validates a minimum age for a given date. The $format argument should be in accordance to PHP's date() function.|
|multiple| |Validates if the input is a multiple of the given parameter.|
|negative| |Validates whether the input is a negative number.|

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

##Uploading files through the gateway
The gateway supports the uploading of files as part of an object, to add file uploads add a attribute of the type file to the entity that you want to upload to. It is strongly advised to to only use ONE attribute of the type file per entity but more are allowed (this will however prevent you frontend from using mulit-part form uploads and force base64 encoding of files on the frontend, besides being more complex this will also eat into you maximum memory use within the browser, commonly 10MB).

When creating an attribute of the type file you can maximize size, restrict mime types and designate if you are expecting one or multiple files.

When this is done it is possible to make multipart posts to the specific endpoint representing the entity you just created (to test this in postman go to the body tab and set the body type to form-data). This will then

When posting to an endpoint containing file type attributes trough form-data the first available attribute of the type file will be used to process the incoming files, so if you are including multiple files they are all stored under the same attribute and that attribute must be set to multiple. If you want multiple attributes containing files (for example al lists of diploma and an employee picture) consider using sub-entities (with there own endpoints) or making an api based request (like json).

You can also post files in a json like manner by either supling a base encoded string or and array containing the beast encode string and an name, e.g.

```json
{
	….
"logo":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=="
}
```
or
```json
{
	….
"logo":{
“name”:”my logo”,
“base64”: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=="
}
```
When working with an attribute that allows multiple you MY put either of the above methods into an array (not doing so will just create a multiple of one) and you MAY mix them (using the plain string and object option in the same array), e.g.

```json
{
	….
"logos":[
"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==",
{
“name”:”my logo”,
“data”: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=="}
}

Files will be translated to file objects by the gateway, and returned as such. By default the base encoded file is not returned but instead a download link is provided. This is preferable from both an performance (preventing large return objects) as a safety (logging file 
downloads) point of view e.g.

```json
{
	….
"logo":{
“name”:”my logo”,
“extension”:”png”,
“mimeType”:”image/png”,
“size”:”124065”,
“url”: "/api/downloads/2dee1608-2cdd-11ec-8d3d-0242ac130003"
}
}
```

Files MAY be passed on to un underlaying register or DMS system depending on the gateway settings.


## Easy configuration

### Dashboard
gjgh
### API
fgfhfgh
###Y AML
sdfs

## Plugins
The gateway aims at providing al functionality for normal the normal integration and distribution of data trough pre made functionality that can be configured to your wishes. It is  however impossible to catch the world in coding an we don’t aim to do that.

If  you require functionality for a specific project or integration that is simply not provided by the gateway you can ad it in the form of a plugin. A plugin is a separate bit of coding run within an gateway instance.
### Setting up your plugin
The gateway  runs on the symphony flex framework for bundles, as such al plugins should be valid symphony bundles. The gateway then discovers these bundles trough the publiccode standard (so a publiccode.yamml should be included in the plugin code) and the gateway loads these bunles trough ackagist (so they should be registerd there). Oke that a bit much to digest so lets take that stap by step.
1.	**Setting up your plugin as a bundle ** This is surprisingly the easiest part, we provide a template bundle at the gateway gitub org. Simply hop over to [this]() repository and follow the instructions.
2.	** Setting up your public code file ** Interestingly enough this is already included in the template repository you just used and included at root level. Take a moment to look into the publiccode file and see if you want to change any information.
3.	** Registering to packagist ** Again this is easier then it sounds, head over to [packadgist.org]( https://packagist.org/packages/submit) login and submit  your freshly created repository.
### Adding the functionality

Now that your plugin is  ready to use its time to add functionality. Gateway plugins provide functionality by hooking into the gateway’s [event system]() and then performing specific action’s (like altering data or sending a mail). They do this trough handlers, a handler is a bit of code that handles an event send by the event system. An example of this would be sending an email every time a object is stored in the [datalayer]().
Each handler should be a php file containing a class. Implementing the ActionHandlerInterface and providing at least the functions.

```php
<?php

namespace Acme\EmailBundle;

use App\Exception\GatewayException;
use Psr\Container\ContainerInterface;
use App\Service\EmailService;

class EmailHandler implements ActionHandlerInterface
{
    // Declare private functions
    private EmailService $emailService;
     
    /**
    * The constructor is a magic method that is called when the class is declared, it is genneraly used to get an service from the container inteface
    *   
    * @param ContainerInterface $container
    */
    public function __construct(ContainerInterface $container)
    {
        // No return required
    }
    
    /**
     *  This function returns the event types that are suported by this handler
     *
     * @return array a list of event types
     */
    public function getEvents(): array
    {
	    return [];
    }


    /**
     *  This function returns the configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
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
     *     
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
	    return $data;
    }
}
```

Oke so lets look at these functions one at a time. At the top of our code we find our namespacing.  Namespacing is an important part of design MVC frameworks on object orianteded languages. For Gatewat plugins we follow the symfont bundle standard to ensure the namespaces are unique and wont conflict between plugins. The namespace should therfore always be `[organisationsname]\[pluginname]Bundle`. Like: 

```php
namespace Acme\EmailBundle;
```

Then the constuct function. We use it to makes services available as privete variables within our class. We will get into services when we look at the `__run()`function but for now we should rememebr that it is good practise to defer busnes logic code to services. 

*The `__construct()` function **MUST** be present and **MAY** set services for later use.*

```php

    private EmailService $emailService;

    /**
    * The constructor is a magic method that is called when the class is declared, it is genneraly used to get an service from the container inteface
    *   
    * @param ContainerInterface $container
    */
    public function __construct(ContainerInterface $container)
    {
        $emailService = $container->get('mailService');
        if ($emailService instanceof EmailService) {
            $this->emailService = $emailService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }
```

The above example would also require us to `use` (the php version of importing) the apropriate classes. We can do that at the top of our class like

```php
use App\Exception\GatewayException;
use Psr\Container\ContainerInterface;
use App\Service\EmailService;
```

The getEvents function tells the gateway what event types are supported by this handles, that is important becouse difrend event types present diffrend kinds of data to handles. It is therefore unlikely that one handler can deal with all event types (unless that handles dosn't use data in the first  place). If an Handlers wisches to support all functions it may return an empty array. You can find a list of all available events and the data that they provide under [events](#events). The events will both be used tot provide users with options when selecting adding actions and are used as a vilidation when events are registerd.

*The `getEvents()` function **MUST** be present and **MUST** return an array. The array **MAY** be empty, but if it is not all values **MUST** be valid events.* 
```php
    /**
     *  This function returns the event types that are suported by this handler
     *
     * @throws array a list of event types
     */
    public function getEvents(): array
    {
	    return ['commongateway.handler.pre', 'commongateway.handler.post'];
    }
```

In the configuration array a handler tells the gateway wich configuration options it has and how they should be presented to a user. This information is then used by the gateway to render both userforms and validation 

*The `getConfiguration()` function **MUST** be present and **MUST** return an array. The array **MAY** be empty, but if it is not its **MUST** contain a valid [json-schema](https://json-schema.org/).*
```php
    /**
     *  This function returns the configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
	    return [
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Sends a email for mailbox to another mailbox',
            'required'   => [ 'template', 'sender', 'reciever'],
            'properties' => [
                'template' => [
                    'type'        => 'string',
                    'description' => 'The actual email template, should be a base64 encoded twig template',
                    'example'     => 'eyMgdG9kbzogbW92ZSB0aGlzIHRvIGFuIGVtYWlsIHBsdWdpbiAoc2VlIEVtYWlsU2VydmljZS5waHApICN9CjwhRE9DVFlQRSBodG1sIFBVQkxJQyAiLS8vVzNDLy9EVEQgWEhUTUwgMS4wIFRyYW5zaXRpb25hbC8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9UUi94aHRtbDEvRFREL3hodG1sMS10cmFuc2l0aW9uYWwuZHRkIj4KPGh0bWwgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGh0bWwiPgo8aGVhZD4KICA8bWV0YSBuYW1lPSJ2aWV3cG9ydCIgY29udGVudD0id2lkdGg9ZGV2aWNlLXdpZHRoLCBpbml0aWFsLXNjYWxlPTEuMCIgLz4KICA8bWV0YSBodHRwLWVxdWl2PSJDb250ZW50LVR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD1VVEYtOCIgLz4KICA8dGl0bGU+e3sgc3ViamVjdCB9fTwvdGl0bGU+CgogIDxsaW5rIHJlbD0icHJlY29ubmVjdCIgaHJlZj0iaHR0cHM6Ly9mb250cy5nc3RhdGljLmNvbSIgLz4KICA8bGluawogICAgICAgICAgaHJlZj0iaHR0cHM6Ly9mb250cy5nb29nbGVhcGlzLmNvbS9jc3MyP2ZhbWlseT1GYXVzdGluYTp3Z2h0QDYwMCZkaXNwbGF5PXN3YXAiCiAgICAgICAgICByZWw9InN0eWxlc2hlZXQiCiAgLz4KICA8bGluawogICAgICAgICAgaHJlZj0iaHR0cHM6Ly9mb250cy5nb29nbGVhcGlzLmNvbS9jc3MyP2ZhbWlseT1Tb3VyY2UrU2FucytQcm8mZGlzcGxheT1zd2FwIgogICAgICAgICAgcmVsPSJzdHlsZXNoZWV0IgogIC8+CgogIDxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyIgcmVsPSJzdHlsZXNoZWV0IiBtZWRpYT0iYWxsIj4KICAgIC8qIEJhc2UgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgYm9keSB7CiAgICAgIHdpZHRoOiAxMDAlICFpbXBvcnRhbnQ7CiAgICAgIGhlaWdodDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGluZS1oZWlnaHQ6IDEuNDsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmZmZmZjsKICAgICAgY29sb3I6ICM3NDc4N2U7CiAgICAgIC13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogbm9uZTsKICAgIH0KCiAgICBwLAogICAgdWwsCiAgICBvbCwKICAgIGJsb2NrcXVvdGUgewogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGluZS1oZWlnaHQ6IDEuNDsKICAgICAgdGV4dC1hbGlnbjogbGVmdDsKICAgIH0KCiAgICBhIHsKICAgICAgY29sb3I6ICMxZDU1ZmY7CiAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsKICAgIH0KCiAgICBhOmhvdmVyIHsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiB1bmRlcmxpbmU7CiAgICB9CgogICAgcCBhIHsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiB1bmRlcmxpbmU7CiAgICB9CgogICAgYSBpbWcgewogICAgICBib3JkZXI6IG5vbmU7CiAgICB9CgogICAgdGQgewogICAgICB3b3JkLWJyZWFrOiBicmVhay13b3JkOwogICAgfQogICAgLyogTGF5b3V0IC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIC5oZWFkZXIgewogICAgICBiYWNrZ3JvdW5kOiAjMWQ1NWZmOwogICAgICB3aWR0aDogMTAwJTsKICAgICAgaGVpZ2h0OiAyMzZweDsKICAgICAgYmFja2dyb3VuZC1yZXBlYXQ6IG5vLXJlcGVhdDsKICAgICAgYmFja2dyb3VuZC1wb3NpdGlvbjogY2VudGVyOwogICAgfQoKICAgIC5oZWFkZXItY2VsbCB7CiAgICAgIHBhZGRpbmc6IDE2cHggMjRweDsKICAgIH0KCiAgICAuZW1haWwtd3JhcHBlciB7CiAgICAgIHdpZHRoOiAxMDAlOwogICAgICBtYXJnaW46IDA7CiAgICAgIHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItd2lkdGg6IDEwMCU7CiAgICAgIC1wcmVtYWlsZXItY2VsbHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItY2VsbHNwYWNpbmc6IDA7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmZmZmY7CiAgICB9CgogICAgLmVtYWlsLWNvbnRlbnQgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgfQogICAgLyogTWFzdGhlYWQgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KCiAgICAuZW1haWwtbWFzdGhlYWQgewogICAgICBwYWRkaW5nOiAyNXB4IDA7CiAgICAgIHRleHQtYWxpZ246IGNlbnRlcjsKICAgIH0KCiAgICAuZW1haWwtbWFzdGhlYWRfbG9nbyB7CiAgICAgIHdpZHRoOiA5NHB4OwogICAgfQoKICAgIC5lbWFpbC1tYXN0aGVhZF9uYW1lIHsKICAgICAgZm9udC1zaXplOiAxNnB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgICBjb2xvcjogI2JiYmZjMzsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiBub25lOwogICAgICB0ZXh0LXNoYWRvdzogMCAxcHggMCB3aGl0ZTsKICAgIH0KICAgIC8qIEJvZHkgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgLmVtYWlsLWJvZHkgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgICBiYWNrZ3JvdW5kOiBub25lOwogICAgfQoKICAgIC5lbWFpbC1ib2R5X2lubmVyIHsKICAgICAgd2lkdGg6IDY0MHB4OwogICAgICBtYXJnaW46IDAgYXV0bzsKICAgICAgcGFkZGluZzogMDsKICAgICAgLXByZW1haWxlci13aWR0aDogNTcwcHg7CiAgICAgIC1wcmVtYWlsZXItY2VsbHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItY2VsbHNwYWNpbmc6IDA7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmZmZmY7CiAgICB9CgogICAgLmVtYWlsLWZvb3RlciB7CiAgICAgIHdpZHRoOiA2NDBweDsKICAgICAgbWFyZ2luOiAwIGF1dG87CiAgICAgIHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItd2lkdGg6IDU3MHB4OwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7CiAgICB9CgogICAgLmVtYWlsLWZvb3RlciBwIHsKICAgICAgY29sb3I6ICNhZWFlYWU7CiAgICB9CgogICAgLmJvZHktYWN0aW9uIHsKICAgICAgd2lkdGg6IDEwMCU7CiAgICAgIG1hcmdpbjogNDBweCBhdXRvOwogICAgICBwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7CiAgICB9CgogICAgLmJvZHktc3ViIHsKICAgICAgbWFyZ2luLXRvcDogMjVweDsKICAgICAgcGFkZGluZy10b3A6IDI1cHg7CiAgICAgIGJvcmRlci10b3A6IDFweCBzb2xpZCAjZWRlZmYyOwogICAgfQoKICAgIC5jb250ZW50LWNlbGwgewogICAgICBwYWRkaW5nOiAzNnB4IDE2cHg7CiAgICB9CgogICAgLnByZWhlYWRlciB7CiAgICAgIGRpc3BsYXk6IG5vbmUgIWltcG9ydGFudDsKICAgICAgdmlzaWJpbGl0eTogaGlkZGVuOwogICAgICBtc28taGlkZTogYWxsOwogICAgICBmb250LXNpemU6IDFweDsKICAgICAgbXNvLWxpbmUtaGVpZ2h0LXJ1bGU6IGV4YWN0bHk7CiAgICAgIGxpbmUtaGVpZ2h0OiAxcHg7CiAgICAgIG1heC1oZWlnaHQ6IDA7CiAgICAgIG1heC13aWR0aDogMDsKICAgICAgb3BhY2l0eTogMDsKICAgICAgb3ZlcmZsb3c6IGhpZGRlbjsKICAgIH0KICAgIC8qIEF0dHJpYnV0ZSBsaXN0IC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIC5hdHRyaWJ1dGVzIHsKICAgICAgbWFyZ2luOiAwIDAgMjFweDsKICAgIH0KCiAgICAuYXR0cmlidXRlc19jb250ZW50IHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2VkZWZmMjsKICAgICAgcGFkZGluZzogMTZweDsKICAgIH0KCiAgICAuYXR0cmlidXRlc19pdGVtIHsKICAgICAgcGFkZGluZzogMDsKICAgIH0KICAgIC8qIFJlbGF0ZWQgSXRlbXMgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgLnJlbGF0ZWQgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAyNXB4IDAgMCAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgfQoKICAgIC5yZWxhdGVkX2l0ZW0gewogICAgICBwYWRkaW5nOiAxMHB4IDA7CiAgICAgIGNvbG9yOiAjNzQ3ODdlOwogICAgICBmb250LXNpemU6IDE1cHg7CiAgICAgIG1zby1saW5lLWhlaWdodC1ydWxlOiBleGFjdGx5OwogICAgICBsaW5lLWhlaWdodDogMThweDsKICAgIH0KCiAgICAucmVsYXRlZF9pdGVtLXRpdGxlIHsKICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgIG1hcmdpbjogMC41ZW0gMCAwOwogICAgfQoKICAgIC5yZWxhdGVkX2l0ZW0tdGh1bWIgewogICAgICBkaXNwbGF5OiBibG9jazsKICAgICAgcGFkZGluZy1ib3R0b206IDEwcHg7CiAgICB9CgogICAgLnJlbGF0ZWRfaGVhZGluZyB7CiAgICAgIGJvcmRlci10b3A6IDFweCBzb2xpZCAjZWRlZmYyOwogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7CiAgICAgIHBhZGRpbmc6IDI1cHggMCAxMHB4OwogICAgfQoKICAgIC8qIFV0aWxpdGllcyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KCiAgICAubm8tbWFyZ2luIHsKICAgICAgbWFyZ2luOiAwOwogICAgfQoKICAgIC5tYXJnaW4tdG9wIHsKICAgICAgbWFyZ2luLXRvcDogOHB4OwogICAgfQoKICAgIC5hbGlnbi1yaWdodCB7CiAgICAgIHRleHQtYWxpZ246IHJpZ2h0OwogICAgfQoKICAgIC5hbGlnbi1sZWZ0IHsKICAgICAgdGV4dC1hbGlnbjogbGVmdDsKICAgIH0KCiAgICAuYWxpZ24tY2VudGVyIHsKICAgICAgdGV4dC1hbGlnbjogY2VudGVyOwogICAgfQogICAgLypNZWRpYSBRdWVyaWVzIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIEBtZWRpYSBvbmx5IHNjcmVlbiBhbmQgKG1heC13aWR0aDogNjAwcHgpIHsKICAgICAgLmVtYWlsLWJvZHlfaW5uZXIsCiAgICAgIC5lbWFpbC1mb290ZXIgewogICAgICAgIHdpZHRoOiAxMDAlICFpbXBvcnRhbnQ7CiAgICAgIH0KICAgIH0KCiAgICBAbWVkaWEgb25seSBzY3JlZW4gYW5kIChtYXgtd2lkdGg6IDUwMHB4KSB7CiAgICAgIC5idXR0b24gewogICAgICAgIHdpZHRoOiAxMDAlICFpbXBvcnRhbnQ7CiAgICAgIH0KICAgIH0KCiAgICAvKiBDYXJkcyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KICAgIC5jYXJkIHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmZjsKICAgICAgYm9yZGVyLXRvcDogMXB4IHNvbGlkICNlMGUxZTU7CiAgICAgIGJvcmRlci1yaWdodDogMXB4IHNvbGlkICNlMGUxZTU7CiAgICAgIGJvcmRlci1ib3R0b206IDFweCBzb2xpZCAjZTBlMWU1OwogICAgICBib3JkZXItbGVmdDogMXB4IHNvbGlkICNlMGUxZTU7CiAgICAgIHBhZGRpbmc6IDI0cHg7CiAgICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgICAgY29sb3I6ICMzOTM5M2E7CiAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsKICAgICAgd2lkdGg6IDEwMCU7CiAgICAgIGJvcmRlci1yYWRpdXM6IDNweDsKICAgICAgYm94LXNoYWRvdzogMCA0cHggM3B4IC0zcHggcmdiYSgwLCAwLCAwLCAwLjA4KTsKICAgICAgLXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiBub25lOwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGluZS1oZWlnaHQ6IDEuNzU7CiAgICAgIGxldHRlci1zcGFjaW5nOiAwLjhweDsKICAgIH0KCiAgICAvKiBCdXR0b25zIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIC5idXR0b24gewogICAgICBiYWNrZ3JvdW5kLWNvbG9yOiAjMWRiNGVkOwogICAgICBib3JkZXItdG9wOiAxMHB4IHNvbGlkICMxZGI0ZWQ7CiAgICAgIGJvcmRlci1yaWdodDogMThweCBzb2xpZCAjMWRiNGVkOwogICAgICBib3JkZXItYm90dG9tOiAxMHB4IHNvbGlkICMxZGI0ZWQ7CiAgICAgIGJvcmRlci1sZWZ0OiAxOHB4IHNvbGlkICMxZGI0ZWQ7CiAgICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgICAgY29sb3I6ICNmZmY7CiAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsKICAgICAgYm9yZGVyLXJhZGl1czogNHB4OwogICAgICBib3gtc2hhZG93OiAwIDJweCAzcHggcmdiYSgwLCAwLCAwLCAwLjE2KTsKICAgICAgLXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiBub25lOwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgd2lkdGg6IDEwMCU7CiAgICAgIHRleHQtYWxpZ246IGNlbnRlcjsKICAgICAgZm9udC1zaXplOiAxNHB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgfQoKICAgIC5zbWFsbC1sb2dvIHsKICAgICAgd2lkdGg6IDI0cHg7CiAgICAgIGhlaWdodDogMjRweDsKICAgIH0KCiAgICAuaW5saW5lIHsKICAgICAgZGlzcGxheTogaW5saW5lOwogICAgfQogICAgLyogVHlwZSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KCiAgICBwIHsKICAgICAgbWFyZ2luOiAwOwogICAgICBjb2xvcjogIzM5MzkzYTsKICAgICAgZm9udC1zaXplOiAxNXB4OwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGV0dGVyLXNwYWNpbmc6IG5vcm1hbDsKICAgICAgdGV4dC1hbGlnbjogbGVmdDsKICAgICAgbGluZS1oZWlnaHQ6IDIwcHg7CiAgICB9CgogICAgcCArIHAgewogICAgICBtYXJnaW4tdG9wOiAyMHB4OwogICAgfQoKICAgIHAuc3VmZml4IHsKICAgICAgZm9udC1zaXplOiAxNHB4OwogICAgfQoKICAgIHAuc3ViIHsKICAgICAgZm9udC1zaXplOiAxMnB4OwogICAgfQoKICAgIHAuY2VudGVyIHsKICAgICAgdGV4dC1hbGlnbjogY2VudGVyOwogICAgfQoKICAgIC5zdWJ0bGUgewogICAgICBjb2xvcjogI2IxYjFiMTsKICAgIH0KCiAgICAvKiBGb290ZXIgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgLmxvZ28tbGFiZWwgewogICAgICB2ZXJ0aWNhbC1hbGlnbjogdG9wOwogICAgICBmb250LXNpemU6IDE0cHg7CiAgICAgIG1hcmdpbi1sZWZ0OiA0cHg7CiAgICB9CgogICAgLmZvb3Rlci1jZWxsIHsKICAgICAgcGFkZGluZzogOHB4IDI0cHg7CiAgICB9CgogICAgLmZvb3Rlci1uYXYgewogICAgICBtYXJnaW4tbGVmdDogOHB4OwogICAgICBmb250LXNpemU6IDE0cHg7CiAgICAgIGNvbG9yOiAjMzkzOTNhOwogICAgICB0ZXh0LWRlY29yYXRpb246IG5vbmU7CiAgICB9CgogICAgLmhlYWRlci1saW5rIHsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiBub25lOwogICAgICBmb250LXNpemU6IDE0cHg7CiAgICAgIGNvbG9yOiAjMWQ1NWZmOwogICAgICBmb250LXdlaWdodDogNTAwOwogICAgfQoKICAgIC5tYXJnaW4tdG9wIHsKICAgICAgbWFyZ2luLXRvcDogMTZweDsKICAgIH0KCiAgICAubG9nby1jb250YWluZXIgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luLWJvdHRvbTogNTZweDsKICAgIH0KCiAgICAubG9nbyB7CiAgICAgIGRpc3BsYXk6IGJsb2NrOwogICAgfQoKICAgIC8qIEN1c3RvbSBzdHlsZXMgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCiAgICBociB7CiAgICAgIGJvcmRlci10b3A6IDFweCBzb2xpZCAjZDlkOWRlOwogICAgICBjb2xvcjogI2Q5ZDlkZTsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2Q5ZDlkZTsKICAgICAgbWFyZ2luLXRvcDogMzJweDsKICAgICAgbWFyZ2luLWJvdHRvbTogNDBweDsKICAgIH0KCiAgICBoMSB7CiAgICAgIGZvbnQtZmFtaWx5OiAiRmF1c3RpbmEiLCBzZXJpZjsKICAgICAgZm9udC1zaXplOiAzMnB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgICBjb2xvcjogIzIzMjMyNjsKICAgICAgbWFyZ2luLWJvdHRvbTogMjJweDsKICAgIH0KCiAgICBwIHsKICAgICAgZm9udC1mYW1pbHk6ICJTb3VyY2UgU2FucyBQcm8iLCBzYW5zLXNlcmlmOwogICAgICBmb250LXNpemU6IDE4cHg7CiAgICAgIGxpbmUtaGVpZ2h0OiAxLjY7CiAgICAgIGNvbG9yOiAjMjMyMzI2OwogICAgfQoKICAgIC5idXR0b24gewogICAgICBmb250LWZhbWlseTogIlNvdXJjZSBTYW5zIFBybyIsIHNhbnMtc2VyaWY7CiAgICB9CgogICAgLmNvbnRlbnQtY2VsbCB7CiAgICAgIHBhZGRpbmc6IDQwcHggNDBweDsKICAgIH0KCiAgICAuYnV0dG9uIHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmNWEyNjsKICAgICAgYm9yZGVyLXRvcDogMTBweCBzb2xpZCAjZmY1YTI2OwogICAgICBib3JkZXItcmlnaHQ6IDE4cHggc29saWQgI2ZmNWEyNjsKICAgICAgYm9yZGVyLWJvdHRvbTogMTBweCBzb2xpZCAjZmY1YTI2OwogICAgICBib3JkZXItbGVmdDogMThweCBzb2xpZCAjZmY1YTI2OwogICAgICBkaXNwbGF5OiBpbmxpbmUtYmxvY2s7CiAgICAgIGNvbG9yOiAjZmZmOwogICAgICB3aWR0aDogYXV0bzsKICAgICAgYm94LXNoYWRvdzogbm9uZTsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiBub25lOwogICAgICBib3JkZXItcmFkaXVzOiA4cHg7CiAgICAgIC13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogbm9uZTsKICAgICAgbXNvLWxpbmUtaGVpZ2h0LXJ1bGU6IGV4YWN0bHk7CiAgICAgIHRleHQtYWxpZ246IGNlbnRlcjsKICAgICAgZm9udC1zaXplOiAxNHB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgfQogIDwvc3R5bGU+CjwvaGVhZD4KPGJvZHk+Cjx0YWJsZSBjbGFzcz0iZW1haWwtd3JhcHBlciIgd2lkdGg9IjEwMCUiIGNlbGxwYWRkaW5nPSIwIiBjZWxsc3BhY2luZz0iMCI+CiAgPHRyPgogICAgPHRkIGFsaWduPSJjZW50ZXIiPgogICAgICA8dGFibGUKICAgICAgICAgICAgICBjbGFzcz0iZW1haWwtY29udGVudCIKICAgICAgICAgICAgICB3aWR0aD0iMTAwJSIKICAgICAgICAgICAgICBjZWxscGFkZGluZz0iMCIKICAgICAgICAgICAgICBjZWxsc3BhY2luZz0iMCIKICAgICAgPgogICAgICAgIDx0cj4KICAgICAgICAgIDx0ZCBjbGFzcz0iZW1haWwtbWFzdGhlYWQiPjwvdGQ+CiAgICAgICAgPC90cj4KICAgICAgICA8IS0tIEVtYWlsIEJvZHkgLS0+CiAgICAgICAgPHRyPgogICAgICAgICAgPHRkCiAgICAgICAgICAgICAgICAgIGNsYXNzPSJlbWFpbC1ib2R5IgogICAgICAgICAgICAgICAgICB3aWR0aD0iMTAwJSIKICAgICAgICAgICAgICAgICAgY2VsbHBhZGRpbmc9IjAiCiAgICAgICAgICAgICAgICAgIGNlbGxzcGFjaW5nPSIwIgogICAgICAgICAgPgogICAgICAgICAgICA8dGFibGUKICAgICAgICAgICAgICAgICAgICBjbGFzcz0iZW1haWwtYm9keV9pbm5lciIKICAgICAgICAgICAgICAgICAgICBhbGlnbj0iY2VudGVyIgogICAgICAgICAgICAgICAgICAgIHdpZHRoPSIxMDAlIgogICAgICAgICAgICAgICAgICAgIGJhY2tncm91bmQtY29sb3I9IiNlZGVmZjIiCiAgICAgICAgICAgICAgICAgICAgY2VsbHBhZGRpbmc9IjAiCiAgICAgICAgICAgICAgICAgICAgY2VsbHNwYWNpbmc9IjAiCiAgICAgICAgICAgID4KICAgICAgICAgICAgICA8IS0tIEJvZHkgY29udGVudCAtLT4KICAgICAgICAgICAgICA8dHI+PC90cj4KICAgICAgICAgICAgICA8dHI+CiAgICAgICAgICAgICAgICA8dGQgY2xhc3M9ImNvbnRlbnQtY2VsbCIgd2lkdGg9IjEwMCUiPgogICAgICAgICAgICAgICAgICA8cD4KICAgICAgICAgICAgICAgICAgICAgIHt7IGF1dGhvciB9fSBoZWVmdCBmZWVkYmFjayBnZWdldmVuIG9wOiB7eyB0b3BpYyB9fQogICAgICAgICAgICAgICAgICA8L3A+CiAgICAgICAgICAgICAgICAgIDxici8+CiAgICAgICAgICAgICAgICAgIDxwPgogICAgICAgICAgICAgICAgICAgICAge3sgZGVzY3JpcHRpb24gfCBubDJiciB9fQogICAgICAgICAgICAgICAgICA8L3A+CiAgICAgICAgICAgICAgICAgIDxociAvPgogICAgICAgICAgICAgICAgICA8cD5NZXQgdnJpZW5kZWxpamtlIGdyb2V0LDwvcD4KICAgICAgICAgICAgICAgICAgPHA+S0lTUzwvcD4KICAgICAgICAgICAgICAgIDwvdGQ+CiAgICAgICAgICAgICAgPC90cj4KICAgICAgICAgICAgICA8dHI+PC90cj4KICAgICAgICAgICAgPC90YWJsZT4KICAgICAgICAgIDwvdGQ+CiAgICAgICAgPC90cj4KICAgICAgPC90YWJsZT4KICAgIDwvdGQ+CiAgPC90cj4KPC90YWJsZT4KPC9ib2R5Pgo8L2h0bWw+Cg==',
                ],
                'sender' => [
                    'type'        => 'string',
                    'description' => 'The sender of the email',
                    'example'     => 'info@conduction.nl',
                ],
                'receiver' => [
                    'type'        => 'string',
                    'description' => 'The receiver of the email',
                    'example'     => 'j.do@conduction.nl',
                ],
            ],
        ];
    }
```
The run function is the function that is actually called by the event manager when an action is triggerd. It recievec the current data state and the configuration of the action that triggerd it. You can read more about creating services [here](https://symfonycasts.com/screencast/symfony-fundamentals/create-service).

*The `__run()` function **MUST** be present and **MUST** return an array. The function **MAY** contain busnes logic but **SHOULD** defer busneslogic to a service.*
```php
    /**
     * This function runs the email service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->emailService->emailHandler($data, $configuration);
    }
```
### Listing available Handlers
In order to register actions for handlers the gateway needs to know what handlers are available. Making handlers known to the gateway is done trough the yaml [configuration files](https://symfony.com/doc/current/configuration.html) of symfony. Becouse the gatway uses the default symfony bundle system the configuration files can just be listed in your bundle in a `resources`. This makes it possible to extend the default gateway configuration from you bundle and add your handler to the handelers array like this:

```php
# config/packages/api_platform.yaml
common_gateway:
    handlers:
        - 'Acme\EmailBundle\EmailHandler'
```

### Adding plugins to your gateway
There are several options how you can add pluging to your gateway, depending on if you run your gateway locally, online or within a cloud.
1. Trough the commandline
2. Through packadgelist
3. Through fixtures
4. Toruh Intreface
1 T

## Dataservice
The dataservice is the main servers used by the gateway to obtain an alter data from either is internal datalake, external sources or session specific attributes. When dealing with data this function **MUST** be used. This is especially trough  because the event driven nature of the gateway means that underlying data could change during the course of an event. That in turn means that normal symfony functions might present false data. And using them in your  code makes theme error prone.
The most dominant usecase for the data service is as part of the mapping of objects (all functions within the data service are available tough the twig function data service. The primary data services are:

### Data
{{Table}}

### Mapping
@todo mapping opbject aanmaken, mapping cast aanmaken, endpointHandler aanmaken, proxyHandler aanmaken.

### Session

### Request

### Datalayer

