# cornchan
[![Build Status](https://travis-ci.com/lurkshark/cornchan.svg?branch=master)](https://travis-ci.com/lurkshark/cornchan)
[![W3C Validation](https://img.shields.io/w3c-validation/html?targetUrl=https%3A%2F%2Fcornchan.org%2Fcorn%2F)](https://validator.nu/?doc=https%3A%2F%2Fcornchan.org%2Fcorn%2F)

## Development
The guiding principle of this project is to have a well-tested piece of garbage. The implementation should be a single PHP file with as few functions, classes, or otherwise good things as possible. This doesn't mean that it should be insecure or buggy; the end product should be solid.

### Server

```sh
# Launches a server on http://localhost:8000
docker-compose -f "docker-compose.yml" up --build --detach
```

### Testing

```sh
docker-compose -f "docker-compose.yml" up --build --exit-code-from test
```
