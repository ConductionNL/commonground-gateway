# Providing API’s (Application programming interface)
The gateway provides an API for other applications to use and consumes API’s from sources in that way the gateway acts both as an provider and consumer of API’s. How to consume API’s from the gateway is further detailed under the Sources chapters. This chapter deals with providing API’s from the gateway to other applications

## Creating Applications


## Users, Processes an Applications
From a logging perspective the gateway differs any call on the aspects
- Who is making the call? e.g. User John
- How is he making the call? e.g. from the front desk applications
- For which process is he making the call? e.g. client registration

While it isn’t necessary to alway use al of the above settings it is het preferred way of settup

## Querying the API

{propertyNema}={searchValue} e.g. `firstname=john`

or 

{propertyNema}[method]={searchValue}


## Methods

method less queries (e.g. `firstname=john`) are treated as exact methods `firstname[exact]=john`

* [exact] exact match* 
Only usable on properties of the type `text`,  `integer` or `datetime`

* [like] wildcard search* 
Only usable on properties of the type `text`,  `integer` or `datetime`

* [>=] greater than* 
Only usable on properties of the type `integer`

* [<=] smaller than* 
Only usable on properties of the type `integer`

* [afther] smaller than*
Only usable on properties of the type `date` or `datetime`

* [before] smaller than* 
Only usable on properties of the type `date` or `datetime`




## Ordering the results

## Working with pagination

## Limiting the return data
In some cases you either don’t need or don’t want an complete object. In those cases its good practice for the consuming application to limit the field it wants in its return call. This makes the return messages smaller (and therefore faster) but it is also more secure because it prevents the sending and retention of unnecessary data.

The returned data can be limited using the _fields query parameter, this parameter is expected to be an array containing the name of the properties  that are requested. It is possible to include nested properties using dot notation. Let’s take a look at the following example.  We have a person object containing the following data:


```json
{	
	 “firstname”:”John”,
	 “lastname”:”Do”,
	“born”:{
		“city”:“Amsterdam”,
		“country”:”Netherlands”,
”date”:”1985-07-27”	
	}
}
```

Of we then query using ` _fields[]=firstname&_fields[]=born.date` we would expect the following object to be returned:



```json
{	
	 “firstname”:”John”,
	“born”:{
”date”:”1985-07-27”	
	}
}
```

Note: The _fields property may be aliased as _properties

## Specifying the data format
The gateway can deliver data from its data layer in several formats, those formats are independent from there original source. e.g. A source where the gateway consumes information from might be XML bassed but the gateway could than provide that information in JSON format to a different application.

The data format is defined by the application requesting the data trough the `Accept` header. 

## Mapping the data (transformation)

## Working with pagination
 
