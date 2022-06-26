# Getting started

The Common Gateway is designed to be developer-friendly and provides a local development environment is an essential part of that experience. Since the Common Gateway is developed [Kubernetes first](https://kubernetes.io/docs/tutorials/kubernetes-basics/) we will assume that you have [Docker Desktop](https://docs.docker.com/desktop/) installed. If you want to play around with the API we also recommend using [Stoplight](stoplight.io/) or running a local application through [Node.js](https://nodejs.org/en/download/)

__________________________________________________________________________________________________________

## Basic setup

_________________________________________________________________________________________________________

The Common Gateway supplies a (progressive) web-based app, or PWA, with an AJAX endpoint that handles all the application's actions. In other words, it's the engine under your application. The Gateway itself doesn't provide an interface. You can, however, also deploy a dashboard. To handle and store information, the Gateway will also need a database. Additionally, it uses a separate NGINX container to service web traffic (you can read a bit more about the why under security). Generally speaking, your local setup would look something like this:

Okay, that might sound a bit complex, but keep in mind that containers are just Kubernetes slang for virtual machines, and volumes are comparable to drives. Fortunately, Docker Desktop handles all the complexities of this setup, so let's look at getting it all up and running.
__________________________________________________________________________________________________________

## Spinning up the Gateway

__________________________________________________________________________________________________________

As a Kubernetes open-source software project, all the Gateway's images are publicly available for download, which means that we don't need to build any code locally. We simply tell Docker Desktop to pull the images, connect them, attach volumes and spin them up.

This is done through a `docker-compose` file (example below). The latest version is pulled automatically with your repository.

```yaml
version: "3.4"

services:
    php: &php
        image: conduction/commonground-gateway-php:dev
        depends_on:
            - db
        volumes:
            - ./gateway:/srv/api/fixtures:rw,cached
        environment:
            #- DATABASE_URL=postgres://api-platform:!ChangeMe!@db/api?serverVersion=10.1
            - DATABASE_URL=mysql://api-platform:!ChangeMe!@db/api?serverVersion=10.1
            - AUTH_ENABLED=false
            - REDIS_HOST=redis
            - REDIS_PORT=6379
            - APP_REPRO=example
        ports:
            - "82:80"

    api: &nginx
        image: conduction/commonground-gateway-nginx:latest
        depends_on:
            - php
        ports:
            - "80:80"
        environment:
            - NGINX_HOST=php

    db:
        image: mysql:5.7
        environment:
            - MYSQL_ROOT_PASSWORD=example
            - MYSQL_DATABASE=api
            - MYSQL_USER=api-platform
            - MYSQL_PASSWORD=!ChangeMe!
        volumes:
            - db-mysql:/var/lib/mysql:rw
        ports:
            - "3366:3306"

volumes:
    db-data: {}
    db-mysql: {}
```

You can download this file to any directory on your computer, open up your [command line tool](https://www.google.com/search?q=command+line+tool) or shell, and tell Docker to start your containers with the `docker-compose up` command from the folder you just downloaded the yaml file to.

```cli
docker-compose up
```

You will see the containers (virtual machines) come up one by one. Wait until all reports are done and a `ready to handle connections` message appears. Open your Docker Desktop to verify all containers are up and running.

Alternatively, you can spin up the frontend from [this repository](https://github.com/ConductionNL/commonground-gateway-frontend) and have a GUI along with the Gateway engine.  
  
![Gateway interface](https://github.com/ConductionNL/commonground-gateway/blob/master/redoc/assets/dashboard.png).

The login is:

login: `test@gateway.local`  
password: `!ChangeMe!`  

__________________________________________________________________________________________________________

## How to pull APIs

__________________________________________________________________________________________________________

The Common Gateway is n API gateway. So what good is it without using APIs? Pulling APIs into the Gateway is where the fun starts/

There are two methods to pulling APIs into the Gateway:

1. via the App store
2. via `docker-compose`

Both methods are explained in detail in this chapter.

- option 1 will be available shortly after coming out of beta. You can contact Conduction to see the feature in development.
- option 2 is described in full in the folowing tutorial and can be found [here](https://github.com/CommonGateway/PetStoreAPI)

___________________________________________________________________________________________________________

## How to prepare APIs to load them into the Common Gateway

____________________________________________________________________________________________________________

 APIs can be pulled into the Gateway, but some alteration may need to be done before compatibility. The Gateway uses the [OpenAPI Specification(OAS)](https://swagger.io/specification/). The OAS defines a standard,
 language-agnostic interface to RESTful APIs, allowing humans and computers to discover and understand the service's capabilities without access to source code, documentation, or network traffic inspection.
 When properly defined, a consumer can understand and interact with the remote service with minimal implementation logic.

Documentation generation tools can then use an OpenAPI definition to display the API, code generation tools to generate servers and clients in various programming languages, testing tools, and many other use cases.

More information on how to prepare the APIs can be found in the Petstore tutorial [here](https://github.com/CommonGateway/PetStoreAPI)

___________________________________________________________________________________________________________________

## How to pull in functionality into the Gateway

___________________________________________________________________________________________________________________

Besides APIs other functionality can be pulled into the Gateway. There are two methods to pulling APIs into the Gateway:

1. via the Plug-ins (see below)
2. via `docker-compose`

Altering your Docker Images will add functionality to the Gateway. Through this method, updates are released in the future as well. Fork the codebase and alter
the `api/composer.json` for additional functionality.

- FOrks and Images (docker / composer.json)

- Voorbeeld

_________________________________________________________________________________________________________________

## How to pull in plugins into the Gateway

_________________________________________________________________________________________________________________

This is where the technology starts to get interesting. The feature is still in beta, but keep coming back to check when this feature is released!

### Preparing plugins

- Work in progress

_____________________________________________________________________________________________________________

## How to install the Common Gateway online

___________________________________________________________________________________________________________

Working locally is great for testing, but to really make the technology available to the world, an online employment is needed. This chapter describes the step needed for online deployment.

To deploy an online enviroment and version of the Common Gateway, a few things are needed:

## Minimal system requirements for your cluster

- Kubernetes 1.16 of later
- A minimum 3 nodes
- 4 vCPUs per node
- 4 GB RAM per node
- 50 GB disk space per node

## Kubernetes Providers

There is a number of Kubernetes providers that are suitable to run CommonGround components. Most notable are:

- [Digital Ocean](https://digitalocean.com)
- [Google Cloud](https://cloud.google.com)
- [Amazon Web Services](https://aws.amazon.com)

Beyond that, there's a extensive walktrough that is found [here](https://github.com/ConductionNL/conduction-ui/blob/master/INSTALLATION.md)

______________________________________________________________________________________________

## CI /CD

______________________________________________________________________________________________

CI and CD stand for continuous integration and continuous delivery/continuous deployment. In simple terms, CI is a modern software development practice in which incremental code changes are made frequently and
 reliably. Automated build-and-test steps triggered by CI ensure that code changes being merged into the repository are reliable. The code is then delivered quickly and seamlessly as a part of the
 CD process. The CI/CD pipeline refers to the automation that enables incremental code changes from developers to be delivered quickly and reliably to production.

This chapter will describe the steps needed for the automation flows.

- Work in progress
