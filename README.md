# Commonground Gateway

[![Documentation Status](https://readthedocs.org/projects/commonground-gateway/badge/?version=latest)](https://commonground-gateway.readthedocs.io/en/latest/?badge=latest)


## Quickstart

Clone this repository (green button ) or use the following command in the terminal:

```cli
$ git clone https://github.com/ConductionNL/commonground-gateway

cd commonground-gateway
```

To run the Commonground Gateway API - built and run as a container - you will need to have Docker
installed on your machine. If you do not have Docker installed on your machine, please head to [Docker](https://docs.docker.com/get-started/) and choose the installation method for your device.

Then, use the following command in your terminal:

```cli
docker-compose up
```

This command will only build the container image needed to run the Commonground Gateway locally. You will also need to run the frontend part of the [Commonground Gateway](https://github.com/ConductionNL/commonground-gateway-frontend). EDIT: due to an update, running the frontend alone is sufficient. It has the gateway included.

## Documentation

Full technical documentation is provided on [read the docs](https://commonground-gateway.readthedocs.io/) and is based on [MKDocs](https://www.mkdocs.org/). A more product owner-focused (and less technical) product page can be found here [link to be added]().

If you want to run the technical documentation locally, you can use MKDocs' build server, and the `mkdocs serve` command. Navigate to the local repository and execute the following command for the documentation to be available on `port 8000` (make sure to [install MKDocs](https://www.mkdocs.org/user-guide/installation/) first)

```cli
mkdocs serve
```

The (non-)technical product page is based on Gatsby and can also be published locally, head over to the repository, and then to the docs directory. (make sure to [install Gatsby CLI](https://www.gatsbyjs.com/docs/tutorial/part-0/#gatsby-cli) on your machine first, though)

```cli
cd /product-page
gatsby develop
```

It's also possible to update the product page directly from the repository by running the pre-configured deploy command for the product-page directory, or

```cli
cd /product-page
npm run deploy
```
