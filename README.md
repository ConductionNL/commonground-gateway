# Common Gateway
[![Artifact Hub](https://img.shields.io/endpoint?url=https://artifacthub.io/badge/repository/commonground-gateway)](https://artifacthub.io/packages/search?repo=commonground-gateway)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/b6de6f6071044e1783a145afa27f1829)](https://www.codacy.com/gh/CommonGateway/CoreBundle/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=CommonGateway/CoreBundle&amp;utm_campaign=Badge_Grade)

The Common Gateway repository provides a quick Kubernetes wrapper for the Common Gateway Symfony Bundle. In other words, it doesn't aim to be its own code base but simply contains the files needed to create Kubernetes images and Helm installers for the core bundle.

If you are looking for the Common Gateway code base, please refer to the Core Bundle repository instead, as that's where you will find all the appropriate documentation.

## Quick start (for local development)
> **Dependencies**
> - To clone the codebase to your locale machine you will need Git
> - To run the gateway on your local machine, you will need Docker Desktop.

You can start up the Common Gateway locally by cloning the repository to your machine
````cli
$ git clone https://github.com/ConductionNL/commonground-gateway
````

After that, you need to spin up your local Docker setup

````cli
$ docker compose up --build
````

(the `--build` flag can be ignored the next time you want to run the application)

The common gateway control panel can be found on http://localhost:8000 on default setups.

The default credentials for the common gateway are:<br>
Username: no-reply@test.com<br>
Password: !ChangeMe!

> **Using Ubuntu?**<br>
> Create the folder vendor in the api folder and run the following commands:
>
> `chmod 777 -R api/vendor api/public api/var api/config`<br>
> `chmod 777 api/composer.lock api/composer.json api/src/Logger/SessionDataProcessor.php`
>
> Docker and Ubuntu manage to create some permission collisions on the gateway unfortunately.

## Quick start (for Kubernetes)
> **Dependencies**
> - For installations on Kubernetes, you will need to install Helm on your local machine.
> - If you need to install LetsEncrypt, you will also need to install `kubectl`, but if you previously installed Docker Desktop then `kubectl` is already present on your machine (so no installation needed).

### Haven installations
If you are installing the Common Gateway on a Haven environment, you can just run the provided Helm installer. Before installing the Common Gateway, you need to add it to your Helm repositories.

````helm
$ helm repo add common-gateway https://raw.githubusercontent.com/ConductionNL/commonground-gateway/main/api/helm/
````


After that, you can simply install the Gateway to your cluster using the Helm installer. If you are using a kubeconfig file to authenticate yourself, you need to add that as a --kubeconfig flag. However, if you are using another authentication method, you can omit the flag. When installing an application to Helm, always choose a name that helps you identify the application and put that in place of [my-installation].


````helm
$ helm install [my-gateway] common-gateway/commonground-gateway --kubeconfig=[path-to-your-kubeconfig] --namespace [namespace]
````

This will install the Gateway in a bare-bones and production setup on the latest version (you can lock versions through the version flag e.g. `--version 2.2)`. To further configure the Gateway, we need to set some environmental values. A full option list can be found [here](). Let's for now assume that you want to switch your Gateway from prod to dev-mode, enable cron-runners for asynchronous resource handling, add a domain name to make the Gateway publicly accessible, and last but not least, use LetsEncrypt to provide an SSL/HTTPS connection. In Helm, we use the `--set` flag to set values. So the total upgrade command would look like:

````helm
$ helm upgrade [my-gateway] --kubeconfig=[path-to-your-kubeconfig] --set  gateway.enabled=true, pwa.apiUrl=https://[your-domain]/api, pwa.meUrl=https://[your-domain]/me, pwa.baseUrl=https://[your-domain], pwa.frontendUrl=https://[your-domain], pwa.adminUrl=https://[your-domain], ingress.enabled=true, global.domain=[your-domain], ingress.hostname=[your-domain], ingress.annotations.cert-manager\.io/cluster-manager=letsencrypt-prod, ingress.tls.0.hosts.0=[your-domain], ingress.tls.0.secretName=[your-domain but replace . with -]-tls, gateway.cronrunner.enabled=true
````

Or for the headless version

````helm
$ helm upgrade [my-gateway] common-gateway/commonground-gateway --kubeconfig=[path-to-your-kubeconfig] --set cronrunner.enabled=true,php.tag=dev,nginx.tag=dev,ingress.enabled=true,global.domain=[my-domain.com]
````

The helm install and upgrade commandos can also be put together:

````helm
$ helm upgrade [my-gateway] common-gateway/commonground-gateway --kubeconfig=[path-to-your-kubeconfig] --set cronrunner.enabled=true,php.tag=dev,nginx.tag=dev,ingress.enabled=true,global.domain=[my-domain.com] --namespace [namespace] --install
````

Alternatively, you can use the Kubernetes dashboard to change the Helm values file.

For more information on installing the application through Helm, read the [Helm documentation](https://helm.sh/docs/helm/).

### Non-Haven installations
If however, your environment is not compliant with the Haven standard (and we suggest that you follow it), then keep in mind that you need the following dependencies on your Kubernetes clusters:

**MUST have**
- [nfs](https://artifacthub.io/packages/helm/kvaps/nfs-server-provisioner) 
- [Ingress-nginx](https://artifacthub.io/packages/helm/ingress-nginx/ingress-nginx)

**Should have**
- [Cert manager](https://artifacthub.io/packages/helm/cert-manager/cert-manager) (add `--set installCRDs=true` to the command if using Helm, you will need them for LetsEncrypt)
- LetsEncrypt (see details below)
- Prometheus
- Loki


### Activating LetsEncrypt. 

If you want your cluster to be able to set up its own certificates for SSL/HTTPS (and save yourself the hassle of manually loading certificates), you can install LetsEncrypt. Keep in mind that this will provide low-level certificates. If you need higher-level certificates, you will still need to manually obtain them.

**Note: Make sure to install Cert Manager before LetsEncrypt**

````helm
$ kubectl apply -f letsencrypt-ci.yaml --kubeconfig=[path-to-your-kubeconfig]
````

