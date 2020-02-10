# cornchan
[![Build Status](https://travis-ci.com/lurkshark/cornchan.svg?branch=master)](https://travis-ci.com/lurkshark/cornchan)
[![W3C Validation](https://img.shields.io/w3c-validation/html?targetUrl=https%3A%2F%2Fcornchan.org)](https://validator.nu/?doc=https%3A%2F%2Fcornchan.org)

## Installation
Cornchan is designed to run well on shared Apache+PHP hosting. It's tested on [NearlyFreeSpeech.NET](https://www.nearlyfreespeech.net/) but should work on almost any shared hosting that has the required PHP extensions. Cornchan will check all these requirements on its first run.

### How-to
1. Upload `index.php` to your website hosting root directory.
2. Visit your website and follow the instructions.

### Nginx
You can also to deploy Cornchan to Nginx-fronted hosting, but this hasn't been tested. The Nginx equivalent to the Apache configuration used would look something like this:

```nginx
location / {
  try_files $uri /index.php;
}
```

### Troubleshooting

#### Cannot write or delete files on your server

#### GD PHP extension or a required filetype isn't installed

#### DBA PHP extension isn't installed or doesn't have an acceptable handler

## Development
The guiding principle of this project is to be as cheap and easy as possible to deploy while still maintaining a high level of quality. In practice this means targeting shared hosting with no RDBMS, and requiring as few files as possible to deploy. The ideal setup is a single `index.php` file that takes care of everything.

### Server

```sh
# Launches a server on http://localhost:8000
docker-compose -f "docker-compose.yml" up web
```

### Testing
All testing is done as a black-box. The most important are the Capybara tests that validate features from an end-user perspective. All functional features should have Capybara tests.

```sh
# Launches a server, runs the tests, and exits with the result code
docker-compose -f "docker-compose.yml" up --exit-code-from test
```
