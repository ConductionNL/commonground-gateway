# Plugins

The Common Gateway is easily extendable through a plugin structure. The structure is based on the (Symfony bundle system)[https://symfony.com/doc/current/bundles.html] in other words, all Common Gateway plugins are Symfony bundles, and Symfony bundles can be Common Gateway plugins.

If you want to develop your own plugin, you can use the Pet store plugin as a starting point.  If you want develop your own custom plugins, we suggest the Pet store plugin as a starting point

## Finding and installing plugins

Under the hood Common Gateway uses [packagist](https://packagist.org/) to find installable plugins. Any plugin that is tagged with [‘common-gateway-plugin”](https://packagist.org/?query=common-gateway-plugin) is considered an installable plugin. Plugins are installed, updated and removed using [composer](https://getcomposer.org/). Composer is a command line-based solution, meaning that it’s rather impractical for everyday use. The [gateway UI]() therefore offers a visual interface for installing plugins. However, you can still use [composer CLI](https://getcomposer.org/doc/03-cli.md) to manage plugins and packages for your gateway installation.
To install plugins through the UI go to plugins in the menu and select “Search for plugins”. Find the plugin you want to install and view its details page. If the plugin is not installed already, you should see an install button in the top right corner.

## Updating and removing plugins

In case you want to update or remove a plugin, go to plugins in the Gateway UI main menu and select “Installed”. Click on the plugin that you want to update or remove and press the Update or Remove button in the top right of the screen.
## Making your own plugin

You might also want to create your own plugins, if you are interested in that take a look at the Pet Store Template.  

## Adding Schema’s to your plugin

You can include a schema folder in the root of your plugin repository containing schema.json files. Whenever the Gateway installs or updates a plugin it looks for the schema map and handles all schema.json files in that folder as a schema [upload]().

## Adding test data or fixtures to your plugin

You can include both fixtures and test data in your plugin, the difference being that fixtures are required for your plugin to work and test data is optional. You can include both data sets as .json file in the data folder in the root of your plugin repository. Datasets are categorized by their name e.g., data.json in the data folder is considered a fixture,whereas [anything else].json is considered test or optional data. That means that anything in data.json is ALWAYS loaded on a plugin install or update and other files are NEVER loaded on a plugin install or update. However, the user can load the files manually from the plugin details page in the gateway UI. 

All files should follow the following convention in their structure
1 - A primary array indexes on schema refs
2 - Secondary array within the primary array containing the objects that you want to upload or update for that specific schema 

```json
{
    [
	“ref1”:[
		{”object1”},
		{”object2”},
		{”object3”}
],
	“ref2”:[
		{”object1”},
		{”object2”},
		{”object3”}
],
	“ref3”:[
		{”object1”},
		{”object2”},
		{”object3”}
]
]
}
```

When handling data uploads (be it fixtures or other) the gateway will loop through the primary array, try to find the appropriate schema for your object. If it finds this schema, create or update the object for the schema.

For fixtures, this is done in an unsafe manner, meaning that 
When the gateway can’t find a schema for a reference, it will ignore the reference and continue
The gateway won't validate the objects (meaning that you can ignore property requirements, but you can't add values for non-existent properties) 

When handling other uploads the gateway does so in a safe manner, meaning:
When the gateway can’t find a schema for a reference, it will throw an error
The gateway will validate the objects and throw an error when it reaches an invalid object

## Adding configuration scripts to your plugin

In some cases you might want to be more specific about how you want your plugin to be configured. For example you might want to provide endpoints for your schemas, check if sources exist or create actions. 

For this you can add PHP scripts to your plugin that are run whenever your plugin is installed, updated or removed. You can code for your installation anywhere you want (do it is preferable to make it a service). But you will need an installer to make ik work for the gateway. 
