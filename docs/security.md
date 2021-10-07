# Security

ad

## Separation between webservice and code
The Web Gateway has a strict separation between the webservice used to communicate to the internet, its business logic (contained in separate container) and  the data. This is developed in line with the “landingzone” principle. Meaning that the place that is actually exposed to the internet dosn’t provide code or business logic and does not have access to the database 
