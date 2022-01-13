# Commonground Gateway

[![Documentation Status](https://readthedocs.org/projects/commonground-gateway/badge/?version=latest)](https://commonground-gateway.readthedocs.io/en/latest/?badge=latest)
[![BCH compliance](https://bettercodehub.com/edge/badge/ConductionNL/commonground-gateway?branch=master)](https://bettercodehub.com/)

## Quick start

Clone this repository (green button up) or use in the terminal

```cli
$ git clone https://github.com/ConductionNL/commonground-gateway

cd commonground-gateway
```

To be able to run the Commonground Gateway API - which is build and run as a container - you will need to have Docker
installed to your machine. If you do not have Docker installed to your machine, please head to [Docker](https://docs.docker.com/get-started/) and choose the installation method for your machine.

Then, use the following command in your terminal:

```cli
$ docker-compose up
```

This will build the container image needed to run the Commonground Gateway locally. You will also need to run the frontend part of the [Commonground Gateway](https://www.mkdocs.org/user-guide/installation/).

## Documentation

Full technical documentation is provided on [read the docs](https://commonground-gateway.readthedocs.io/) and is based on [MKDocs](https://www.mkdocs.org/). A more product owner focused (and less technical) product page is hosted at []().

If you want to run the technical documentation locally, you can do so by using MKDocs build server and the serve command. Just go to the local repository and execute the following command for the documenation to be available on port 8000 (make sure to [install MKDocs](https://www.mkdocs.org/user-guide/installation/) first)

```cli
$ mkdocs serve
```

The (non-)technical product page is based on Gatsby and can also be published locally, just navigate to the repository and then to the docs directory. (make sure to [install Gatsby CLI](https://www.gatsbyjs.com/docs/tutorial/part-0/#gatsby-cli) on your machine first though)

```cli
$ cd /product-page
$ gatsby develop
```

It's also possible to update the product page directly from the repository by running the pre-configured deploy command for the product-page directory, or

```cli
$ cd /product-page
$ npm run deploy
```

<!--
## Update -->
