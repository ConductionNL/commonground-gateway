# Mapping

Mapping is the process of changing the structure of an object. For example, an object is either sent to or retrieved from an external source. This always follows in the Output <- mapping <- Input model. Mapping is beneficial when the source doesn't match the data model you want to use.

The gateway performs mapping as a series of mapping rules handled in order. Mapping rules are written in a To <- From

### Simple mapping

In its simplest form, a mapping consists of changing the position of a value within an object. A simple mapping does this mutation To <- From order. Or in other words, you describe the object you want using the object (or other data) you have

So let's see a simple mapping rule with a tree object like this

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "Chestnut", 
  "location":"Orvil’s farm"
}
```

And we conclude that we need to move the data in the `description` field to a `species` field to free up the description field for more generic data. Which we then also want to fill. In short move a value to a new position and insert a new value in the old position. We can do that with the following two mapping rules.

```json
{
    "species":"description", 
    "description":"This is the tree that granny planted when she and gramps got married",
}
```

Which will give us

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

So what happened under the hood? And why is one value executed as a movement rule and the second as a string? Let's take a look at the first rule

```json
{
    "species":"description" 
}
```

Rules carry out as a `To <- From` pair. In this case, the `species` has a `description` key. When interpreting what the description is, the mapping service has two options:

* The value is either a dot notation array pointing to another position in the object (see dot notation). If so, then the value of that position is copied to the new position
* The value is not a dot notation array to another position in the object (see dot notation), then the value is rendered as a twig template

### Twig mapping

Another means of mapping is Twig mapping. Let's look at a more complex mapping example to transform or map out data. We have a tree object in our data layer  looking like

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big white tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "location":"Orvil’s farm", 
  "species":"Chestnut"
}
```

Now the municipality opened up a county-wide tree register, and we would like to register our tree there. The municipality decided to move locations and species of the tree into metadata array data and thus expects an object like this

```json
{
  "id":"0d671e30-04af-479a-926a-5e7044484171",
  "name":"The big old tree",
  "description": "This is the tree that granny planted when she and gramps got married", 
  "metadata":{
    "location":"Orvil’s farm", 
    "species":"Chestnut"
  }
}
```

Ok, so let's put our mapping to the rescue!

A mapping always consists of an array where the array keys are a dot notation of where we want something to go. And a value representing what we want to go there. That value is a string that may contain twig logic. In this twig logic, our original object is represented under de source object. In this case we could do a mapping like

```json
{
    "metadata.location":"{{source.location }}", 
    "metadata.species":"{{source.species }}",
}
```

We would then end up wit a new object (after mapping) looking like

 ```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married", 
    "location":"Orvil’s farm", 
    "species":"Chestnut", 
    "metadata":{
        "location":"Orvil’s farm",
        "species":"Chestnut"
    }
}
```

Both dot-notation and twig-based mapping are valid to move value's around in an object. Dot-notation is preferred performance-wise.

### Dropping keys

Twig mapping is an improvement, but it's not quite there yet, because mapping alters the source object rather than replacing it. Optimally, we map and drop the data we won't need. We can do that under the `_drop` key. The `_drop` key accepts an array of dot notation to drop. Let's change the mapping to

```json
{
    "metadata.location":"{{source.location }}", 
    "metadata.species":"{{source.species }}",
    "_drop":[
        "location",
        "species"
    ]
}
```

We now have an object that’s

```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description":"This is the tree that granny planted when she and gramps got married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut"
  }
}
```

**Note** droping keys is always the last action perfomed in the mapping procces.

### Adding keys

The mapping setup allows you to add keys and values to objects simply by declaring them. Let's look at the above example and assume that the county wants us to enter the primary color of the tree. A value that we don't have in our object. Assume all our trees to be brown. We could then edit our mapping to

```json
{
    "metadata.location":"{{source.location }}", 
    "metadata.species":"{{source.species }}",
    "metadata.color":"Brown",
    "_drop":[
        "location",
        "species"
  ]
}
```

Which would return

```json
{
    "id":"0d671e30-04af-479a-926a-5e7044484171",
    "name":"The big white tree",
    "description": "This is the tree that granny planted when she and gramps got married", 
    "metadata":{
        "location":"Orvil’s farm", 
        "species":"Chestnut", 
        "color":"Brown"
  }
}
```

...even though we didn't have a color value initially.

Also, note that we used a simple string value here instead of twig code. That's because the twig template may contain strings.

### Working with conditional data

Twig natively supports many [logical operators](https://twig.symfony.com/doc/3.x/templates.html), but a few of those are exceptionally handy when dealing with mappings. For example, concatenating
strings like {{ 'string 1' ~ 'string 2' }} which can be used as the source data inside the mapping

```json
{
    "color": "{{ \"The color is \" ~ source.color }}"
}
```

The same is achieved with [string interpolation](https://twig.symfony.com/doc/1.x/templates.html#string-interpolation) via

```json
{
    "color": "{{ \"The color is {source.color}\" }}"
}
```

So both of the above notations would provide the same result

Another useful twig take is the if statement. This can be used to check if a values exists in the first place

```json
{
    "color": "{% if source.color %} source.color {{ else }} unknown {{ endif }} }}"
}
```

or to check for specific values

```json
{
    "color": "{% if source.color == \"violet\" %} pink {{ endif }} }}"
}
```

### Conditional mapping (beta)

There are cases when a mapping rule should run if conditions are met. This is called conditional mapping. To make a mapping conditional, add a `|` to the mappings key followed by a JSON Condition description. The mapping will only execute if the JSON condition is met (results in true):

```json
{
    "color|{\"source.color\" : [\"blue\"]}": "{{ source.color }}",
    "metadata.color|{\"source.color\" : [\"blue\"]}": "{{ source.color }}"
}
```

Currently, it is impossible to build `if/or/else` cases with mappings. A single mapping rule is either run or not

### Working with array

Its currently not possible to re-map an array (change the keys within an array), but you can map an array into its entirety into a key that expects an array by bypassing the twig handler e.g.

```json
{
    "metadata":{
        "branches": [
            {
                "id":"475342cd-3551-4d55-aa4e-b734c1fda3f7",
                "name":"one"
                
            },
            {
                "id":"18e9d8be-32e5-4bec-93f2-55d60444fc4e",
                "name":"two"
            },
            {
                "id":"3ba4f1be-be2e-4e1a-ae83-04fed18fdf29",
                "name":"three"
            }
        ]
    }
}
```

Under mapping

```json
{
  "roots": "metadata.branches",
  "_drop": ["metadata.branches"]
}
```

Would become

```json
{
    "roots": [
        {
            "id":"475342cd-3551-4d55-aa4e-b734c1fda3f7",
            "name":"one"
            
        },
        {
            "id":"18e9d8be-32e5-4bec-93f2-55d60444fc4e",
            "name":"two"
        },
        {
            "id":"3ba4f1be-be2e-4e1a-ae83-04fed18fdf29",
            "name":"three"
        }
    ]
}
```

### Forcing the type/format of values

Due to twig rendering, mapping output will always change all the values to `string`. For internal gateway traffic, this isn’t problematic, as the data layer will cast values to the appropriate outputs. When sending data to an external source, having all Booleans cast to strings might be bothersome. To avoid this predicament, force the datatype of your values yourself by adding `|[format]` behind your mapping value e.g., `|string` or `|integer`. Beware what functions PHP uses to map these values and if the cast should be possible (or else an error is thrown).

| Cast            | Function                                                  | Twig   |
|---------------- |---------------------------------------------------------- |--------|
| string          | <https://www.php.net/manual/en/function.strval.php>         | No     |
| bool / boolean  | <https://www.php.net/manual/en/function.boolval.php>        | No     |
| int / integer   | <https://www.php.net/manual/en/function.intval.php>         | No     |
| float           | <https://www.php.net/manual/en/function.floatval>           |  No     |
| array           |                                                           | No     |
| date            | <https://www.php.net/manual/en/function.date>               |  No     |
| url             | <https://www.php.net/manual/en/function.urlencode.php>      |  Yes   |
| rawurl          | <https://www.php.net/manual/en/function.rawurlencode.php>   |  Yes   |
| base64          | <https://www.php.net/manual/en/function.base64-encode.php>  |  Yes   |
| json            | <https://www.php.net/manual/en/function.json-encode.php>    |  Yes   |
| xml             |                                                           |  No     |

Example a mapping of

```json
{
  ..
    "metadata.hasFruit": "Yes|bool",
  ..
}
```

Would return

```json
{
  ...
    "metadata":{
      ...
      "hasFruit":true
  }
}
```

Notice that format adjustments should be made outside the `{{`and `}}` twig brackets to overwrite the twig string output

### Translating values

Twigg natively supports [translations](https://symfony.com/doc/current/translation.html),  but remember that translations are an active filter `|trans`. And thus should be specifically called on values you want to translate. Translations are performed against a translation table. You can read more about configuring your translation table here.

The base for translations is the locale, as provided in the localization header of a request. When sending data, the base is in the default setting of a gateway environment. You can also translate from an specific table and language by configuring the translation filter e.g. {{ 'greeting' | trans({}, `[table_name]`, `[language]`) }}

Original object

```json
{
    "color":"brown"
}
```

With mapping

```json
{
    "color":"{{source.color|trans({},\"colors\") }}"
}
```

returns (on locale nl)

```json
{
    "color":"bruin"
}
```

If we want to force German (even if the requester asked for a different language), we'd map like

```json
{
    "color":"{{source.color|trans({},\"colors\".\"de\") }}" 
}
```

And get

```json
{
    "color":"braun"
}
```

### Renaming Keys

The mapping doesn't support the renaming of keys directly but can rename keys indirectly by moving the data to a new position and dropping the old position

Original object

```json
{
    "name":"The big old tree"
}
```

With mapping

```json
{
    "naam":"{{source.name }}",
  "_drop":[
      "name"
  ]
}
```

returns

```json
{
    "naam":"The big old tree"
}
```

### What if I can't map?

Even with all the above options, it might be possible that the objects you are looking at are too different to map. In that case, don't look for mapping solutions. If objects A and B are too different, add them to the data layer and write a plugin to keep them in sync based on actions.
