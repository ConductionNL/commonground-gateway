# Getting started

The Web Gateway is designed to be developer-friendly, providing a local development environment is an essential part of that experience. Since the Web Gateway is developed (Kubernetes first)[] we will assume that you have (Docker Desktop)[https://docs.docker.com/desktop/] installed. If you want to play around with the API we also recommend using (Postman)[https://www.postman.com/downloads/] or running a local application through (Node.js)[https://nodejs.org/en/download/]

## Basic setup

The Web Gateway supplies a (progressive) web-based app, or PWA, with an AJAX endpoint that handles all the application's actions. In other words, it's the engine under your application. The gateway itself doesn't provide an interface. You can, however, also deploy the getaways dashboard. To handle and store information, the gateway will also need a database, additionally, it uses a separate NGINX container to service web traffic (you can read a bit more about the why under security). Generally speaking, this means that your local setup would look something like this.

Okay, that might sound a bit complex, but keep in mind that containers are just Kubernetes slang for virtual machines, and volumes are comparable to drives. Fortunately, Docker Desktop handles all the complexities of this setup, so let take a look at getting it all up and running.

## Spinning up the gateway

As a Kubernetes open-source software project, all the gateway's images are publicly available for download, this means that we don't need to build any code locally. We simply tell Docker Desktop to pull the images, connect them, attach volumes and spin them up.

This is done through a docker compose file, which looks like this. you can download the example below here

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

You can simply download this file to any directory on your computer, then open up your favorite (command line tool)[https://www.google.com/search?q=command+line+tool] or shell, navigate to the specific folder and tell docker to start your containers with the `docker-compose up` command:

```cli
$ docker-compose up
```

You can now see the containers (virtual machines) come up one by one. Wait until all reports are done, open your browser and navigate to (localhost)[localhost:8000]. You should see a page that looks like this:

[screenhost]

Alternatively (if you just don't like command line interfaces) you can use the Docker desktop UI to spin up the gateway

[screenshot + explanation ]

## Creating you first objects

Now that we have our Web Gateway up and running, let's start by creating some objects to turn it into a working API. The Web Gateway supports three different ways of (configuring)[] it.
