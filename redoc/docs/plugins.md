# Plugins

You can consider a plugin for the Common Gateway as a configuration set to extend a base Gateway's functionality. The plugin structure is based on the (Symfony bundle system)[https://symfony.com/doc/current/bundles.html]. In other words, all Common Gateway plugins are Symfony bundles, and Symfony bundles can be Common Gateway plugins. 

## Finding and installing plugins

If you start from a brand new Gateway installation and head over to your Dashboard, you can find the `plugin` section on the left side panel. You can search for the plugins you want to add from this tab by selecting `Search for plugins`. Find the plugin you wish to install and view its details page. You should see an install button in the top right corner if the plugin is not installed.  

The Common Gateway finds plugins to install with [packagist](https://packagist.org/). It does this entirely under the hood, and the only requirement is that plugins need a [‘common-gateway-plugin”](https://packagist.org/?query=common-gateway-plugin) tag. Packagist functions as a plugin store as well in this regard.

The plugins are installed, updated, and removed with the [composer](https://getcomposer.org/) CLI. While this feature still exists for developers, we recommend using the user interface [see `plugins`](#plugins) for installing plugins.


## Creating plugins

If you want to develop your plugin, we recommend using the [PetStoreBundle](https://github.com/CommonGateway/PetStoreBundle. This method ensures all necessary steps are taken, and the plugin will be found and installable through the method described above. 

## Updating and removing plugins

If you want to update or remove a plugin, go to `plugins` in the Gateway UI main menu and select `Installed`. Click on the plugin you wish to update or remove and press the `Update` or `Remove button` in the top right corner.

## Adding Schema’s to your plugin

You can include a `schema` folder in the root of your plugin repository containing [schema.json](https://json-schema.org/) files. Whenever the Gateway installs or updates a plugin, it looks for the schema map and handles all schema.json files in that folder as a schema [upload]().

There is an example [here](https://github.com/CommonGateway/CoreBundle/blob/master/Schema/example.json). The `$id` and `$schema` properties are needed for the Gateway to find the plugin. The `version` property's value helps the Gateway decide whether an update is required and will update automatically.

## Adding test data or fixtures to your plugin

You can include both fixtures and test data in your plugin. The difference is that fixtures are required for your plugin to work, and test data is optional. You can include both data sets as .json files in the `Data` folder at the root of your plugin repository. An example is shown [here](https://github.com/CommonGateway/CoreBundle/blob/master/Data/example.json).  

Datasets are categorized by name, e.g., `data.json` in the data folder will be considered a fixture, whereas [anything else].json will be regarded as test or optional data. 

As a fixture, anything in data.json is always loaded on a plugin installation or update. The other files are never loaded on a plugin install or update. However, the user can load the files manually from the plugin details page in the gateway UI. 

All files should follow the following convention in their structure
1 - A primary array indexes on schema `refs`
2 - Secondary array within the primary array containing the objects that you want to upload or update for that specific schema 

```json
{
    [
	"ref1":[
		{"object1"},
		{"object2"},
		{"object3"}
        ],
	"ref2":[
		{"object1"},
		{"object2"},
		{"object3"}
        ],
	"ref3":[
		{"object1"},
		{"object2"},
		{"object3"}
        ]
    ]
}
```

When handling data uploads ( fixtures or other), the Gateway will loop through the primary array and try to find the appropriate schema for your object. If it finds this schema, creates or updates the object for the schema.

For fixtures, this is done in an unsafe manner, meaning that 
When the Gateway can’t find a schema for a reference, it will ignore the reference and continue
The Gateway won't validate the objects (meaning that you can ignore property requirements, but you can't add values for non-existent properties) 

When handling other uploads, the Gateway does so in a safe manner, meaning:
When the Gateway can’t find a schema for a reference, it will throw an error. The Gateway will validate the objects and throw an error when it reaches an invalid object.

## Adding configuration scripts to your plugin

Sometimes, you should be more specific about how you want your plugin to be configured. For example, provide endpoints for your schemas, check if sources exist, or create actions. 

For this, you can add PHP scripts to your plugin that run whenever your plugin is installed, updated, or removed. While you can technically have the code anywhere in your codebase, optimally, it's made as a service. There is an example shown [here](). You will need an [installer](https://github.com/CommonGateway/CoreBundle/blob/master/Installer/InstallerInterface.php) to make it work for the Gateway


