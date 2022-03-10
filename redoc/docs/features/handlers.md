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

If you add multiple handlers and need to prioritize handlers firing off, this can be done with [JsonLogic](https://jsonlogic.com/) and sequencing. Contact Conduction if you require assistence with multiple handler functionality.
