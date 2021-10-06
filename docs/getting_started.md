# Getting started

The Web Gateway is designed to be developer friendly, providing a local development environment is an important part of that experience.  Since the Web Gateway is developed (Kubernetes first)[] we will assume the you have (Docker Desktop)[] installed, if not you can download it from (here)[]. If you want to play around with the api we also recommend using (postman)[] or running a local application trough (node.js)[]

## Basic setup
The web gateway supplies an (progressive) web based app, or PWA, with an ajax endpoint that handles all the actions of an application. With other words its the engine under you application. This means that the gateway itself normally doesn’t provide an interface, you can however optionally choose to also deploy the getaways dashboard. In order to handle and store information the gateway will also need an database, additional is uses an separate NGINX container to service web traffic (you can read a bit more about the why under security). Generally speaking this means that you local setup would look something like this. 



Okey that might sound a bit complex, but keep in mind that containers are just kubernetes slang for virtual machines and volumes are comparable to drive. Furtunalty Docker Desktop handles al the complexities of this setup so let take a look at getting it all up an running 



## Spinning up the gateway
As an kubernetes open source software project al the gateways images are publicly available for downloads, that means that we don’t actually need to build any code locally we simply need to tell Docker Desktop to pull the images, connect them to each other, attach volumes and spin them up.

This is done trough an docker compose file, which looks like this. you can download the example below here

```yaml
version: "3.4"

services:
  php:
    &php
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

  api:
    &nginx
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
````

You can simply download this file to any directory on you computer, then open your favorite (command line too)[]l or shell, (navigate)[] to the specific folder and tell docker to start you containers with de docker-compose up command

```cli
$ docker-compose up
```

You can now see the containers (virtual machines) come up one by one. Wait until the al report ready, open your browser and navigate to (localhost)[] you should see a page that looks like.

[screenhost]

Alternatively (if you just don't like command line interfaces) wou can use the docker desktop ui to spin up the gateway

[screenshot + explanation ]


## Creating you first objects
Okey now that we have our Web Gateway up and running lets start by creating some objects to turn it into an working api. The Web Gateway actually supports three different way of (configuring)[] it.
