  
# Installation
The wedding planner application consists of a number of components and one/several ui's built according to the [commonground five-layer model]. Underlying components can be shared between applications, so we recommend installing only new components and reusing existing components. If you want more insight into a component you can click on the title for the VNG components catalog or look in the github repository for more detailed descriptions and the source code files.  

All components are available as a docker container via github packages (due to the download maximum on dockerhub.io), the containers can therefore be found directly at the repositories. HELM installation files are also available for all components. You can find these in the component's repository (github) as well as in the official HELM hub ([artifacthub.io](https://artifacthub.io/)).

## Configuration
The configuration of the various components takes place via the helm installation of that component, follow the manual supplied with the component. However, there is a general point for all components regarding making these components externally accessible:  

To open a component to the web, three steps are required:
1. The value ingress.enabled must be true
2. The value ingress.host must contain a hostname routed to the load balancer
3. The value path must be correct. For an application this can be /, but for components /api/v1/{component name} or /api/v1/{component code} is recommended

Before the components are functional, the database will have to be prepared. We do this using the following command:  
bin/console doctrine:schema:update -f  

## From components to application  
The wedding planner application consists of a series of components, in order for them to form an application together it is necessary to make them work together. For this it is necessary to give the central spider in the web (wedding planner service) access to the components so that she can set them up. The necessary configuration is included in the (helm) installation files and description of the wedding planner ui (which includes the wedding planner service). Therefore, install it last and read the installation manual and configuration description carefully before installing the component.

## Example data
After the configuration has been completed, prefaces can be chosen to load a move with sample data (for example for demo purposes). To load sample data, this data must be loaded on three components in sequence after the dependencies of the relevant component have been set:
- landelijketabellencatalogus
- brpservice
- trouw-service

The following command must be run on these components in the php container:  
bin/console doctrine:fixtures:load -n  
The Trouw Service will also send sample data to the other components. 
