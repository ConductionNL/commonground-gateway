Request handlers are the functions that handle the client `request` and construct a response. Response handlers are the functions that handle outgoing `response` bodies.

Handlers functions include mapping, translations and prioritizing firing handlers (read more below)

*Example mapping and translations*: 

The incoming data will be objects of various formats, but the content of these objects may vary as well. To explain it briefly, we will provide you with a "week/weather" analogy.
Let's picture a JSON object(although handlers also accept XML /SOAP):

```json
{ 
"monday" : "rain", 
"tuesday" : "sunny", 
"wednesday" : "cloudy", 
"thursday" : "blizzard", 
"friday" : "hurricane", 
} 
```

It would be lovely if everyone knew this format and stuck to it, but in reality, the values may change, and even the properties may come in under different names. Instead of "Monday" as a property name, the object may come in with "Maandag" or "Lundi" as a given property name. Luckily, this is where handlers come in; handlers do what their name implies and handle a specific functionality. In this situation, handlers would use mapping. Similarly, if the property values need translation, well, the handlers use just that: translation.
Once the mapping/ translation is done correctly, the handlers send the object data to the EAV storage.

If you add multiple handlers and need to prioritize handlers firing off, this can be done with JsonLogic and sequencing.


JSON Logic

With JsonLogic you can set particular rules for the handler to follow, for example we can set a rule like this:

```json
{ "==" : [var, 14] }
```

This way the handler will act only if the outcome of our JsonLogic is true
so if the variable is equal to 14 the handler will be used

from here on you can make it as complex as you want from:
```json
{"and" : [
	  { ">" : [var1, 17] },
	  { "<" : [var2, 1939] }
	] 
}
```
to 

```json
{"or" : [
    {"and" : [
        { ">" : [int, 17] },
        { "<" : [string, 1939] }
    ]}
    {"and" : [
        { ">" : [age, 17] },
        { "==" : [children, 2] }
        ] 
    }
    {"and" : [
        { "==" : [state, "Maryland"] },
        { "!=" : [city, "Wasington DC"] }
    }] 
]}
```
learn more about [JsonLogic](https://jsonlogic.com/).
