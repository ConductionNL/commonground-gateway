A collection contains a reference to an openapi.yaml file on GitHub.

This file contains a description of another API outside the gateway.

A collection can be loaded by means of an api call or by setting autoLoad of a collection to true.
When a collection is loaded, Entities, Attributes, Handlers and Endpoints are automatically created in the Gateway, which ensure that the description of that other API also becomes available within the Gateway.

There is also an option to specify test data for a collection and a 'loadTestData' boolean with which you can indicate that test data for this collection may also be loaded into the gateway.
