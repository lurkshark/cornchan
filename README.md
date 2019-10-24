# cornchan
[![Build Status](https://travis-ci.com/lurkshark/cornchan.svg?branch=master)](https://travis-ci.com/lurkshark/cornchan)

## Development

### Server

```sh
# Launches a server on http://localhost:8000
docker-compose -f "docker-compose.yml" up --build --detach
```

### Testing

```sh
docker-compose -f "docker-compose.yml" up --build --exit-code-from test
```
