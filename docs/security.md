# Quality and Security

ad

## Separation between webservice and code
The Web Gateway has a strict separation between the webservice used to communicate to the internet, its business logic (contained in separate container) and  the data. This is developed in line with the “landingzone” principle. Meaning that the place that is actually exposed to the internet dosn’t provide code or business logic and does not have access to the database 


## CI&CD Workflow
Images and instalation files are only created afther an succesful workflow, the follwoing steps are automaticly included in the workflow

- Gryphe (security test) provided by the [github Gryphe action](https://github.com/marketplace/actions/anchore-container-scan).
- Trivy (security test) provided by [github Trivy action](https://github.com/marketplace/actions/aqua-security-trivy).
- Fossa (lincane and intelectual property test) provided by [github Fossa action](https://github.com/marketplace/actions/official-fossa-action).
- Postman (integration test) provided by [github Newman action](https://github.com/marketplace/actions/newman-action).
- PHP Unit (unit and integration test)
- Composer Dependency (security test)
- Database consistency (integration test)

All actions can be configured from dthe dockerimage.yaml workflow file. Please see the appropriate gitworkflow action page for configuration options.

