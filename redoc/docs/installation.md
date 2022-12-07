# Installation
_________________________________________________________________________________________________________________________________________


We dever the installation of the gateway between local en server installations, keep in mind that local installations are meant for development, testing en demo purposes ande are (by there nature) not suited for production environments. When installing the gateway for production purposes ALWAYS follow the steps as set out under the server installation manual. 



## Local installation
For our local environment we use [docker desktop](https://www.docker.com/products/docker-desktop/) this allows use to easily spin up virtual machines that mimick production servers. In other words it helps us to make sure that the code that we test/develop locally will also work online. The same can also be said for configurations.

In order to spin up the gateway for local use you will need both docker  [docker desktop](https://www.docker.com/products/docker-desktop/) and a [git client](https://github.com/git-guides/install-git) (we like to use [git kraken](https://www.gitkraken.com/) but any other will suffice, you can also install [git](https://git-scm.com/) on your local machine)

Steps
1. Install docker desktop and git
2. Git clone the common gateway repository to a folder on your machine (if you like to use the command line interface of git thats `git clone 
3. Open a [command line tool](https://developers.google.com/web/shows/ttt/series-2/windows-commandline) and [navigate](https://www.codecademy.com/learn/learn-the-command-line/modules/learn-the-command-line-navigation/cheatsheet) to the folder where you copied the gateway. 
4. Run the command 'docker compose up`
5. You should now see the gateway initiating the virtual machines that it needs on your command line tool. 
6. When it it done you can find the gateway api in your browser under localhost and the gateway ui under [localhost:8000](localhost:8000)


>__Note__:  These steps should not be used for production, please follow server installation for production setups



## Server Installation

There are three main routes to install the Common gateway, but we advice to use helm route for kubernetes environment.

### Installation through composer (Linux / Lamp)
Before starting an linux installation make sure you have a basic LAMP setup, you can read more about that [here](https://www.digitalocean.com/community/tutorials/how-to-install-linux-apache-mysql-php-lamp-stack-ubuntu-18-04). Keep in mind that the gateway also has the following requirements that need to be met before installation.

Linux extensions
Composer
PHP extensions
-
After making sure you meed te requirement you can install the gateway trough the following steps.

In your linux environment create a folder for the gateway ( `cd /var/www/gateway`) navigate to that folder (`cd /var/www/gateway`).
Then run either `composer require common-gateway/core-bundle` or the composer require command for a specific plugin.


### Installation trough docker compose
The gateway repository contains a docker compose, and an .env file containing all setting options. These are the same files that are used for the local development environment. However when using this route to install the gateway for production you **Must** set the `APP_ENV` variable tp `PROD` (enabling caching and security features) and you must change al passwords  (convieantly labeld _!ChangeMe!_) **NEVER** run your database from docker compose, docker compose is non persist and you will lose your data. Alway use a separate managed database solution. 

### Installation trough Helm charts


## Production
The gateway is designd to operate differently in a production, then in a development environment. The main difference being the amount of cashing and some security setting.

## Cronjobs and the cronrunner
The gateway uses cronjobs to fire repeating event (like synchronisations) at certain intervals. Users can set the up and maintain them trough the admin ui. However cronjobs themselves are fired trough a cronrunner, meaning that there is a script running that checks every x minutes (5 by default) wheter there are cronjobs that need to be fired. That means that the execution of cronjob is limited by the rate set in the cronrunnen .e.g if the cronrunner runs every 5 minutes its impossible to run cronjobs every 2 minutes.

For docker compose and helm installation the cronrunner is is based on the linux crontab demon and included in het installation scripts. If you are however installing the gateway manually you wil need to setup your own crontab to fire every x minutes. 

 In the normal setup these cronjobs are fired every 5 minutes., 

/srv/api bin/console cronjob:command

## Setting up plugins
