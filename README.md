# Common Gateway
[![Artifact Hub](https://img.shields.io/endpoint?url=https://artifacthub.io/badge/repository/commonground-gateway)](https://artifacthub.io/packages/search?repo=commonground-gateway)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/b6de6f6071044e1783a145afa27f1829)](https://www.codacy.com/gh/CommonGateway/CoreBundle/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=CommonGateway/CoreBundle&amp;utm_campaign=Badge_Grade)

The common gateway repositroy gives a quick kubertnetes wrapper of the [common gateway symfony bundle](https://github.com/CommonGateway/CoreBundle). In other words it doesn't aim to be its own code base but simply contains the files needed to create kubernetes images and helm installers for the core bundle.

If you are looking for the common gateway code base please look at the core bundle repository instead, that is place where you will find al the appropriate documentation.

## Quick start (for local development)
> **Dependencies**
> - To clone the codebase to your locale machine you will need git
> - To run the gateway your local machine you will need docker desktop.

You can start up the commongatway locally by cloning the respository to you machine
````cli
$ git clone https://github.com/ConductionNL/commonground-gateway
````

Afther that you need to spin upyour local docker setup 

````cli
$ docker compose up --build
````

(the --build flag can be ignored the next time you want to run the application)

The common gateway control panel can be found on http://localhost:8000 on default setups.


## Quick start (for kubernetes)
> **Dependencies**
> - For installations on kubernetes you will need to install [helm](https://helm.sh/docs/intro/install/) on your local machine.
> - If you need to install lets encrypt you will also need to install [kubectl](https://kubernetes.io/docs/tasks/tools/), but if you preffeiusly installed docker dekstop then kubectl is already pressent on your machine (so no installation needed).

### Haven installations
If you are installing the common gateway on an [haven](https://haven.commonground.nl/) environment you can just run the provided helm installer. Before installing the common gateway you need to add it to your helm repositories.

````helm
$ helm repo add common-gateway https://raw.githubusercontent.com/ConductionNL/gateway-ui/refinement-demo-branch/helm/
````

Or if you want to install the gateway as a headless application (without user interfaces)

````helm
$ helm repo add common-gateway https://raw.githubusercontent.com/ConductionNL/commonground-gateway/master/api/helm/
````


Afther that you can simply install the gateway to your cluster by using the helm installer, if you are using a kubeconfig file to authenticate your self you need to add that as a `--kubeconfig` flag. But if you are using another authentication route you can onmit the flag. When installing an application to helm always choice a name that helps you identiy the application and put that in place of `[my-installation]`


````helm
$ helm install [my-gateway] common-gateway/gateway-ui --kubeconfig=[path-to-your-kubeconfig]
````

This will install the gateway in a barebone and production setup on the latest version (you can log versions trough the version flag e.g. `--version 2.2`. To further configure the gateway we need to set somme envirenmental values. A full option list can be found [here](). Lets for now assume that you want to swith your gateway from prod to devmode, enable cronrunners for asynchronus resource handling, add a domain name to make the gateway publicly accable and last but not least use lets encryped to provide an ssl/https connection.  In helm we use the `--set` flag to set values. So the total upgrade command would look like: 

````helm
$ helm upgrade [my-gateway] --kubeconfig=[path-to-your-kubeconfig] --set  gateway.enabled=true, pwa.apiUrl=https://[your-domain]/api, pwa.meUrl=https://[your-domain]/me, pwa.baseUrl=https://[your-domain], pwa.frontendUrl=https://[your-domain], pwa.adminUrl=https://[your-domain], ingress.enabled=true, global.domain=[your-domain], ingress.hostname=[your-domain], ingress.annotations.cert-manager\.io/cluster-manager=letsencrypt-prod, ingress.tls.0.hosts.0=[your-domain], ingress.tls.0.secretName=[your-domain but replace . with -]-tls, gateway.cronrunner.enabled=true
````

Or for the headles version

````helm
$ helm upgrade [my-gateway] --kubeconfig=[path-to-your-kubeconfig] --set cronrunner.enabled=true,php.tag=dev,nginx.tag=dev,ingress.enabled=true,global.domain=[my-domain.com],tls.0.hosts.0=[my-domain.com],tls.0.secretName=[my-domain]-tls,ingress.annotations.cert-manager\.io/cluster-manager=letsencrypt-prod
````

Alternativly you can just use kubernetes dashboard to change the helm values file.

For more information on installing the application trough helm read the [helm documentation](https://helm.sh/docs/helm/).

### Non-haven installations
If however your envrinoment is not compliant with the haven standard (and we sugest that you follow it) then you should keep in mind the you need the followin dependencies on your kubernates clusters

**MUST have**
- [nfs](https://artifacthub.io/packages/helm/kvaps/nfs-server-provisioner) 
- [Ingress-nginx](https://artifacthub.io/packages/helm/ingress-nginx/ingress-nginx)

**Should have**
- [Cert manager](https://artifacthub.io/packages/helm/cert-manager/cert-manager) (add `--set installCRDs=true` to the command if using helm, you will need them for letsencrypt)
- Letsencrypt (see details below)
- Prometheus
- Loki


### Activating lets encrypt. 
If you want your cluster to be able to setup its own certificates for ssl/https (and save yourself the hussle of manually loadeing certificates) you can install lets encrypt. Keep in mind that this wil profide low level certificates. If you need higher lever certificates you still need to manually obtain them.

**Note: Make sure to install cert manager before lets encrypt**

````helm
$ kubectl apply -f letsencrypt-ci.yaml --kubeconfig=[path-to-your-kubeconfig]
````

