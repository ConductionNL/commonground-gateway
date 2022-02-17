# Security

-   to be added -

## Separation between web service and code

The Web Gateway has a strict separation between the web service used to communicate to the internet, its business logic (contained in a separate container), and the data. This way of developing aligns with the "landing zone" principle. It means that the part exposed to the internet doesn't provide code or business logic and does not have access to the database
