Adding an API as a source WILL NOT leave that API exposed. API’s might be added manually through discovery. Discovery methods currently on the roadmap are NLX, Autorisatie Component, and Generic Kubernetes services.

Here is where you can configure access and communicate with those sources. You can create new sources or update existing ones.

The fields with \* are mandatory. Special mention to the documentation field where you can specify the URL to your API documentation. Recommended for working in developers working in teams.

```json
Properties
_name_:
_location_:
_type_:
_accept_:
_locale_:
_auth_:
_jwt_:
_jwtid_:
_secret_:
_apikey_:
_documentation_:
_authorizationHeader_:
_userName_:
_password_:
```

You can add additional header specification, OAS and paths below.
