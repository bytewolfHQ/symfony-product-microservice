# symfony-product-microservice
symfony, php, docker, microservice, jwt, nelmio

# Symfony Product Microservice – Infra

**PR 1**: Infrastruktur & Runtime (Docker, Nginx, MariaDB, Makefile, Envs).  
Der eigentliche Symfony-Code folgt in PR 2.

## Quickstart (Dev)

```bash
make env-init ENV=dev
make bootstrap  ENV=dev
make url

