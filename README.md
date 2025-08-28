# Symfony Product Microservice
Small product service based on **Symfony 7 + Doctrine**, packaged in **Docker (Nginx + PHP-FPM + MariaDB)**.
Tests run against **SQLite** (fast & self-contained). The API is documented with **NelmioApiDocBundle (OpenAPI 3 / Swagger UI)**.

## Features
* Product domain: ``Product`` (id, name, description, price, category, isActive, createdAt, updatedAt)
* CRUD Endpoints (GET list, GET one, POST, PUT, PATCH, DELETE)
* Filter & Pagination (category, price-Range, isActive, page/limit, sort)
* JSON API with ``X-Total-Count`` header
* Health/Ping Endpoints (``/healthz``, ``/api/ping``)
* OpenAPI 3 via NelmioApiDocBundle:
    * Swagger UI: ``GET /api/doc``
    * JSON spec: ``GET /api/doc.json``
    * YAML spec: ``GET /api/doc.yaml``
* Tests with PHPUnit (SQLite, schema is generated automatically)
* Makefile commands for local flow

### Quickstart (Dev)
Requirements: Docker & Docker Compose, make, ports 8080 (web) & 8081 (admin) free.

```bash
# 1) Build images
make build ENV=dev

# 2) Start containers
make up ENV=dev

# 3) Apply DB migrtions
make migrate ENV=dev

# 4) Load test data (Doctrine Fixtures)
make seed ENV=dev
```
Check:
* Health: http://localhost:8080/healthz → ``{"status":"ok"}``
* Swagger UI: http://localhost:8080/api/doc
* Adminer (optional): http://localhost:8081 (DB: ``symfony_microservice``, User/Pass from .env)

Stop:
```bash
make down ENV=dev
```

### API Overview
Base path: /api

Method | Path               | Description
------------|------------|------------
GET | /api/products      | List with filters/pagination
GET | /api/products/{id} | Single product
POST | /api/products | Create new product
PUT | /api/products/{id} | Update complete product
PATCH | /api/products/{id} | Partially update product
DELETE | /api/products/{id} | Delete product
GET | /api/ping | Ping
GET | /api/healthz / Health check

### Filter / Pagination (GET /api/products)
Query parameter:
* ``category`` (string)
* ``minPrice`` / ``maxPrice`` (number)
* ``isActive`` (bool., example ``true`` / ``false``)
* ``page`` (integer, 1-based)
* ``limit`` (integer)
* ``sort`` (string, example ``createdAt,DESC``)

**Response**: JSON ``{data: [...], meta: { total, page, limit }}``  
**Header**: ``X-Total-Count: <total>``

Example:  
```bash
curl -s "http://localhost:8080/api/products?category=Electronics&minPrice=10&maxPrice=500&isActive=true&page=1&limit=10&sort=createdAt,DESC"
```
### OpenAPI / Swagger
* UI: http://localhost:8080/api/doc
* JSON: http://localhost:8080/api/doc.json
* YAML: http://localhost:8080/api/doc.yaml

The documentation is generated from PHP attributes in ``src/Controller`` (operations) and ``src/Entity/Product.php`` (schema).  
In case of „No operations defined“: Clear cache and restart
```bash
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.dev.yml exec app php bin/console cache:clear
make down ENV=dev && make up ENV=dev
```

### Tests
The tests use SQLite and generate the schema automatically.
```bash
# All tests
make phpunit

# Execute Test/Method filter
make phpunit ARGS='--filter ProductControllerTest::testListWithFiltersAndPagination'
```
CI (GitHub Actions) also uses SQLite and runs phpunit in ~15 s.  

### Databases & Migrations
```bash
# Create/Update DB (dev)
make migrate ENV=dev

# Create migration (In case of changing the mapping)
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.dev.yml exec app php bin/console make:migration
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.dev.yml exec app php bin/console doctrine:migrations:migrate -n
```
Load fixtures:  
```bash
make seed ENV=dev
```

### Makefile-Cheatsheet
> Names may vary slightly depending on the Makefile – these targets are active on the system and have been used in the history.

Command | Effect
------------|-----------
``make build ENV=dev`` | Build docker images
``make up ENV=dev`` | Start containers (Nginx, PHP-FPM, MariaDB, Adminer)
``make down ENV=dev`` | Stop containers & remove network
``make migrate ENV=dev`` | Execute Doctrine migration
``make seed ENV=dev`` | Load doctrine fixtures
``make phpunit [ARGS='...']`` | PHPUnit tests (optional with filters)

### Troubleshooting (short & sweet)
* ``The metadata storage is not up to date``  
  → ``bin/console doctrine:migrations:sync-metadata-storage -n``
* ``table already exists`` **(SQLite im CI/Test)**  
  → Delete Test-DB: ``rm -f var/test.db`` (is created anew)  
  → or clear cache: ``bin/console cache:clear --env=test``
* Swagger shows no operations  
  → Clear cache & restart container (see above), check that the Nelmio configuration ``areas.default.path_patterns`` points to ``/api`` and ``with_attributes: true`` is set.
* 404 on ``/api/ping``  
  → Run ``bin/console router:match /api/ping`` – this should match ``app_api_ping`` and point to ``PingController::ping()``.

### Dependencies (Twig & Asset)
* Twig is required for the Swagger UI page. Keep it if you want to use /api/doc.
* **symfony/asset** isn't required for the standard Swagger UI. If you don't use it:
```bash
composer remove symfony/asset
docker compose exec app php bin/console cache:clear
```
If you only want to deliver JSON/YAML (without UI), you could remove Twig and use ``/api/doc.json`` / ``/api/doc.yaml``:
```bash
composer remove symfony/twig-bundle
# Delete Nelmio routes for swagger_ui/redocly
docker compose exec app php bin/console cache:clear
```

### Next steps / Ideas
* AuthN/AuthZ with JWT (Login, Token-Guard for mutations)
* Request/Response DTOs & Validation Errors via Problem+JSON
* E2E/Smoke-Tests (in example over curl in CI)
* Expand Docker-Healthcheck & Status-Endpoint

### License
MIT – see ``LICENSE``
