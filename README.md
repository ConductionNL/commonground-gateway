# Commonground Gateway

[![Documentation Status](https://readthedocs.org/projects/commonground-gateway/badge/?version=latest)](https://commonground-gateway.readthedocs.io/en/latest/?badge=latest)
[![BCH compliance](https://bettercodehub.com/edge/badge/ConductionNL/commonground-gateway?branch=master)](https://bettercodehub.com/)

## Quickstart

Clone this repository (green button up) or use it in the terminal

```cli
$ git clone https://github.com/ConductionNL/commonground-gateway
cd commonground-gateway
```

To run the Commonground Gateway API - built and run as a container - you will need to have Docker
installed. If you do not have Docker installed on your machine, please head to [Docker](https://docs.docker.com/get-started/) and choose the installation method for your device.

Use the following command in your terminal:

```cli
$ docker-compose up
```

The container image needed to run the Commonground Gateway builds locally.

## Documentation

Full technical documentation is provided on [read the docs](https://commonground-gateway.readthedocs.io/) and is based on [MKDocs](https://www.mkdocs.org/). A more product owner-focused (and less technical) product page is available at [link to be added]().

If you want to run the technical documentation locally, you can use the MKDocs build server and run the `mkdocs serve` command. Go to the local repository and execute the following command for the documentation to be available on port 8000 (make sure to [install MKDocs](https://www.mkdocs.org/user-guide/installation/) first)

```cli
$ cd docs
$ mkdocs serve
```

The (non-)technical product page is based on Gatsby and can also be published locally. Navigate to the `docs` directory and run the command below (make sure to have [Gatsby CLI](https://www.gatsbyjs.com/docs/tutorial/part-0/#gatsby-cli) installed on your machine).

```cli
$ cd /product-page
$ gatsby develop
```

It's also possible to update the product page directly from the repository by running the pre-configured deploy command for the product-page directory, or

```cli
$ cd /product-page
$ npm run deploy
```
