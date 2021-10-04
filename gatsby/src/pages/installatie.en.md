  
# Installation

[comment]: <> (The wedding planner application consists of a number of components and one/several ui's built according to the [commonground five-layer model]. Underlying components can be shared between applications, so we recommend installing only new components and reusing existing components. If you want more insight into a component you can click on the title for the VNG components catalog or look in the github repository for more detailed descriptions and the source code files.  )

[comment]: <> (All components are available as a docker container via github packages &#40;due to the download maximum on dockerhub.io&#41;, the containers can therefore be found directly at the repositories. HELM installation files are also available for all components. You can find these in the component's repository &#40;github&#41; as well as in the official HELM hub &#40;[artifacthub.io]&#40;https://artifacthub.io/&#41;&#41;.)

[comment]: <> (## Configuration)

[comment]: <> (The configuration of the various components takes place via the helm installation of that component, follow the manual supplied with the component. However, there is a general point for all components regarding making these components externally accessible:  )

[comment]: <> (To open a component to the web, three steps are required:)

[comment]: <> (1. The value ingress.enabled must be true)

[comment]: <> (2. The value ingress.host must contain a hostname routed to the load balancer)

[comment]: <> (3. The value path must be correct. For an application this can be /, but for components /api/v1/{component name} or /api/v1/{component code} is recommended)

[comment]: <> (Before the components are functional, the database will have to be prepared. We do this using the following command:  )

[comment]: <> (bin/console doctrine:schema:update -f  )

[comment]: <> (## From components to application  )

[comment]: <> (The wedding planner application consists of a series of components, in order for them to form an application together it is necessary to make them work together. For this it is necessary to give the central spider in the web &#40;wedding planner service&#41; access to the components so that she can set them up. The necessary configuration is included in the &#40;helm&#41; installation files and description of the wedding planner ui &#40;which includes the wedding planner service&#41;. Therefore, install it last and read the installation manual and configuration description carefully before installing the component.)

[comment]: <> (## Example data)

[comment]: <> (After the configuration has been completed, prefaces can be chosen to load a move with sample data &#40;for example for demo purposes&#41;. To load sample data, this data must be loaded on three components in sequence after the dependencies of the relevant component have been set:)

[comment]: <> (- landelijketabellencatalogus)

[comment]: <> (- brpservice)

[comment]: <> (- trouw-service)

[comment]: <> (The following command must be run on these components in the php container:  )

[comment]: <> (bin/console doctrine:fixtures:load -n  )

[comment]: <> (The Trouw Service will also send sample data to the other components. )
