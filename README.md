# cornchan

## development

### server

```sh
# launches a server on http://localhost:8000
docker-compose -f "docker-compose.yml" up --build --detach
```

### testing

```sh
docker-compose -f "docker-compose.yml" up --build --exit-code-from test
```
