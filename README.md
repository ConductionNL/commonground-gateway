# Commonground Gateway
[![Artifact Hub](https://img.shields.io/endpoint?url=https://artifacthub.io/badge/repository/commonground-gateway)](https://artifacthub.io/packages/search?repo=commonground-gateway)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/b6de6f6071044e1783a145afa27f1829)](https://www.codacy.com/gh/CommonGateway/CoreBundle/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=CommonGateway/CoreBundle&amp;utm_campaign=Badge_Grade)

The common gateway repositroy gives a quick kubertnetes wrapper of the [common gateway symfony bundle](https://github.com/CommonGateway/CoreBundle).

## Quick start (for local development)

You can start up the commongatway locally by cloning the respository to you machine
````cli
$ git clone https://github.com/ConductionNL/commonground-gateway
````

Afther that you need to spin upyour local docker setup 

````cli
$ docker compose up --build
````

(the --build flag can be ignored the next time you want to run the application)


## Quick start (for kubernetes)
