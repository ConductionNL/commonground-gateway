This is how you can configure the automation of object in need of translation.

Let's picture a JSON object(although handlers also accept XML /SOAP):

```json
{
    "monday": "rain",
    "tuesday": "sunny",
    "wednesday": "cloudy",
    "thursday": "blizzard",
    "friday": "hurricane"
}
```

It would be lovely if everyone knew this format and stuck to it, but in reality, the values may change, and even the properties may come in under different names. Instead of "Monday" as a property name, the object may come in with "Maandag" or "Lundi" as a given property name. Luckily, this is where handlers come in; translation handlers do what their name implies and handle a specific functionality. In this situation, handlers would use mapping. Similarly, if the property values need translation, well, the handlers use just that: translation.

All fields except `language` are needed for this functionality.

```json
Properties
_table_:
_language_:
_from_:
_to_:
```
