# Contribution Guide

## Setting up the project

### Nix Setup (With Flakes)

Pull down this project locally.

when you enter the directory if you have direnv installed run:

```sh
direnv allow
```

If you do not run

```sh
nix develop --impure
```

this will pull down all dependencies, install hooks and formatters etc.

### Docker Setup

For those who prefer Docker, we've included a `docker-compose.yml` file to quickly set up a development environment with all required services.

1. Make sure you have Docker and Docker Compose installed on your system.

2. Start the development environment:

```sh
docker-compose up -d
```

This will start:
- Elasticsearch (accessible at http://localhost:9200)
- Kibana (accessible at http://localhost:5601)
- Redis (accessible at localhost:6379)

All services include health checks to ensure they're properly initialized before use.

3. To stop the environment:

```sh
docker-compose down
```

4. To view logs from the services:

```sh
docker-compose logs -f
```

### Development with Nix

During development with the Nix setup, you will need to start a local elasticsearch instance. To start elasticsearch run:

```sh
just up
```

This will start process-compose and an elastic search instance.

You can now open a new terminal window and run tests using pest. For conviance purposes the nix shell has the command `p` linked to run `./vendor/bin/pest` and `pf` linked to run `./vendor/bin/pest --filter "$@"`.

### Development with Docker

When using the Docker setup, all required services are already running after executing `docker-compose up -d`. You can run tests directly against these services.

To run tests:

```sh
./vendor/bin/pest
```

To run a specific test:

```sh
./vendor/bin/pest --filter="test_name"
```

The Docker environment provides all necessary services configured with appropriate default settings for development and testing.
