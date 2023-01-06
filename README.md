# Commonground Gateway
[![Documentation Status](https://readthedocs.org/projects/commonground-gateway/badge/?version=latest)](https://commonground-gateway.readthedocs.io/en/latest/?badge=latest)
[![BCH compliance](https://bettercodehub.com/edge/badge/ConductionNL/commonground-gateway?branch=master)](https://bettercodehub.com/)

## Quick start

## Documentation
Full technical documentation is provided on [read the docs](https://commonground-gateway.readthedocs.io/) and is based on [MKDocs](https://www.mkdocs.org/). An more product owner focused (and lesd tehcnical) product page is hosted at [](). 

If you want to run the technical documantation localy you van do so by using MKDocs build in server and the serve command. Just go to the local reposiroty and execute the fowwing command for the documenation to be available on post 8000 (make sure to [instal MKDocs](mkdocs.org/user-guide/installation/) first)
```cli
$ mkdocs serve
```

The (non-)technical product page is based on gatsby and can als be published localy, jsut navigate to the repostory and then to de docs directory. (make sure to [install gatsby CLI](https://www.gatsbyjs.com/docs/tutorial/part-0/#gatsby-cli) on you machine first doh) 
```cli
$ cd /product-page
$ gatsby develop
```

Its is also posible to update the product page dicetly from the reposotry by running the pre-configures deploy command for the product-page direcotry, or 
```cli
$ cd /product-page
$ npm run deploy
```

## Update
      
