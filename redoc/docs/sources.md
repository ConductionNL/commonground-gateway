# Schema’s


## Downloading schema’s

## Uploading schema’s

You can upload a schema through the UI by pressing the upload button in the top right corner, schema’s might also by uploaded by plugins or collections.  When a schema is uploaded the following things will happen:

The gateway will look in its schema library if a version of that schema is already present. It does so based on the schema ID.
Blessed on that result the gateway will handle the schema acooridinglik
If no matching schema is found the gateway will create a new schema
If a matching schema is found the gateway wil compare versions to establish what to bassed on the following option
The old schema has no set version and the new schema has no set version -> The gateway will update the old schema with the new schema
The old schema has no set version and the new schema has a set version -> The gateway will update the old schema with the new schema
The old schema has a set version number and the new schema has a higher set version number -> The gateway will update the old schema with the new schema
The old schema has a set version number and the new schema has a lower set version number -> The gateway does not update the schema
The old schema has a set version number and the new schema does not have a set version number -> The gateway does not update the schema

Let take a look at an example, we have a wheather plugin that contains a wheather schema.

# Objects

## Hydration
