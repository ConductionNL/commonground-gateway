#Features
The web gateway is designed as an feature rich solution to quickly developing progressive web applications. 
##Sources
Sources form the beating heart of the gateway. A source represents an external API (be it either registered or microservice in nature) that might be exposed through the web gateway. Adding an API as a source WILL NOT cause that API to be exposed. API’s might be added manually our through discovery, discovery methods currently on the roadmap are NLX, Autorisatie Component and Generic Kubernetes services.

Properties
*name*:
*description* 
##Authentication
The web gateway provides several out of the box authentication mechanisms that are aimed at lowering the dependency on external services. The include DigiD and ADFS and also come with local and online mock environments. Creating an easy and straightforward flow to setting up application focuses on governmental services. 

##Authorisation
The web gateway can either provide an authorisation scheme on its own or deffer to other components like ADFS or het user component. 

##Multi-application
It is possible to split authorisation based on application profiles, providing basic framework for running multiple applications from the same gateway. 

##Multi-tenancy
The web gateway provides multi-tenancy by separating all the data it holds (both configuration and actual usage) along the lines of organizational id’s. This is hardcoded into a database query’s and thereby provides a save an fool proof way of running an  multi tenancy setop from an single installation. If you however prefer an seperation of databases on tenant base we advise you to use a multi-installation setup.
##NL Design System Ready
The wab gateway provides an ease (and inline scripting save) way of providing application with NL Design System token files based on the organisation that is currently served (see multy-tenency). This allows applications to easily implement NL Design by using nl Design tokens (a full list of which can be viewed here). The only actions an application need to take is to include the following line before loading other ccs files `/api/organisation/{id}/nl-design-tokens.css`. Keep in mind that from a security perspective the API needs to be provided from the same domain as the actual application.

##Haven ready
ad

##GDPR compliance and logging
The web gateway support logging all data requests and there ‘doelbinding’ to a gdpr registry (or in case of ducth governmental agencies the vng doelbindings register), be aware do that activating this functionality can lead to double registrations in said registry if the receiving api is also already logging all requests. Therefore please check the documentation of the api you are connecting before activating this function 

##Notifications and event driven
The web gateway can send notification on its own behalf when receiving and processing request (both on successful and unsuccessful results) and thereby trigger event driven applications (like a busnes engine), it can however also trigger events on the behalf of other api’s thereby providing event driven architecture to ecosystems that normally do not support such a scheme. In both cases the gateway requires an notification api to be present under its sources and configurations for the others sources or entities that should us this api.

##Smart cashing
The web gateway relies on smart caching to quickly serve required data, it does this by taking active subscriptions on any resources that it cashes. E.g. when cashing a case from zgw it will take an notificatie subscription, any time the case is changed the web gateway will drop it from it cache and (if cache warmup is activated) replace it with a new copy). This simple process means that any api that supports the notificatie or berichten standaard will be served to the client lightning fast. It also allows the gateway to share cache between applications (do not organisations) to speed the proses up even more. 

##Field limiting
The web gateway supports limiting the returned data by use of the fields query parameter. When supplied the gateway wil only return the requested attributes (even if the entity contains more attributes) and wil only log the requested fields in the GDPR registry. Fields can be supplied as comma separated or in an array as in ?fields=field1,field2,field2 or ?fields[]=field1&fields[]=field2. When dealing with sub entities simply listing the attribute containing the entity will result in returning the complete entity when only specific fields are required then a dot notation array can be used to filter those e.g. ?fields[]field1.subfield1. Field limitation is only supported to a field depth of 5. 

##Entity extension
Occasionally you might encounter a linked data uri to a different api in a commonground registry (zgw api’s are notorious examples) if sufficient rights have been provided (the references api is a valid gateway source and the user/application combination has right to an entity matching the uri’s endpoint) you can use the gateway to include the linked data object in the result if though it where one result (keep in mind do that this provides worse performance than just setting up the attribute properly). To do this you can use the extend query parameters e.g. `?extend[]=field1` which can also be used on sub entities by using dot annotation eg.  `?extend[]=field1.subfield1`. The gateway will try to use smart cashing to quickly deliver the desired sub entity but this will require additional internal calls at best, or an external api call at the worst. Severely impacting performance.
###Metadata extension
In addition to the way we can extend objects as described above, there is also a way to use the extend query parameter to show more metadata about objects. This can be done by using `?extend[]=x-commongateway-metadata` and if you just want one or a few specific metadata fields you could also do the following: `?extend[]=x-commongateway-metadata.dateCreated&extend[]=x-commongateway-metadata.dateModified`. Please do note that using the Accept headers application/json+ld or application/json+hal will always result in a response with metadata included (except for the field dateRead).

##Smart search 
The web gateway allows searching on any attribute that is marked as  ‘searchable’ even if the original API dosn’t allow for searching on that specific attribute or property. It also support the searching of sub entities by the way of dot annotation e.g. `?field1.subfield1=searchme. All searchable fields of an endpoint are automatically collected for fuzzy search under the search query parameter allows you to also use `?search=searchme` be aware tho that this comes with a severe performance drain and that specific searches are almost always preferable. Wildcard search is only supported on string type attributes. 

##Ordering results
adas
##Pagination
The web gateway supports two different approaches to pagination, allowing the developer choose what fits him best. When making a GET call to a collection endpoint e.g. `/pet` instead of `/pet/{petId}` the result will always be an array of objects in the results property of the response (do the array might consist of zero items).  Additionally a start(default 1), limit (default 25), page, pages and total property will be returned allowing you to slice the result any way you want. Basic slicing can be done using the start and limit properties, advanced slicing to the page end limit property (pagination). Lets assume that you have an collection containing a 125 result. A besic get would then get you something like 
```json
{
	“results”: .. ,#array containing 25 items
	“total”:125
	“start”:1,
	“limit”:25,
	“page”:1
	“pages”:5
}
```

Lets say that you are displaying the data in an table and want more results to start with so you set the limit to `?limit=100`, the expected result would then be
```json
{
	“results”: .. , #array containing 100 items
	“total”:125
	“start”:1,
	“limit”:100,
	“page”:1
	“pages”:2
}
```
You can now provide pagination buttons to you user based directly on the pages property, lets say you make a button for each page and the user presses page 2 (or next) your following call will then be  `?limit=100&page=2` and the result 
```json
{
	“results”: .. , #array containing 25 items
	“total”:125
	“start”:101,
	“limit”:100,
	“page”:2
	“pages”:2
}
```
Alternatively you could also query `?limit=100&start=101` for the same result

##Exporting files
At some points you might want to provide a user a download option for an export of file containing a data set, for GDPR reasons it is preferable to create these through the web gateway (so that the data transfer can be properly logged in the gdpr registry). For this reason all the collection endpoints support returning downloadable files besides returning json objects. To initiate a file download simply set the ‘accept’ head to the prefferd file format. We current support `text/csv`,`application/ms-excel`,`application/pdf` keep in mind that you can use the field, extend and pagination query functionality to influence the result/data set. 
##Entities and Attributes
Object storage and persistens to other api’s is handled trough an eav model. This means that any object from an underlying source can be made available through an mapping this mapping is necessary because you may not want all the properties of an api object to be externally available trough an ajax request, or you might want to provide additional validation or staked objects through the API. 

Generally speaking on underlying API will provide `endpoints` containing (collections of) `objects`. In case of the swagger petstore example that the `/pet` endpoint  contains a list of pets in an results array and the  `pet/{petId}` contains single pet `object` identified by id.

An pet object looks something like this
```json
{
  "id": 0,
  "category": {
    "id": 0,
    "name": "string"
  },
  "name": "doggie",
  "photoUrls": [
    "string"
  ],
  "tags": [
    {
      "id": 0,
      "name": "string"
    }
  ],
  "status": "available"
}
```

So what does that mean for EAV mapping? Well simply put our objects become entities and our properties become attributes. So in this case we will have an entity called Pet that has the following attributes: ‘category’,’name’,’photoUrls’,’tags’ and ’status’/. Both entities and attributes can contain additional configuration, meaning the can do MORE than the original api object but never less. For example is the pet object requires you to provide a name for a pet (it does) you might chose to ignore that requirement on the name attribute but that will just cause the underlying api to throw an error any time you try to process a pet without a name. 



###Entity
Entities represent objects that you want to communicate to underlaying sources, to do this they require a source and an endpoint on that source to send the object to. You may however choose to ignore this. And just ‘store’ you objects with the gateway, do this might be a good way to mock api’s in development environments it isn’t recommended for production environments.

####Properties
An entity consists of the following properties that can be configured

*name*: ‘required’ An unique name for this entity
*description*:  ‘optional’ The description for this entity that wil be shown in de API documentation
*source*: ‘optional’ The source where this entity resides
*endpoint*: ‘required if an source is provided’ The endpoint within the source that this entity should be posted to as an object
*file*: ‘required’ Whethers this entity might contain file uploads, if present this should either be set to ‘no’,’yes’ or ‘multiple’. Where yes wil allow a single file to be uploaded through this entity and multiple wil allow multiple files to be uploaded through this entity. Read more under ‘Uploading files through the gateway’.
*fileRequirements*: ‘required if file is either yes or multiple’ Read more under ‘Uploading files through the gateway’.

###Attribute
Attributes represent properties on objects that you want to communicatie to underlying sources. In a normal setup and attribute should at leas apply the same restrictions as the onderlying property (e.g. required) to prevent errors when pushing the entity to its source. It can however provide additional validations to an properte, for a example the source aiu might simply require the property ‘email’  to be an unique string but you could set the formt to ‘email’ causing the input to be validated as an iso compatible email adres. 


####Properties
*name*: ’required’  An name for this attribute MUST be unique on an entity level and MAY NOT be ‘id’,’file’,‘files’, ’search’,’fields’,’start’,’page’,’limit’,’extend’,’organization’
*type*:  ’required’ See types
*format*:  ’optional’ See formats

####Types
The type of an attribute provides basic validations and an way for the gateway to store and cahse values in an efficient manner. Types are derived from the OAS3 specification. Currently available types are:

*string*:
*int*
*date*
*date-time*

####Formats
A format defines a way an value should be formated, and is directly connected to an type, for example an string MAY BE an format of email, but an integer cannot be an valid email. Formats are derived from the OAS3 specification but supplemented with formats that are generally needed in governmental applications (like bsn) . Currently available formats are:

*bsn*:
####Validations
Besides validations on type and string you can also use specific validations, these are contained in the validation array. Validation might be specific to certain types or formats e.g. minValue can only be applied values that can be turned into numeric value. And other validations might be of an more general nature e.g. required.
##API Documentation
ad

##Uploading files through the gateway
ad

##Easy configuration

###Dashboard
gjgh
###API
fgfhfgh
###YAML
sdfs
