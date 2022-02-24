This is an overview of your object type. Object types are also known as _entities_ in the backend. Click `Create` to create a new object.

In the overview objects are shown with their respective connections and relations.
This is where _Entities_ are defined for the objects to be stored. Entities are object that exists.
Entities don't have to do anything, they can just exist for mock purposes. For an entity to be useful as an object, attributes are needed and can be specified in section of the dashboard.

In database administration, an entity can be a single thing, person, place, or object. Data can be stored about such entities. Linking to sources is not mandatory.

The object needs `name` and `function(role)` as a minimum to be stored.

You can configure access to objects in this tab.

Properties:

`_name_`: **required** An unique name for this entity,

`_function_`: **required** function role is selected for priviliges.

`_endpoint_`: **required** if an source is provided. The endpoint within the source of this entity should be posted to as an object

`_route_`: **optional** should be entered as :`/api/[PATH]`

`_source_`: **optional** The source where this entity resides

`_description_`: **optional** The description for this entity that will be shown in de API documentation
