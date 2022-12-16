# Endpoint


> **Warning**
> This file is maintained at conductions [google drive](https://docs.google.com/document/d/1b0yGB1Q_IR27ik8XUurdFq9VUFqTWC62P-ZhZZF62EI/edit), please make any suggestions of alterations there.


Endpoints are locations where applications can interact with the gateway e.g., in commongateway.nl/api/pets the /pets part would be an endpoint. External endpoints are always publicized under /api/[your endpoint] to prevent malicious overwrite of internal gateway endpoints. 

## Defining an endpoint
Endpoints are created through either the gateway API or UI (under the endpoints). Endpoints consists of the following special properties:

- **pathRegex** The regex that is used by the gateway to find the endpoint, for the above - example that would be `^pets` but the regex could also allow for variable parts like `^pets/?([a-z0-9-]+)?$`. The pathRegex **MUST** be unique within an gateway installation
- **path** An array of the items in the path that are separated by / in the endpoint. For the above example that would be `[‘pets’,’id’]`. Path parts **MUST** exist of letters and numbers.
- **pathParts** An array containing the parts of the path that should be transformed into variables for later processes to use. Based on their index in the path array and the name of the variable that should be created. For the above example that would be `[‘1’=>’id’]` 
- **methods** An array of the methods that are allowed on this path, for example [‘GET’,’PUT’,’POST’,’PATCH’,’DELETE’]. An endpoint **must** have at least one method  
- **source** An external source that is proxied by the endpoint (see Proxy)
- **schema's** Any schema’s provided by the endpoint (see schema’s)
- **throws** Any events thrown by the endpoint (see event driven)

## Handling incoming traffic
Once an endpoint is called by an external application (or user, a browser is also an application), the endpoint will handle the following actions in the following order. Keep in mind that all extensions break the flow further. e.g., if an endpoint has a proxy, it will return the proxy result and never get to the schema point

## Proxy
An endpoint MAY be a proxy for another (external) source. In this case, the endpoint will forward all traffic to the external source (and attach authorisation if required by that source). It will also pass along any headers, query parameters, body, and the method (e.g., GET) sent to the endpoint. Keep in mind that it will only do so if the method used is activated on the endpoint (in practice, it is quite common not to expose delete through an endpoint) 

Suppose the endpoint path contains an endpoint parameter that is included in the path regex e.g. `example`. In that case, it will also forward that message to that specific endpoint on the external source. . So `gateway.com/api/petstore/pets` would be forwarded to   `petstore.com/pets`.

### Schema's
If an endpoint is connected to one or more schemas, it will try to handle the traffic based on the request service. The endpoint will continue in its handling order if no schema’s are defined.


If an endpoint is hooked to schema(‘s) it will automatically create an API and appropriate redoc based on its settings. See API for more information on the API.

It is possible to hook an endpoint to multiple schema’s.When hooked to multiple schemas, the endpoint can still handle post requests, BUT a post request must then include a valid _self.scheme.id or _self. scheme.ref that refers to the appropriate schema so that the endpoint understands what schema you are trying to create. 

If an endpoint is hooked to more than one entity, it will render search results from all linked entities based on supplied queries

### Event driven
If no proxy or entity(s) are provided, the endpoint will throw events as listen under throws. The endpoint will continue in its handling order if no throws are defined. 

When the endpoint throws events, it generates a response in the call cache. After all the throws are handled it will return the response to the user. The response starts as a `200 OK` “your request has been processed” but may be altered by any action that subscribed to a thrown event.

Any action can subscribe to an event thrown by an endpoint, but common examples are proxy en request actions. These fulfill the same functionality as the direct proxy or event link but allow additional configuration, such as mapping.

### Return an error
The endpoint will return an error if no proxy, entities, or throws are defined.  

## Security
Endpoints MAY be secured by assigning them to user groups, this is done on the bases of methods.  



