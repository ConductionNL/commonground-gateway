# Endpoint

Endpoints are locations where applications can interact e.g. in commongateway.nl/api/pets the /pets part would be an endpoint.

## Defining an endpoint
Endpoints are created trough either the gateway API or UI (under the endpoints) an endpoint consists of the following special properties

- **pathRegex** The regex that is used by the gateway to find the endpoint, for the above - example that would be `^pets` but the regex could also alow for variable parts like `^pets/?([a-z0-9-]+)?$`. The pathRegex **MUST** be unique within an gateway installation
- **path** An array of the items in the path that are separated by / in the endpoint. For the above example that would be `[‘pets’,’id’]`. Path parts **MUST** exist of letters and numbers.
- **pathParts** An array containing the parts of the path that should be transformed into variable for later processes to use. Based on thier index in the path array and the name of the variable that should be created. For the above example that would be `[‘1’=>’id’]` 
- **methods** An array of the methods that are allowed on this path, for example [‘GET’,’PUT’,’POST’,’PATH’,’DELETE’]. An endoint **must** have at least one method  
- **source** An external source that is proxied by the endpoint (see Proxy)
- **schemas** Any schema’s provided by the endpoint (see schema’s)
- **throws** Any events thrown by the endpoint (see event driven)

## Handling incoming traffic
Once an endpoint is called by an external application (or user a browser is also an application) the endpoint will handle the following actions in the following order. Keep in mind that all extensions break the furter flow. e.g. if an endpoint has a proxy it will return the proxy result and never get to the schema point

## Proxy
An endpoint MAY be a proxy for another (external) source, in this case, the endpoint will forward all traffic to the external source (and attach authorisation if required by that source). It will also pass along any headers, query parameters, body and the method (e.g. GET) sent to the endpoint. Keep in mind that it will only do so if the method used is activated on the endpoint (in practice, it is quite common not to expose delete through an endpoint) 

If the endpoint path contains an endpoint parameter and this parameter is included in the path regex e.g. `example` it will also forward that message to that specific endpoint on the external source. So `gateway.com/api/petstore/pets` would be forwarded to   `petstore.com/pets`.

### Schema's
If an endpoint is connected to one or more schemas, it will try to handle the traffic based on the request service. If no entities are defined the endpoint will continue in its handling order.

When hooked to multiple schemas, the endpoint can still handle post requests BUT a post request must then include a valid _self scheme that refers to the appropriate schema so that the endpoint understands what schema you are trying to create. 

If an endpoint is linked to more than one entity, it will render search results from all entities that it is linked to. 

### Event driven
If no proxy or entity(s) are provided the endpoint will throw events as listen under throws. If no throws are defined the endpoint will continue in its handling order. 

When the endpoint throws events it generates a response in the call cache, after all the throws are handled it will return the response to the user. The response starts out as a `200 OK` “your request has been processed” but may be altered by any action that subscribed to a thrown event.

Any action can subscribe to an event thrown by an endpoint but common examples are proxy en request actions. These fulfill the same functionality as the direct proxy or event link but allow additional configuration, such as mapping.

### Return an error
If no proxy, entities or throws are defined the endpoint will return an error  

## Security
Endpoints MAY be secured by assigning them to user groups, this is done on the bases of methods.  

> **Warning**
> This file is maintained at (google drive](https://docs.google.com/document/d/1b0yGB1Q_IR27ik8XUurdFq9VUFqTWC62P-ZhZZF62EI/edit)
