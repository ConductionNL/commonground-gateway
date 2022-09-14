# Features

___
The web gateway is designed as a feature-rich solution to quickly develop progressive web applications.

## Sources

Sources form the beating heart of the gateway. A source represents an external API (either registered or microservice in nature) that can be exposed through the web gateway. Adding an API as a source WILL NOT cause that API to be exposed. You can add APIs manually or through discovery. Discovery methods currently on the roadmap are NLX, Autorisatie Component, and Generic Kubernetes services.

`Properties`  
*name*:
*description*

## Authentication

The web gateway provides several out-of-the-box authentication mechanisms to lower the dependency on external services. These mechanisms include DigiD and ADFS and come with local and online mock environments. This offers a straightforward flow for setting up applications focused on governmental services.

## Authorisation

The web gateway can provide an authorization scheme or defer to other components like ADFS or the user component.

## Multi-application

It is possible to split authorization based on application profiles, providing a basic framework for running multiple applications from the same gateway.

## Multi-tenancy

The web gateway provides multi-tenancy by separating all the data it holds (configuration and actual usage) along the lines of organizational IDs. This feature is hard-coded into database queries and provides a safe way of running a multi-tenancy setup from a single installation. If you, however, prefer a separation of databases on a tenant base, we advise you to use a multi-installation setup.

## NL Design System Ready

The web gateway provides an easy (and inline scripting-save) way of providing an application with NL Design System token files based on the organization currently served (see multi-tenancy). This allows applications to easily implement NL Design by using NL Design tokens (see a complete list here). The only action an application need is to include the following line before loading other CCS files `/api/organization/{id}/nl-design-tokens.css`. Keep in mind that from a security perspective, the API needs to be provided from the same domain as the actual application.

## Haven ready

ad

## GDPR compliance and logging

The web gateway supports logging all data requests and their ‘doelbinding’ to a GDPR registry (in the case of Dutch governmental agencies like the VNG doelbindings register). Be aware that activating this functionality can lead to double registrations in the said registry if the receiving API is also already logging all requests. Please check the documentation of the API you are connecting before activating this functionality.

## Notifications and event driven

The web gateway can send notifications on its behalf when receiving and processing requests (both on successful and unsuccessful results) and trigger event-driven applications (like a business engine). It can also trigger events on behalf of other APIs, thereby providing event-driven architecture to ecosystems that generally do not support such a scheme. In both cases, the gateway requires a notification API to be present under its sources and configurations for other sources or entities that  use this API.

## Smart caching

The web gateway relies on smart caching to serve required data quickly. Smart caching takes active subscriptions on any resources that it caches. For instance, caching a case from ZGW will take a `notificatie` subscription. Any time the case is changed, the web gateway will drop it from its cache and (if cache warmup is activated) replace it with a new copy). This simple process means that any API that supports the `notificatie` or `berichten standaard` will be served to the client lightning fast. Smart caching also allows the gateway to share cache between applications (but not organisations) to speed the process up even more.

## Field limiting

The web gateway supports limiting the returned data using the field's query parameter. When supplied, the gateway will only return the requested attributes (even if the entity contains more attributes) and will only log the requested fields in the GDPR registry. Fields can be supplied as comma separated or in an array as in `?fields=field1,field2,field2` or `?fields[]=field1&fields[]=field2`.  

Dealing with sub-entities, listing the attribute containing the entity will return the complete entity. When only specific fields are required, field inputs accept dot notation to filter those, e.g., `?fields[]field1.subfield1`. Field limitation supports a field depth of 5.

## Entity extension

Occasionally you might encounter a linked data URI to a different API in a Common Ground registry (ZGW APIs are notorious examples). If sufficient rights are provided**,  you can use the gateway to include the linked data object in the result as if it were one result (this provides worse performance than just setting up the attribute properly). To do this, use the `extend` query parameters, e.g. `?extend=field1` or the dot notation, e.g., `?extend=field1.subfield1`.  

The gateway will try to use smart caching to deliver the desired sub-entity quickly, but this will require additional internal calls or an external API call, at worst—severely impacting performance.

** the references API is a valid gateway source, and the user/application combination has the right to an entity matching the URI’s endpoint

## Smart search

The web gateway allows searching on any attribute marked as  ‘searchable’ even if the original API doesn’t allow searching on that specific attribute or property. It also supports searching sub-entities by dot annotation, e.g., `?field1.subfield1=searchme`. All searchable fields of an endpoint are collected automatically for fuzzy search. The search query parameter allows you also to use `?search=searchme`. Be aware, though, that this comes with a severe performance drain and that specific searches are almost always preferable. The wildcard search only supports string type attributes.

## Data formats

The web gateway defaults to JSON data format but supports other data formats. The data format determines how the data is provided to the consuming application. It is possible to have the gateway consume a JSON API but present its results as XML to a web application. The data format must be set on each call through the Accept header. The gateway currently supports the following Accept headers and corresponding formats.

`text/csv`,  
`text/yaml`,  
`application/json`,  
`application/ld+json`,
`application/json+ld`,  
`application/hal+json`,  
`application/json+hal`,  
`application/xml`

## Pagination

The web gateway supports two different approaches to pagination, allowing the developer to choose what fits best. When making a GET call to a collection endpoint, e.g., `/pet instead of /pet/{petId}`, the result will always be an array of objects in the results property of the response (even if the array has zero items).

Additionally, a `start`(default 1), `limit` (default 25), `page`, `pages`, and total properties will be returned, allowing you to slice the result any way you want. Use the start and limit properties for basic and the page end limit property (pagination) for advanced slicing. Let's assume that you have a collection containing 125 results. A basic GET would then get you something like:

```JSON
{
 "results": [],
 "total":125,
 "start":1,
 "limit":25,
 "page":1,
 "pages":5
}
```

Let's say you are displaying the data in a table and want more results to begin with, so you set the limit to `?limit=100`, the expected result would be

```JSON
{
 "results": [],
 "total":125,
 "start":1,
 "limit":100,
 "page":1,
 "pages":2
}
```

You can now provide pagination buttons to your users based directly on the pages property, let's say you make a button for each page, and the user presses page 2 (or next) your following call will then be `?limit=100&page=2` and the result

```JSON
{
 "results": [],
 "total":125,
 "start":101,
 "limit":100,
 "page":2,
 "pages":2
}
```

Alternatively, you could query `?limit=100&start=101` for the same result

## Exporting files

A recommended download option for exporting a file containing a data set is through the web gateway for GDPR reasons(logging the data transfer in the registry). For this reason, all the collection endpoints support returning downloadable files besides JSON objects. Set the ‘accept’ head to the preferred file format and initiate a download. We currently support `text/csv`,`application/ms-excel`,`application/pdf`.  

Remember that you can use the field, extend, and pagination query functionality to influence the result/data set.

## Entities and Attributes

Object storage and persistence to other APIs is handled through an EAV model. Any object from an underlying source can be made available through a mapping. This mapping is necessary if you don't want an API object's properties to be externally available through an AJAX request, or you might want to provide additional validation or staked objects through the API.

An underlying API will generally provide `endpoints` containing (collections of) `objects`. In the case of the Swagger pet store example, the `/pet` endpoint contains a list of pets in a results array, and the  `pet/{petId}` contains a single pet `object` identified by id.

A pet object looks something like this

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

So what does that mean for EAV mapping? Our objects become entities, and our properties become attributes. In this case, we will have an entity called `Pet` with the following attributes:

 `category`, `name`, `photoUrls`, `tags`, and `status`.

Both entities and attributes can contain additional configuration, meaning they can do MORE than the original API object but never less. For example, if the pet object requires you to provide a name for a pet, you might ignore that requirement on the name attribute, but that will cause the underlying API to throw an error any time you try to process a pet without a name.

### Entity

Entities represent objects you want to communicate to underlaying sources. To do this, they require a source and an endpoint on that source to send the object to. You may choose to ignore this. And just 'store' your objects with the gateway, this might be an excellent way to mock API's in development environments, but it's not recommended for production environments.

#### Entity -Properties

An entity consists of the following configurable properties

| Property | Required | Description |
|--------|-------------| -------------|
|name| yes |An unique name for this entity|
|description| no | The description for this entity that is shown in de API documentation|
|source| no |The source where this entity resides|
|endpoint| yes | if a source is provided | The endpoint within the source that this entity should be posted to as an object|
|route| no | The route this entity can be found easier, should be a path |
|extend| no | Whether or not the properties of the original object are automatically included|

### Attribute

Attributes represent properties on objects that you want to communicate to underlying sources. In a standard setup, an attribute should apply the same restrictions as the underlying property (e.g., required) to prevent errors when pushing the entity to its source. It can, however, provide additional validations to a property. For example, the source AIU might require the property 'email' to be a unique string, but you could set the form to 'email', causing the input to be validated as an ISO-compatible email address.

#### Attribute - Properties

| Property | Required | Description |
|--------|-------------| -------------|
|name| yes  |`string` A name for this attribute. MUST be unique on an entity level and MAY NOT be 'id', ' file', 'files', ' search', ' fields', ' start', ' page', 'limit', 'extend' or 'organization' |
|description| no | The description for this attribute is shown in de API documentation|
|type|  yes |`string` See [types](#### Types)|
|format|  no |`string` See [formats](#### Formats)|
|validations|  no |`array of strings` See [validations](#### Validations)|
|multiple|  no |`boolean` if this attribute expects an array of the given type |
|defaultValue|  no |`string` An default value for this value if a user doesn't supply a value|
|deprecated|  no |`boolean`  Whether or not this property has been deprecated and will be removed in the future|
|required|  no |`boolean` whether or not this property is required to be in a POST or UPDATE|
|requiredIf| no |`array` a nested set of validations that will cause this attribute to become required |
|forbidden|  no |`boolean` whether or not this property is forbidden to be in a POST or UPDATE|
|forbiddenIf| no |`array`  a nested set of validations that will cause this attribute to become forbidden|
|example|  no |`string` An example of the value that should be supplied|
|persistToSource|  no |`boolean` Setting this property to `true` will force the property to be saved in the gateway endpoint (default behavior is saving in the EAV)|
|searchable|  no |`boolean` Whether or not this property is searchable|
|cascade|  no |`boolean`  Whether or not this property can be used to create new entities (versus when it can only be used to link existing entities)|

#### Types

The attribute type provides basic validations and a way for the gateway to store and cache values efficiently. Types are derived from the OAS3 specification. The currently available types are:

| Format | Description |
|--------|-------------|
|string| a text |
|integer| a full number without decimals|
|decimal| a number including decimals|
|boolean| a true/false |
|date| an ISO 8601 date |
|date-time| an ISO 8601 date |
|array| an array or list of values|
|object|Used to nest an Entity as attribute of another Entity, read more about [nesting]()|
|file|Used to handle file uploads, an Entity SHOULD only contain one attribute of the type file, read more about [handling file uploads]() |

* you are allowed to use integer instead of int, boolean instead of bool, date-time or dateTime instead of datetime.

#### Formats

A format defines how a value should be formatted and connects directly to a type. For example, a string may be an email format, but an integer cannot be a valid email. Formats are derived from the OAS3 specification but supplemented with additional formats generally needed in governmental applications (like BSN). Currently available formats are:

General formats

| Format | Type(s) | Description |
|--------|---------|-------------|
|alnum|         |Validates whether the input is alphanumeric or not. Alphanumeric is a combination of alphabetic and numeric characters|
|alpha|         |Validates whether the input contains only alphabetic characters|
|numeric|         |Validates whether the input contains only numeric characters|
|uuid|string||
|base|  |Validate numbers in any base, even with non-regular bases.|
|base64| | Validate if a string is Base64-encoded.|
|countryCode|string|Validates whether the input is a country code in ISO 3166-1 standard.|
|creditCard|string|Validates a credit card number.|
|currencyCode|string|Validates an ISO 4217 currency code like GBP or EUR.|
|digit|string|Validates whether the input contains only digits.|
|directory|string|Validates if the given path is a directory.|
|domain|string|Validates whether the input is a valid domain name.|
|url|string|Validates whether the input is a valid URL or not.|
|email|string|Validates an email address.|
|phone|string|Validates a phone number.|
|fibonacci|integer|Validates whether the input follows the Fibonacci integer sequence.|
|file|string|Validates whether file input is a regular filename.|
|hexRgbColor|string|Validates whether the input is a hex RGB color or not.|
|iban|string|Validates whether the input is a valid IBAN (International Bank Account Number).|
|imei|string|Validates if the input is a valid IMEI.|
|ip|string|Validates whether the input is a valid IP address.|
|isbn|string|Validates whether the input is a valid ISBN.|
|json|string|Validates if the given input is a valid JSON.|
|xml|string|Validates if the given input is a valid XML.|
|languageCode|string|Validates whether the input is language code based on ISO 639.|
|luhn|string|Validate whether a given input is a Luhn number.|
|macAddress|string|Validates whether the input is a valid MAC address.|
|nfeAccessKey|string|Validates the access key of the Brazilian electronic invoice (NFL).|

* Phone numbers should ALWAYS be treated as a string since they MAY contain a leading zero.

#### *Country specific formats*

| Format | Type(s) | Description |
|--------|---------|-------------|
|bsn|string|Dutch social security number (BSN)|
|nip|string, integer|Polish VAT identification number (NIP)|
|nif|string, integer|Spanish fiscal identification number (NIF)|
|cnh|string, integer|Brazilian driver’s license |
|cpf|string, integer| Validates a Brazillian CPF number |
|cnpj|string, integer|Validates if the input is a Brazilian National Registry of Legal Entities (CNPJ) number |

* Dutch BSN numbers should ALWAY be treated as a string since they MAY contain a leading zero.

#### Validations

Besides validations on types and strings, you can also use specific validations included in the validation array. Validation might be type-specific or formats, e.g., `minValue` can only be applied to values that can be turned into numeric values. And other validations might be more general, such as `required`.

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
|anyOf| | This group validator acts as an OR operator. `AnyOf` returns `true` if at least one inner validator passes.|
|arrayType| | Validates whether the type of input is array|
|arrayVal| |Validates if the input is an array or if the input can be used as an array (instance of ArrayAcces or SimpleXMLElement.|
|attribute| | Validates an object attribute, even private ones.|
|consonant| | Validates if the input contains only consonants.|
|contains| | Validates if the input contains some value. |
|containsAny| | Validates if the input contains at least one of the defined values.|
|control| | Validates if all of the characters in the provided string are control characters. |
|countable| | Validates if the input is countable - if you're allowed to use the `count()` function. |
|decimal| | Validates whether the input matches the expected number or decimals.|
|each| | Validates whether each value in the input is valid according to another rule.|
|endsWith| | This validator is similar to `Contains()`, but only validates if the value is at the end of the input.|
|equals| | Validates if the input is equal to some value.|
|equivalent| | Validates if the input is equivalent to some value.|
|even| |  Validates whether the input is an even number.|
|executable| |Validates if a file is an executable.|
|exists| | Validates files or directories.|
|extension| | Validates if the file extension matches the expected one. This rule is case-sensitive.|
|factor| | Validates if the input is a factor of the defined dividend.|
|falseVal| | Validates if a value is considered false.|
|file| | Validates whether file input is as a regular filename.|
|image| | Validates if the file is a valid image by checking its MIME type.|
|filterVar| | Validates the input with the PHP’s `filter_var()` function.|
|finite| | Validates if the input is a finite number.|
|floatType| | Validates whether the type of the input is float.|
|floatVal| |Validate whether the input value is float.|
|graph| | Validates if all characters in the input are printable and create a visible output (no white space).|
|greaterThen| | Validates whether the input is greater than a value.|
|identical| | Validates if the input is identical to some value.|
|in| | Validates if the input is in a specific haystack. |
|infinite | | Validates if the input is an infinite number.|
|instance| | Validates if the input is an instance of the given class or interface.|
|iterableType| | Validates whether the pseudo-type of the input is iterable with the'foreach` language construct.|
|key| | Validates an array key.|
|keyNested| | Validates an array key or an object property using `.` to represent nested data.|
|keySet| | Validates a key in a defined structure.|
|keyValue| | |
|leapDate| |Validates if a date is a leap.|
|leapYear| |Validates if a year is a leap.|
|length| | Validates the length of a data structure |
|lessThan| |Validates whether the input is less than a value.|
|lowercase | |Validates whether the characters in the input are lowercase.|
|not| | |
|notBlank| |Validates if the given input is not a blank value (null, zeros, empty strings, or empty arrays, recursively).|
|notEmoji| |Validates if the input does not contain an emoji.|
|no| |Validates if a value is considered as "No".|
|noWhitespace| | Validates a string contains no whitespace (spaces, tabs, and line breaks).|
|noneOf| |Validates if NONE of the given validators validate.|
|max| |Validates whether the input is less than or equal to a value.|
|maxAge| |Validates a maximum age for a given date. The $format argument should correspond to PHP's `date()` function.|
|mimetype| |Validates if the input is a file and its MIME type matches the expected one.|
|min| |Validates whether the input is greater than or equal to a value.|
|minAge| |Validates a minimum age for a given date. The $format argument should correspond to PHP's `date()` function.|
|multiple| |Validates if the input is a multiple of the given parameter.|
|negative| |Validates whether the input is a negative number.|

## Mapping

Mapping is the process of changing the structure of an object. For example, an object is either sent to or retrieved from an external source. This always follows in the Output <- mapping <- Input model. Mapping is beneficial when the source doesn't match the data model you want to use.

The gateway performs mapping as a series of mapping rules handled in order. Mapping rules are written in a To <- From

### Simple mapping

In its simplest form, a mapping consists of changing the position of a value within an object. A simple mapping does this mutation To <- From order. Or in other words, you describe the object you want using the object (or other data) you have

So let's see a simple mapping rule with a tree object like this

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "Chestnut", 
  "location":"Orvil’s farm"
}
```

And we conclude that we need to move the data in the `description` field to a `species` field to free up the description field for more generic data. Which we then also want to fill. In short move a value to a new position and insert a new value in the old position. We can do that with the following two mapping rules.

```json
{
    "species":"description", 
    "description":"This is the tree that granny planted when she and gramps got married",
}
```

Which will give us

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

So what happened under the hood? And why is one value executed as a movement rule and the second as a string? Let's take a look at the first rule

```json
{
    "species":"description" 
}
```

Rules carry out as a `To <- From` pair. In this case, the `species` has a `description` key. When interpreting what the description is, the mapping service has two options:

* The value is either a dot notation array pointing to another position in the object (see dot notation). If so, then the value of that position is copied to the new position
* The value is not a dot notation array to another position in the object (see dot notation), then the value is rendered as a twig template

### Twig mapping

Another means of mapping is Twig mapping. Let's look at a more complex mapping example to transform or map out data. We have a tree object in our data layer  looking like

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

Now the municipality opened up a county-wide tree register, and we would like to register our tree there. The municipality decided to move locations and species of the tree into metadata array data and thus expects an object like this

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big old tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "metadata":{
    "location":"Orvil’s farm", 
    "species":"Chestnut"
  }
}
```

Ok, so let's put our mapping to the rescue!

A mapping always consists of an array where the array keys are a dot notation of where we want something to go. And a value representing what we want to go there. That value is a string that may contain twig logic. In this twig logic, our original object is represented under de source object. In this case we could do a mapping like

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
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married", 
    "location":"Orvil’s farm", 
    "species":"Chestnut", 
    "metadata":{
        "location":"Orvil’s farm",
        "species":"Chestnut"
    }
}
```

Both dot-notation and twig-based mapping are valid to move value's around in an object. Dot-notation is preferred performance-wise.

### Dropping keys

Twig mapping is an improvement, but it's not quite there yet, because mapping alters the source object rather than replacing it. Optimally, we map and drop the data we won't need. We can do that under the `_drop` key. The `_drop` key accepts an array of dot notation to drop. Let's change the mapping to

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
    "name":"The big white tree",
    "description":"This is the tree that granny planted when she and gramps got married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut"
  }
}
```

**Note** droping keys is always the last action perfomed in the mapping procces.

### Adding keys

The mapping setup allows you to add keys and values to objects simply by declaring them. Let's look at the above example and assume that the county wants us to enter the primary color of the tree. A value that we don't have in our object. Assume all our trees to be brown. We could then edit our mapping to

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

Which would return

```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut", 
        "color":"Brown"
  }
}
```

...even though we didn't have a color value initially.

Also, note that we used a simple string value here instead of twig code. That's because the twig template may contain strings.

### Working with conditional data

Twig natively supports many [logical operators](https://twig.symfony.com/doc/3.x/templates.html), but a few of those are exceptionally handy when dealing with mappings. For example, concatenating
strings like {{ 'string 1' ~ 'string 2' }} which can be used as the source data inside the mapping

```json
{
    "color": "{{ \"The color is \" ~ source.color }}"
}
```

The same is achieved with [string interpolation](https://twig.symfony.com/doc/1.x/templates.html#string-interpolation) via

```json
{
    "color": "{{ \"The color is {source.color}\" }}"
}
```

So both of the above notations would provide the same result

Another useful twig take is the if statement. This can be used to check if a values exists in the first place

```json
{
    "color": "{% if source.color %} source.color {{ else }} unknown {{ endif }} }}"
}
```

or to check for specific values

```json
{
    "color": "{% if source.color == \"violet\" %} pink {{ endif }} }}"
}
```

### Conditional mapping (beta)

There are cases when a mapping rule should run if conditions are met. This is called conditional mapping. To make a mapping conditional, add a `|` to the mappings key followed by a JSON Condition description. The mapping will only execute if the JSON condition is met (results in true):

```json
{
    "color|{\"source.color\" : [\"blue\"]}": "{{ source.color }}",
    "metadata.color|{\"source.color\" : [\"blue\"]}": "{{ source.color }}"
}
```

Currently, it is impossible to build `if/or/else` cases with mappings. A single mapping rule is either run or not

### Working with array

Its currently not possible to re-map an array (change the keys within an array), but you can map an array into its entirety into a key that expects an array by bypassing the twig handler e.g.

```json
{
    "metadata":{
        "branches": [
            {
                "id":"475342cd-3551-4d55-aa4e-b734c1fda3f7",
                "name":"one"
                
            },
            {
                "id":"18e9d8be-32e5-4bec-93f2-55d60444fc4e",
                "name":"two"
            },
            {
                "id":"3ba4f1be-be2e-4e1a-ae83-04fed18fdf29",
                "name":"three"
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
            "name":"one"
            
        },
        {
            "id":"18e9d8be-32e5-4bec-93f2-55d60444fc4e",
            "name":"two"
        },
        {
            "id":"3ba4f1be-be2e-4e1a-ae83-04fed18fdf29",
            "name":"three"
        }
    ]
}
```

### Forcing the type/format of values

Due to twig rendering, mapping output will always change all the values to `string`. For internal gateway traffic, this isn’t problematic, as the data layer will cast values to the appropriate outputs. When sending data to an external source, having all Booleans cast to strings might be bothersome. To avoid this predicament, force the datatype of your values yourself by adding `|[format]` behind your mapping value e.g., `|string` or `|integer`. Beware what functions PHP uses to map these values and if the cast should be possible (or else an error is thrown).

| Cast            | Function                                                  | Twig   |
|---------------- |---------------------------------------------------------- |--------|
| string          | <https://www.php.net/manual/en/function.strval.php>         | No     |
| bool / boolean  | <https://www.php.net/manual/en/function.boolval.php>        | No     |
| int / integer   | <https://www.php.net/manual/en/function.intval.php>         | No     |
| float           | <https://www.php.net/manual/en/function.floatval>           |  No     |
| array           |                                                           | No     |
| date            | <https://www.php.net/manual/en/function.date>               |  No     |
| url             | <https://www.php.net/manual/en/function.urlencode.php>      |  Yes   |
| rawurl          | <https://www.php.net/manual/en/function.rawurlencode.php>   |  Yes   |
| base64          | <https://www.php.net/manual/en/function.base64-encode.php>  |  Yes   |
| json            | <https://www.php.net/manual/en/function.json-encode.php>    |  Yes   |
| xml             |                                                           |  No     |

Example a mapping of

```json
{
  ..
    "metadata.hasFruit": "Yes|bool",
  ..
}
```

Would return

```json
{
  ...
    "metadata":{
      ...
      "hasFruit":true
  }
}
```

Notice that format adjustments should be made outside the `{{`and `}}` twig brackets to overwrite the twig string output

### Translating values

Twigg natively supports [translations](https://symfony.com/doc/current/translation.html),  but remember that translations are an active filter `|trans`. And thus should be specifically called on values you want to translate. Translations are performed against a translation table. You can read more about configuring your translation table here.

The base for translations is the locale, as provided in the localization header of a request. When sending data, the base is in the default setting of a gateway environment. You can also translate from an specific table and language by configuring the translation filter e.g. {{ 'greeting' | trans({}, `[table_name]`, `[language]`) }}

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

returns (on locale nl)

```json
{
    "color":"bruin"
}
```

If we want to force German (even if the requester asked for a different language), we'd map like

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

The mapping doesn't support the renaming of keys directly but can rename keys indirectly by moving the data to a new position and dropping the old position

Original object

```json
{
    "name":"The big old tree"
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

returns

```json
{
    "naam":"The big old tree"
}
```

### What if I can't map?

Even with all the above options, it might be possible that the objects you are looking at are too different to map. In that case, don't look for mapping solutions. If objects A and B are too different, add them to the data layer and write a plugin to keep them in sync based on actions.

## API Documentation

add

## Translation table

Translation tables are specific by subject and contain translations from the gateway base language (default en) to other languages by iso language code, e.g., fr (French) or nl (Dutch). Translations are loaded and cached from both plugin files, gateway files, and the translation table.

## Uploading files through the gateway

The gateway supports the uploading of files as part of an object. To add file uploads, add an attribute of the type `file` to the entity you want to upload. It is strongly advised only to use ONE attribute of the type `file` per entity. Still, more are allowed (this will prevent your frontend from using multi-part form uploads and force base64 encoding of files on the frontend. Besides being more complex, this will also eat into your maximum memory use within the browser, commonly 10MB).

When creating an attribute of the type file, you can maximize size, restrict mime types and designate if you expect one or multiple files.

When done, it is possible to make multi-part posts to the specific endpoint representing the entity you just created (to test this in Postman, go to the body tab and set the body type to form-data).

When posting to an endpoint containing file type attributes through form-data, the first available attribute of the type file is used to process the incoming files. If you include multiple files, they are stored under the same attribute, and that attribute must be set to `multiple`.

If you want multiple attributes containing files (for example, all lists of diplomas and an employee picture), consider using sub-entities (with their own endpoints) or making an API-based request (like JSON).

You can also post files in a JSON-like manner by either supplying a base encoded string or an array containing the base encoded string and a name, e.g.

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
"name":"my logo",
"base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=="
}
```

When working with an attribute that allows `multiple` you MAY put either of the above methods into an array (not doing so will create a multiple of one), and mixing them is allowed (using the plain string and object option in the same array), e.g.

```json
{
 ….
"logos":[
"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==",
{
"name": "my logo",
"data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4
  //8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg=="}
}
```

Uploaded files are translated to file objects by the gateway and returned as objects. A download link is the returned default and not the base encoded file. This method improves performance (preventing large return objects) and safety (logging).

```json
{
 ….
"logo":{
"name":"my logo",
"extension":"png",
"mimeType":"image/png",
"size":"124065",
"url": "/api/downloads/2dee1608-2cdd-11ec-8d3d-0242ac130003"
}
}
```

Files MAY be passed on to underlaying registers or DMS system depending on the gateway settings.

## Easy configuration

### Dashboard

lorem ipsum

### API

lorem ipsum

### YAML

lorem ipsum

## Plugins

The gateway aims to provide all functionality for normal integration and data distribution through pre-made functionality configured. If you require functionality for a specific project or integration not provided by the gateway, you can add it as a plugin. A plugin is a separate bit of coding run within a gateway instance.

### Setting up your plugin

The gateway runs on the symphony flex framework for bundles. As such, all plugins should be valid symphony bundles. The gateway then discovers these bundles through the Public Code standard ( a publiccode.yaml must be included in the plugin codebase), and the gateway loads these bundles through packages (so they should be registered there). so lets take that stap by step.

1. **Setting up your plugin as a bundle** This is the easy part. We provide a template bundle at the gateway GitHub org at [this]() repository and follow the instructions.
2. **Setting up your public code file** Included in the template repository you just used and included at the root level. Take a moment to look into the publiccode.yaml file and check if you want to change anything.
3. **Registering to packagist** Head over to [packadgist.org]( https://packagist.org/packages/submit), login and submit your freshly created repository.

### Adding the functionality

Now that your plugin is ready to use, it's time to add functionality. Gateway plugins provide functionality by hooking into the gateway's [event system]() and then performing specific actions (like altering data or sending mail). They do this through handlers. A handler is a bit of code that handles an event sent by the event system. An example would be sending an email every time an object is stored in the [datalayer]().
Each handler should be a PHP file containing a class, implementing the `ActionHandlerInterface` and providing at least methods.

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
    * The constructor is a magic method that is called when the class is declared, it is generaly used to get an service from the container interface
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

Let's look at these functions one at a time. At the top of our code, we find our namespace. Namespacing is an integral part of MVC-designed frameworks on object-oriented languages. We follow the Symfony bundle standard for Gateway plugins to ensure the namespaces are unique and won't conflict between plugins. The namespace should, therefore, always be `[organisationsname]\[pluginname]Bundle`. Like:

```php
namespace Acme\EmailBundle;
```

Then the construct function. We use it to make services available as private variables within our class. We will get into services when we look at the `__run()` function, but for now, remember that it is good practice to defer business logic code to services.

*The `__construct()` function **MUST** be present and **MAY** set services for later use.*

```php

    private EmailService $emailService;

    /**
    * The constructor is a method that is called when the class is declared, it is generally used to get an service from the container interface
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

The example above requires us to `use` (the PHP version of importing) the appropriate classes. This is done at the top of the class like

```php
use App\Exception\GatewayException;
use Psr\Container\ContainerInterface;
use App\Service\EmailService;
```

The `getEvents` function tells the gateway what event types these handlers support. That is important because different event types present different kinds of data. Therefore, it is unlikely that one handler can deal with all event types (unless that handler doesn't use data in the first place). A Handler may return an empty array if it wishes to support all functions. You can find a list of all available events and the data they provide under [events](#events). The events will provide users with options when selecting adding actions and are used as validation when events are registered.

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

In the configuration array a handler tells the gateway which configuration options it has and how they should be presented to a user. This information is used by the gateway to render both userforms and validation

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

The run function is the function that is called by the event manager when an action is triggered. It receives the current data state and the configuration of the action that triggered it. You can read more about creating services [here](https://symfonycasts.com/screencast/symfony-fundamentals/create-service).

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

In order to register actions for handlers, the gateway must know what handlers are available. Making handlers known to the gateway is done through Symfony's yaml [configuration files](https://symfony.com/doc/current/configuration.html). Because the gateway uses the default Symfony bundle system, the configuration files should be in your bundle in a `resources`. This way makes it possible to extend the default gateway configuration from your bundle and add your handler to the handlers array like this:

```php
# config/packages/api_platform.yaml
common_gateway:
    handlers:
        - 'Acme\EmailBundle\EmailHandler'
```

### Adding plugins to your gateway

There are several options for adding plugins to your gateway, depending on if you run your gateway locally, online, or within a cloud.

1. Through the command line
2. Through package list
3. Through fixtures
4. Through Interface

## Dataservice

The data service is the main service used by the gateway to obtain altered data from either internal data lake, external sources or session-specific attributes. When dealing with data, this function **MUST** be used. Because of the event-driven nature of the gateway, underlying data could change during an event. That, in turn, means that normal Symfony functions might present false data. And using them in your code makes them error-prone.
The most dominant use case for the data service is as part of the mapping of objects (all functions within the data service are available through the twig function data service. The primary data services are:

### Data

{{Table}}

### Mapping

@todo mapping opbject aanmaken, mapping cast aanmaken, endpointHandler aanmaken, proxyHandler aanmaken.

### Session

### Request

### Datalayer
