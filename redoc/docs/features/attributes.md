## `Type`

The type of this Attribute, is the **only required** 'validation'.

The type of an attribute provides basic validation and a way for the Gateway to store and cache values.
Types are derived from the [OAS3](https://spec.openapis.org/oas/v3.1.0) specification. Available types are:

`{ "string", "integer", "boolean", "float", "number", "datetime", "date", "file", "object", "array" }`

The type `array` is when the data you enter is an object, but can't be defined as such. (e.g., cascaded from a backend source (Gateway), or because the Gateway perceives it as not a separate object, but a subobject without its ID).

If you want this attribute to be an array of any (other) types, take a look at `Multiple` instead of using type `array`!

If the type `file` is used for this Attribute, values should mimic the following example (Where `base64` is required and `filename` optional):

```json
 "TheNameOfYourAttribute": {
        "filename": "aFilenameHere.txt",
        "base64": "data:text/plain;base64,ZGl0IGlzIGVlbiB0ZXN0IGRvY3VtZW50"
    }
```

## `Format`

A format defines how a value should be formated and is directly connected to a type, for example, a string MAY be a format of email, but an integer cannot be a valid email. Formats are [OAS3](https://spec.openapis.org/oas/v3.1.0) specific and supplemented with formats needed in governmental applications (like BSN). Currently available formats are:

An optional format of the Attribute, can be one of the following;

`{ "countryCode", "bsn", "url", "uuid", "email", "phone", "json", "dutch_pc4" }`

This is used for some unique/special validations.
For almost all (current) options the type of this Attribute should be `string`. `dutch_pc4` expects the type `integer`

## `Multiple`

This validation is a `boolean`
`True` if this attribute expects an array of the chosen type instead of one value.
Each value in the array will be validated individually with all other validations.
Default is set to `false`.

## `Object`

This Validation is used for type: `object`
If the attribute expects one or more objects as values, the expected Object Type (also [_Entity_](./MOD_object_types.md)) is defined like this.
The config used for `Object` is a reference to another Object Type.

## `InversedBy`

This Validation is used for type: `object`
If the attribute targets an object, that object might have an `inversedBy` field (Attribute) allowing a two-way connection.
The config used for `InversedBy` is a reference to another Attribute from another Object type (_Entity_)

## `MultipleOf`

This Validation is used for type: `integer`
Specifies a number where the value should be a multiple of, e.g. a multiple of 2 would validate 2,4 and 6 but would prevent 5.

## `Maximum`

This Validation is used for type: `integer`
The maximum allowed value

## `ExclusiveMaximum`

This Validation is used for type: `integer`
This validation is true or false (boolean)
Defines if the maximum is exclusive, e.g. a exclusive maximum of 5 would invalidate 5 but validate 4.
Default set to `null` (this is equal to and should be set to `false`)

## `Minimum`

This Validation is used for type: `integer`
The minimum allowed value

## `ExclusiveMinimum`

This Validation is used for type: `integer`
This validation is true or false (boolean)
Defines if the minimum is exclusive, e.g. a exclusive minimum of 5 would invalidate 5, but validate 6.
Default set to null (this is equal to and should be set to `false`).

## `MaxLength`

This Validation is used for type: `string`
The maximum amount of characters in the value.

## `MinLength`

This Validation is used for type: `string`
The minimal amount of characters in the value.

## `MinDate`

This Validation is used for type: “date” or “datetime”
The minimum date.

## `MaxDate`

This Validation is used for type: `date` or `datetime`
The maximum date.

## `MaxFileSize`

This Validation is used for type: `file`
The maximum allowed file size in bytes

## `MinFileSize`

This Validation is used for type: `file`
The minimum allowed file size in bytes

## `FileTypes`

This Validation is used for type: `file`
The allowed file types (`/mime types`)

## `MaxItems`

This Validation is used for `multiple: true` (an array of the chosen type)
The maximum array length

## `MinItems`

This Validation is used for `multiple: true` (an array of the chosen type)
The minimum array length

## `UniqueItems`

This Validation is used for `multiple: true` (an array of the chosen type)
This validation is true or false (boolean)
Define whether or not values in an array should be unique.
This means that for this attribute multiple values can exist that are exactly the same, but the array of each value contains no duplicates.
(see the difference with `MustBeUnique!`)
Default set to `null` (this is equal to and should be set to `false`)

## `MaxProperties`

This Validation is used for type: `object`
The maximum amount of properties an object should contain

## `MinProperties`

This Validation is used for type: `object`
The minimum amount of properties an object should contain

## `AllOf`

mutually exclusive with using type
An array of possible types that an property should confirm to

## `AnyOf`

mutually exclusive with using type
An array of possible types that an property might confirm to

## `OneOf`

mutually exclusive with using type
An array of possible types that an property must confirm to

## `DefaultValue`

A default value for this Attribute that will be used if no value is supplied.

## `Enum`

An array of possible values, input for this Attribute, is limited to the options in this array.
config expects an array

## `Required`

This validation is true or false (boolean).
Whether or not this Attribute is required
Default set to `null` (this is equal to and should be set to `false`)

## `RequiredIf`

Conditional requirements, this Attribute is required if: config expects an array

## `ForbiddenIf`

Conditional requirements, this Attribute is forbidden if: config expects an array

## `Nullable`

This validation is true or false (boolean)
Whether or not this Attribute can be left empty
Default = null (this is equal to and should actually just be = false)

## `MustBeUnique`

This validation is true or false (boolean)
Define whether or not values of this attribute should be unique.
This means that for this attribute no value exists that is exactly the same.
(see the difference with UniqueItems!)
Default set to `null` (this is equal to and should be set to `false`)

## `CaseSensitive`

This is an extra option for when `MustBeUnique` is used. (this might change and gets used for validation that is concerned about case sensitivity)
This validation is true or false (boolean)
When this is set to false, `MustBeUnique` always sets values to lowercase before checking for duplicates, ignoring uppercase letters in the `unique` check)
Default set to `true`

## `Searchable`

This validation is true or false (boolean)
Whether or not it is allowed to `search and filter` on this Attribute when getting a list (get collection) of the Object Type (`_Entity_`).
Default = false

## `ReadOnly`

This validation is true or false (boolean)
The values for this attribute can only be read. This means that the values for this Attribute must be set on database creation through dataFixtures or DefaultValue must be used. Else the values for this attribute will always be null.
Default set to `null` (this is equal to and should be set to `false`)

## `WriteOnly`

This validation is true or false (boolean)
The values for this attribute can only be written. This means that the values for this Attribute can never be seen with the `/api` endpoint of the gateway. The `/admin` endpoint (admin ui) could/should.
Default set to null (this is equal to and should be set to `false`)

## `Immutable`

This validation is true or false (boolean)
When set to true, the values of this Attribute are no longer allowed to change after creation.
(This means the values of this Attribute can only be set when doing a `POST` request)
Default set to `false`

## `Unsetable`

This validation is true or false (boolean)
When set to true, the values of this Attribute are only allowed to be set after creation.
(This means the values of this Attribute will always be null after doing a `POST` request)
Default = false

## `PersistToGateway`

This is used for type: `object`
This validation is true or false (boolean)
Setting this to `true` will force the values of this Attribute to also be saved and changed in the external Source (`_Gateway_`). By default values are only stored in the Gateway (data warehouse) database;
Default set to false

## `Cascade`

This is used for type: `object`
This validation is true or false (boolean)
Whether or not this Attribute can be used to create new Objects.
Normally you would have to create an Object and then link it to another Object using its `uuid` identifier. But when this is set to true, you can also create the underlying (subresource) Object for this Attribute when you are creating an Object of the Object Type (`_Entity_`) this Attribute is related to.
Default set to false

## `CascadeDelete`

This is used for type: `object`
This validation is true or false (boolean)
Works just like `Cascade`, but on delete instead.
If this is set to true, when an (parent) Object of the Object Type (_Entity_) this Attribute is related to gets deleted, all Values (these are “objects connected to this attribute”) will also be deleted.
Default set to false

## `MayBeOrphaned`

This is used for type: `object`
This validation is true or false (boolean)
If this is set to false, the (parent) Object of the Object Type (_Entity_) this Attribute is related to can not be deleted if this Attribute still has any Values (these are “objects connected to this attribute”).
So the subresources (Objects) have to be deleted first before the parent Object can be deleted.
Default = true

## `ObjectConfig`

This is used for type: `object`
This is used for a very specific offcase where if we have an external API (`Source`) that doesn’t use the `id` field in the body of objects for their primary key. Or if the body or this `id` field is not in the `main/root` of the response but in something like: `envelop.id` instead of just `id`.
Then `ObjectConfig` can be used to configure where the Gateway can find this.
Config for getting the object result info from the correct places (**id is required!**). `envelope` for where to find this item and `id` for where to find the id. (both from the root! So if id is in the envelope example: `[‘envelope’ => ‘instance’, ‘id’ => ‘instance.id’]`)
Default set to `[‘id’ => ‘id’]`

Additional fields on Attributes

## `Entity`

This is the Object Type (Entity) this Attribute is connected to.

## `AttributeValues`

All the values connected to this Attribute. (these can currently not be read when getting an Attribute)

## `DateCreated`

The `dateTime` this Attribute was created.

## `DateModified`

The dateTime this Attribute was last modified.

## `Example`

An example value for this Attribute

## `Deprecated`

Whether or not this property has been deprecated and wil be removed in the future.

## `Description`

A description of this Attribute.

## `Name`

The name of this Attribute. **required** A name for this attribute **MUST** be unique on an entity level and **MAY NOT** be `id`,`file`,`files`, `search`, `fields`, `start`, `page`, `limit`,`extend`,`organization`

## `Id`

The unique `uuid` identifier of this Attribute.
