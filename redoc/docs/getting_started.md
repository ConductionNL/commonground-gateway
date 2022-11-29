# Getting started

The Common Gateway is designed to be developer-friendly and provides a local development environment is an essential part of that experience. Since the Common Gateway is developed [Kubernetes first](https://kubernetes.io/docs/tutorials/kubernetes-basics/) we will assume that you have [Docker Desktop](https://docs.docker.com/desktop/) installed. If you want to play around with the API we also recommend using [Postman](https://www.postman.com/downloads/) or running a local application through [Node.js](https://nodejs.org/en/download/)
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
  
![Gateway interface](https://github.com/ConductionNL/commonground-gateway/blob/master/redoc/assets/dashboard.png?raw=true).

The login is:

login: `test@gateway.local`  
password: `!ChangeMe!`  

__________________________________________________________________________________________________________

## Creating your first objects

__________________________________________________________________________________________________________

Now that we have our Web Gateway up and running let's start by creating some objects to turn it into a working API. The Gateway supports three different ways of configuring:
