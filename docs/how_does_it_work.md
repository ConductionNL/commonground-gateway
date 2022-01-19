## How does the Commonground API work?

To understand what the Commonground API does, we need to know how to use it and its intended use.

The Commonground Gateway is built to solve a problem: as users want to take care of certain things like moving, applying for passports, etc. - they need to submit or verify the information. The information (data) goes through a PWA (Progressive Web App), better known as a website to most people, in both ways - from the user to where it needs to be and back.

This data may be classified or needs a data quality check before organizations can work with the data. This requires data handling. It MUST send this data elsewhere because it can't do anything else with the information itself. That's what we mean by the PWA is dumb.

## Enter the Gateway

Now that we know the PWA can't do anything with the data itself, we will need external help (server-side computation). We submit the data for further processing with a POST request to the Commonground API (an API is an interface that makes it possible for two applications to "talk" to each other). This API is called the Commonground Gateway and has a bunch of neat tricks. It knows what to do with the submitted data and how you want it returned. Pretty cool, huh?

The PWA (client) sends it to the Gateway, and the Gateway does what we need for us. If we need to store data, it does that. If we need data returned... no problem! Even if we send the data in different formats, it takes care of it without a problem. Welcome to the wonderful world of automation.

[General Overview of the Gateway API flow]('../docs/assets/genera_flow-1.png')

## API call

Not everyone should call upon the API and ask for (often sensitive) data. This should go without saying. We ensure only the appropriate end-users can ask for the Gateway API's functionality through calls; a unique identifier is needed (UUID) to start a session and authenticate requests.

The session logic is handled by [Symfony](https://symfony.com/doc/current/components/http_foundation/sessions.html) through a series of setter and getter methods. Symfony is a high-performance HTTP Request-Response PHP framework.

//ZZ controller (ASK)

## Endpoint

-   To be updated -

## Handlers

The incoming data is sent in objects. These objects can be sent in different formats, but the content of these objects may vary as well.
To explain it briefly, we will provide you with a "week/weather" analogy.

Let's picture a JSON object(although handlers also accept XML /SOAP):
` { "monday" : "rain", "tuesday" : "sunny", "wednesday" : "cloudy", "thursday" : "blizzard", "friday" : "hurricane", }`
It would be lovely if everyone knew this format and stuck to it, but in reality, the values may change, and even the properties may come in under different names. Instead of "Monday" as a property name, the object may come in with "Maandag" or "Lundi" as a given property name. Luckily, this is where handlers come in; handlers do what their name implies and handle a specific functionality. In this situation, handlers would use mapping. Similarly, if the property values need translation, well, the handlers use just that: translation.

Once the mapping/ translation is done correctly, the handlers send the object data to the EAV storage (more below).

Here's broad overview of the translation handler in action:

[image handler functionality]('../docs/assets/translationhandler.png')

The gateway supports several forms and moments of translations, these are all based on the request chain.

## Direct Access Storage (EAV)

After the handlers have appropriately processed the data objects according to the Entity-Attribute-Value format, the data objects are sent to the Direct Access Storage (EAV).

If the current user session is authorized, the data is now accessible and retrieved in the desired format. It passes handlers again to validate the data objects and is sent as a Symfony Response Event.

<!-- FIXTURES  -->

If there's a need to populate the EAV database with "fake" or pre-set data, the usage of Symfony fixtures is supported and recommended.

## Back to handlers (Cleaning the EAV)

After cleaning everything in the gateway up to the Direct Access Storage, it's done similarly on the way out.

The outflow is based separately on already existing code in a new service. Again, it is helpful to do this incrementally. The order is
1 ) A rights check (is it allowed for the user in session?)
2 ) break down methods
3 ) validation
4 ) write object(s) to MongoDB
5 ) activating events (so that we can also check sources and services)

#### Breaking down Rights and Methods

We will use the ObjectEntityService, to which we add a handle object function. It receives this from the HandlerService

-   handler
-   Method (GET, POST, etc.)
-   ID (optional)
-   data

We consciously choose not to provide a request and entity. The entity can be retrieved later via the handler. The handler function then goes through several steps:

-   Determine application (this is a separate function)
-   Rights control (move to security service)
-   if Create POST object.
-   if PUT, PATCH, or Delete get an existing object
-   if POST, PUT or PATCH-> validation (using validation service)
-   switch for processing the result where PUT and PATCH can fall under the same denominator, the switch must default to an unsupported Method error
-   Fire any events
-   Returning a response array

The question here is whether we want to immediately implement the difference between PUT and PATCH [see also](https://rapidapi.com/blog/put-vs-patch/). You may need to include a Validate PUT as true config on the handler.

The four primary CRUD functions called by the handler need an object as input.

#### Validation

We will compare arrays with entities (and therefore stop using ObjectEntities). We can also use the validator outside the concept of object entities (this is preparatory work for working with GraphQL). This validator needs as input:
the original data array
the entity against which the validation is performed
the method (PUT, POST, or PATCH) depends on whether missing required fields are returned.
when the validator encounters an object as a property, they call themselves

For validation, we gratefully [use](https://respect-validation.readthedocs.io/en/latest/feature-guide/)

The general idea behind this is when we build a validator object, we can attach the $data at the end of the exercise. We will get a series of errors or get an OK status returned (that we can pass on to the end-user).

[See also](https://respect-validation.readthedocs.io/en/latest/feature-guide/#validating-array-keys-and-values)

Globally you'll get something in lines of:

```
public validateData(array $data, Entity $entity, string method){

    $validatorData = $data;
    $validator = New Validator;
    $validator =  createEntityValidator($validatorData, $entity,   $method);
    return $validator->validate(data);

}

private createEntityValidator(array $data, Entity $entity, string method, Validator $validator){

// Let's validate each attribute
foreach($entity->getAttributes() as $attribute){

	// fallback for empty data
if(!array_key_existis($attribute->getName())){
$data[$attribute->getName()] = null;
}
$validator->key( $attribute->getName(), CreateAttributeEntityValidator($data, $entity, $method, $validator))

// Let's clean it up
unset($data[$attribute->getName()])
}

// Let's see if we have attributes that should not be here (if we haven’t cleaned it up it isn’t an attribute)
foreach($data as $key => $value){
  	$validator->key($key, /\* custom not allowed validator) )
}
 return $validator;
}

private CreateAttributeEntityValidator($data, Atribute $atribute, string method, Validator $validator){
	// if this is an entity we can skip all this
if($attribute->gettype() = ‘object’ ){
return $this->createEnityValidator($data, $attribute->getObject(), $method, $validator);
}

// Validate type
// Check the current validation services on validateType()

// Validate format
// Check the current validation services on validateType()

// Besides the type and format there could be other validations (like minimal datetime, requires etc.)
foreach($atribute->getValidators() as $key = > $value){
switch (key) {
// we need to check for cases here (anything not natively supported by validator or requiring additional logic)
case ‘jsonlogic’:
code to be executed if n=label1;
break;
case ‘postalcode’:
code to be executed if n=label2;
break;
case label3:
code to be executed if n=label3;
break;
// what is left is the default stuff
default:
// we should not end up here…
}
}

    return $validator;
```

#### Firing to sources

An important new insight is saving in EAV and writing to sources separately, whereas writing to a source is asynchronous and arranged based on events. These events are similar to handlers in knowing configuration, mapping, skeletons, and translation (and whether or not they fire using JSONLogic). Unlike handlers, these events are not unique. In other words, multiple events could be fired on one request alone. These events are asynchronous unless otherwise specified (part of the config array).

This requires several steps.

-   setting up a second database connection in the doctrine configuration
-   setting up the Database Service to handle all matters related to the EAV database.
-   On this service, set up a createEntity, updateEntity, deleteEntity, updateAtribute, createObject, updateObject, deleteObject.
-   We can use doctrine to create tables based on EnityObjects and their attributes. [See also](https://stackoverflow.com/questions/44761759/symfony-3-query-without-entity-but-with-doctrine)

In combination with [MySQL](https://dev.mysql.com/doc/refman/5.7/en/getting-information.html) the main thing we need to do is create a translation array where we translate attribute type to column type). So that we can easily convert attributes to columns.
Then we can CRUD non-nested objects relatively quickly, by using the [querybuilder](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/query-builder.html)

Nested objects have to be added independently via the query builder. To prevent execution when one insert fails, mapping everything in a transaction should be standard practice. [For more info](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/transactions.html#transactions)

#### Symfony Response

After the logic goes through the handler functions to map and translate the data accordingly, a Symfony response is given through the controller.

A controller is a PHP function you create that reads information from the Request object and creates and returns a Response object. The response could be an HTML page, JSON, XML, a file download, a redirect, a 404 error, or anything else. In this case, it will be an object with everything you asked for. It knows this from the request headers in the API call earlier.

You can get this response in different ways returned. It depends on how you want to get the request and if that can be delivered in the requested format.

For example:
You can make the API call to ask for data with request headers specified. You want the response to be a JSON object from the EAV, the handlers check if the conversion is possible(it is). If the conversion is possible, it will return the object in the requested format. Otherwise, it will default to
an error message or default type.

Generally, accepted conversion types are JSON, XML, SOAP, REST objects.

## Sessions

A session is a temporary UUID tied to a company ID, user ID, entity or endpoint URL. Sessions are used to avoid excessive API calls by having to log in over and over (and authenticate each time).  
All sessions in Symfony are handled by the (HttpFoundation)[https://symfony.com/doc/current/session.html#configuration] component. This component ensures a straightforward Object-Oriented interface. If a custom configuration is required, recommended to look at the (Symfony Session documentation)[https://symfony.com/doc/current/components/http_foundation/sessions.html] to avoid potential mix-ups with standard PHP code and Symfony's.

Sessions are stored in memory with Redis, so reads and writes will be fast but not stored on disk. If you need to store sessions on disk, take a look at (persistent services)[https://redis.io/topics/persistence] and add it to ` .symfony/services.yaml`

## Subscriber

An (event) subscriber is a method to handle events. During the execution of a Symfony application, lots of event notifications are triggered. The subscriber listens for a specific event and executes code that drives the business logic to handle the event it's subscribed to - such as the translation logic.

The subscriber listens for events in session and logs whenever data is accessed. Logging is done with (Redis)[https://symfony.com/doc/current/session/database.html#store-sessions-in-a-key-value-database-redis].

Every event will get a UUID from the subscriber. This makes backtracking easier for every request and to see whether the request was successful or has failed. All endpoints have different IDs, and combined give a clear picture of how business logic went. This is stored in the Log Object.
