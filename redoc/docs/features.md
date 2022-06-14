# Features

The Common Gateway is a feature-rich solution to develop Progressive Web Applications quickly.

## Sources

Sources form the beating heart of the Gateway. A source represents an external API (registered or microservice in nature) possibly exposed through the Common Gateway.

Adding an API as a source WILL NOT leave that API exposed. API's may be added manually through discovery. Discovery methods currently on the roadmap are NLX, Autorisatie Component, and Generic Kubernetes services.

Here is where you can configure access and communicate with those sources. You can create new sources or update existing ones.

The fields with \* are mandatory. Special mention to the documentation field where you can specify the URL to your API documentation. They are recommended for working in developers working in teams.

```json
Properties
_name_:
_location_:
_type_:
_accept_:
_locale_:
_auth_:
_jwt_:
_jwtid_:
_secret_:
_apikey_:
_documentation_:
_authorizationHeader_:
_userName_:
_password_:
```

You can add additional header specifications, OAS, and paths below.

## Authentication

The Common Gateway provides several out-of-the-box authentication mechanisms designed to lower the dependency on external services. These services include DigiD and ADFS and come with local and online mock environments. It allows a straightforward flow to set up applications focused on governmental services.

## Authorisation

The Common Gateway can either provide an authorization scheme on its own or deffer to other components like ADFS or the user component.

## Multi-application

It is possible to split authorization based on application profiles, providing a basic framework for running multiple applications from the same Gateway.

## Multi-tenancy

The Common Gateway provides multi-tenancy by separating all the data it holds (configuration and actual usage) along the lines of organizational IDs. This separation is hardcoded into the database queries and provides a fool-proof way of running a multi-tenancy setup from a single installation. If you, however, prefer a separation of databases on a tenant base, we advise you not to use a multi-installation setup.

## NL Design System Ready

The Common Gateway provides an easy (and inline scripting safe) way of giving applications NL Design System token files based on the organization currently served (see multi-tenancy). This allows applications to implement using NL Design tokens quickly. The only action an application needs is to include the following line before loading other CSS files `/API/organisations/{id}/NL-design-tokens.css`. Security-wise, the API is to be provided from the same domain as the application.

## Haven ready

Haven is a standard for platform-independent cloud hosting and dictates a specific configuration of Kubernetes. All municipalities in the Netherlands and various suppliers each maintain their technical infrastructure. Haven ready means the application is developed with Kubernetes standards following the Haven standard from the Common Ground ecosystem. A Haven-ready application works on any Haven environment and thus is modular by nature.

## GDPR compliance and logging

The Common Gateway supports logging all data requests and their 'doelbinding' to a GDPR registry (or in the case of Dutch governmental agencies, the VNG doelbindingsregister). Be aware that activating this functionality can lead to double registrations in the said registry if the receiving API is already logging all requests. Please check the API documentation you are connecting to before activating this functionality

## Notifications and event-driven

The Common Gateway can send notifications on its behalf when receiving and processing requests (both on successful and unsuccessful results). Other trigger event-driven applications (like a business engine) can also trigger events on behalf of different API's thereby providing event-driven architecture to ecosystems that generally do not support such a scheme. In both cases, the Gateway requires a notification API to be present under its sources and configurations for the other sources or entities that should use this API.

## Smart caching

The Common Gateway relies on smart caching to serve required data quickly. It does this by taking active subscriptions on any resources it caches. E.g., when caching a case from ZGW, it will take a "notificatie" subscription; any time the state changes, the Common Gateway will drop it from its cache and (if cache warmup is activated) replace it with a new copy). This simple process means that any API that supports the "notificatie" or "Berichten standaard" will serve the client lightning fast. It also allows the Gateway to share cache between applications (but not organizations) to speed up the process.

## Field limiting

The Common Gateway supports limiting the returned data using the fields query parameter. When supplied, the Gateway will only return the requested attributes (even if the entity contains more attributes) and only log the requested fields in the GDPR registry. Fields can be supplied as comma-separated or in an array as in ?fields=field1,field2,field2 or ?fields[]=field1&fields[]=field2. When dealing with sub-entities, simply listing the attribute containing the entity will result in returning the complete entity. If only specific fields are required, a dot notation array can be used to filter those e.g. ?fields[]field1.subfield1. Field limitation only supports a field depth of 5.

## Entity extension

You might encounter a linked data URI to a different API in a Common Ground registry (ZGW APIs are notorious examples) even with sufficient rights. An example would be: the references API is a valid Gateway source, and the user/application combination has the right to an entity matching the URI's endpoint. You could use the Gateway to include the linked data object if it were one result (this provides worse performance than just setting up the attribute properly). To do this, you can use the extend query parameters, e.g. `?extend=field1`, which can also be used on sub-entities by dot annotation, e.g. `?extend=field1.subfield1`. The Gateway will try to use smart caching to deliver the desired sub-entity, but this will require additional internal calls at best or an external API call at the worst—severely impacting performance.

## Smart search

The Common Gateway allows searching on any attribute marked as "searchable" even if the original API doesn't allow searching that specific attribute or property. Smart searching also supports the searching of sub-entities through dot annotation, e.g. `?field1.subfield1=searchme.`. All searchable fields of an endpoint are automatically collected for fuzzy search. The search query parameter allows you also to use `?search=searchme`. This comes with a severe performance drain, and specific searches are preferable. The wildcard search supports string-type attributes only.

## Ordering results

-To be added-

## Pagination

The Common Gateway supports two approaches to pagination, allowing the developer to choose what fits him best. When making a GET call to a collection endpoint, e.g. `/pet` instead of `/pet/{petId}`, the result will always be an array of objects in the results property of the response (even if the array has zero items). Additionally, a start(default 1), limit (default 25), page, pages, and total properties will be returned, allowing you to slice the result any way you want. Basic slicing is done using the start and limit properties and advanced slicing to the page end limit property (pagination). Let's assume that you have a collection containing 125 results. A basic GET would then get you something like

"`json
{
"results": "#array containing 125 items",
 "total":125,
 "start":1,
 "limit":25,
 "page":1,
 "pages":5
}

```

Let's say you are displaying the data in a table and want more results to begin with, so you set the limit to `?limit=100`, the expected result would be

"`json
{
 "results": "[#array containing 100 items]",
 "total":125,
 "start":1,
 "limit":100,
 "page":1,
 "pages":2
}
```

You can now provide pagination buttons to your users based directly on the page's property. Let's say you make a button for each page, and the user presses page 2 (or next). Your following call will then be `?limit=100&page=2` and the result

"`json
{
  "results": "[ #array containing 25 items]",
 "total":125,
 "start":101,
 "limit":100,
 "page":2,
 "pages":2
}

```

Alternatively, you could query `?limit=100&start=101` for the same result

## Exporting files

At some point, you might want to provide the user a download option for exporting a file containing a data set; for GDPR reasons, it is preferable to create these through the Common Gateway (to log data transfers properly in the GDPR registry). For this reason, all the collection endpoints support returning downloadable files besides JSON objects. Set the 'accept' head to the preferred file format to initiate a file download. We currently support `text/csv`,`application/ms-excel,`application/pdf`. Remember that you can use the field, extend, and pagination query functionality to influence the result/data set.

## Entities and Attributes

Object storage and persistence to other API's are handled through an EAV model. Meaning any object from an underlying source can be made available through mapping. This mapping is necessary because you may not want an API object's properties to be externally available through an AJAX request, or you might want to provide additional validation or staked objects through the API.

Generally, an underlying API will provide `endpoints` containing (collections of) `objects`. In the case of the swagger pet store example, the `/pet` endpoint includes a list of pets in a results array, and the `pet/{petId}` contains a single pet `object` identified by id.

A pet object looks something like this

"`json
{
    "id": 0,
    "category": {
        "id": 0,
        "name": "string"
    },
    "name": "doggie",
    "photoUrls": ["string"],
    "tags": [
        {
            "id": 0,
            "name": "string"
        }
    ],
    "status": "available"
}
```

So what does that mean for EAV mapping? Simply put, our objects become entities, and our properties become attributes. In this case, we will have an entity called Pet with the following attributes: 'category',' name',' photoUrls',' tags', and' status'/. Both entities and attributes can contain additional configuration, meaning they can do MORE than the original API object but never less. For example, if the pet object requires you to provide a name for a pet (it does), you might choose to ignore that requirement on the name attribute, but that will cause the underlying API to throw an error any time you try to process a pet without a name.

### Entity

Entities represent objects you want to communicate to underlaying sources. To do this, they require a source and an endpoint on that source to send the object to. You may choose to ignore this and 'store' your objects within the Gateway. This might be an excellent way to mock APIs in development environments, but it's not recommended for production environments.

#### Properties

An entity consists of the following configurable properties.

_name_: 'required' An unique name for this entity
_description_: 'optional' The description for this entity that shows up in de API documentation
_source_: 'optional' The source where this entity resides
_endpoint_: 'required' if a source is provided. The endpoint within the source that this entity should be posted to as an object
_file_: 'required' Based on whether this entity contains file uploads or not, this should either be set to 'no',' yes', or 'multiple'. Where 'yes' will allow a single file to be uploaded through this entity, and 'multiple' will allow multiple files to be uploaded. Read more under 'Uploading files through the Gateway'.
_fileRequirements_: 'required if a file is either yes or multiple' Read more under 'Uploading files through the Gateway'.

### Attribute

Attributes represent properties on objects that you want to communicate to underlying sources. In a standard setup, an attribute should apply the same restrictions as the underlying property (e.g., required) to prevent errors when pushing the entity to its source. It can, however, provide additional validations to a property. For example, the source aiu might require the property 'email' to be a unique string, but you could set the form to 'email', causing the input to be validated as an ISO-compatible email address.

#### Properties

_name_:' required' A name for this attribute MUST be unique on an entity level and MAY NOT be 'id',' file', 'files',' search',' fields',' start',' page',' limit',' extend',' organization'
_type_:' required' See types
_format_:' optional' See formats

#### Types

The type of an attribute provides basic validations and a way for the Gateway to store and efficiently cache values. Types are derived from the OAS3 specification. Currently available types are:

_string_:
_int_
_date_
_date-time_

#### Formats

A format defines a value that should be formatted and directly connects to a type. For example, a string may be an email format, but an integer cannot be a valid email. Formats are derived from the OAS3 specification but supplemented with formats generally needed in governmental applications (like BSN). Currently available formats are:

_bsn_:

#### Validations

Besides validations on type and string, you can also use specific validations; these are contained in the validation array. Validation might be specific to certain types or formats, e.g., minValue can only be applied values to numeric values. And other validations might be more general, e.g., the 'required' value.

## API Documentation

API documentation generates from the OAS specification automatically with Redocly.

## Uploading files through the Gateway

- To be Added -

## Easy configuration

Easy configuration is available via the `Configurations` tab in the User Interface. Exporting the configuration will trigger a YAML download with all the settings.

### Dashboard

The dashboard is accessible via the frontend repository. The dashboard provides a lot of functionality, but not all functionality is available on the dashboard.

### YAML

Yaml is the configuration format for the Gateway and instances of the Gateway.
