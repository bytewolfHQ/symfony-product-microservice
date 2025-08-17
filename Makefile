# -------- Phony --------
.PHONY: help env-init build up down restart sh logs composer-install jwt migrate schema-update admin-hash seed-admin test cc bootstrap status url

# -------- Settings --------
ENV ?= dev

COMPOSE_ENV_FILE ?= .env.docker

COMPOSE_BASE := docker-compose.yml
COMPOSE_DEV  := docker-compose.dev.yml
COMPOSE_STAGE:= docker-compose.stage.yml
COMPOSE_PROD := docker-compose.prod.yml

# Rezepte ohne Tabs:
.RECIPEPREFIX := >

# -------- Compose files according to ENV --------
ifeq ($(ENV),dev)
  COMPOSE_FILES := -f $(COMPOSE_BASE) -f $(COMPOSE_DEV)
  ENABLE_XDEBUG := 1
endif
ifeq ($(ENV),stage)
  COMPOSE_FILES := -f $(COMPOSE_BASE) -f $(COMPOSE_STAGE)
  ENABLE_XDEBUG := 0
endif
ifeq ($(ENV),prod)
  COMPOSE_FILES := -f $(COMPOSE_BASE) -f $(COMPOSE_PROD)
  ENABLE_XDEBUG := 0
endif

DC := docker compose --env-file $(COMPOSE_ENV_FILE) $(COMPOSE_FILES)

help:
>@echo "Targets (ENV=$(ENV)):"
>@echo "  make env-init ENV=dev|stage|prod   # .env.local.<env> erzeugen (wenn fehlt)"
>@echo "  make build                         # Images bauen (ENABLE_XDEBUG=$(ENABLE_XDEBUG))"
>@echo "  make up                            # Container starten"
>@echo "  make down                          # Container stoppen"
>@echo "  make restart                       # Container neustarten"
>@echo "  make sh                            # Shell im app-Container"
>@echo "  make logs                          # Logs folgen"
>@echo "  make composer-install              # composer install im Container"
>@echo "  make jwt                           # JWT-Keys generieren"
>@echo "  make migrate                       # Doctrine-Migrationen ausführen"
>@echo "  make schema-update                 # (Notfall) Schema direkt updaten"
>@echo "  make admin-hash                    # Passwort-Hash erzeugen"
>@echo "  make seed-admin EMAIL=.. PASS=..   # Admin-User in DB anlegen/ersetzen"
>@echo "  make test                          # PHPUnit"
>@echo "  make cc                            # Cache clear"
>@echo "  make bootstrap                     # build + up + composer + jwt + migrate"
>@echo "  make status                        # compose ps"
>@echo "  make url                           # zeigt URLs"
>@echo "  make exec							 # Komfort-Exec im Container"
>@echo "  make sf							 # Symfony Console"
>@echo "  make phpunit						 # PHPUnit"

env-init:
>@if [ ! -f .env.local.$(ENV) ]; then \
>  echo 'APP_ENV=$(if $(filter $(ENV),dev),dev,prod)'            >  .env.local.$(ENV); \
>  echo 'APP_SECRET='`openssl rand -hex 16`                      >> .env.local.$(ENV); \
>  echo 'DATABASE_URL="mysql://symfony:symfony@db:3306/symfony_microservice?serverVersion=11"' >> .env.local.$(ENV); \
>  echo 'JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem' >> .env.local.$(ENV); \
>  echo 'JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem'  >> .env.local.$(ENV); \
>  echo 'JWT_PASSPHRASE=changeme-please'                        >> .env.local.$(ENV); \
>  echo 'Erstellt .env.local.$(ENV). Bitte JWT_PASSPHRASE anpassen.'; \
>else \
>  echo '.env.local.$(ENV) existiert bereits.'; \
>fi
>@if [ ! -f .env.local ]; then cp .env.local.$(ENV) .env.local; fi
>@if [ ! -f .env.docker ]; then \
>  echo "COMPOSE_PROJECT_NAME=symfony-product-microservice-$(ENV)"  >  .env.docker; \
>  echo "WEB_HTTP_PORT=8080"                                       >> .env.docker; \
>  echo "ADMINER_HTTP_PORT=8081"                                   >> .env.docker; \
>  echo "DB_HOST_PORT="                                            >> .env.docker; \
>  echo "PATH_TO_SYMFONY_PROJECT=."                                >> .env.docker; \
>  echo "XDEBUG_MODE=$(if $(filter $(ENV),dev),debug,off)"         >> .env.docker; \
>  echo "DB_USER="                                                 >> .env.docker; \
>  echo "DB_PASSWORD="                                             >> .env.docker; \
>  echo "DB_NAME="                                                 >> .env.docker; \
>  echo "DB_ROOT_PASSWORD="                                        >> .env.docker; \
>  echo "HOST_UID=$$(id -u)"                                       >> .env.docker; \
>  echo "Erstellt .env.docker (Compose). Passe Ports/Namen/DB bei Bedarf an."; \
> else \
>  echo ".env.docker (Compose) existiert bereits."; \
>fi

build:
>$(DC) build --build-arg ENABLE_XDEBUG=$(ENABLE_XDEBUG)

up:
>$(DC) up -d

down:
>$(DC) down

restart:
>$(DC) restart

sh:
>$(DC) exec app bash

logs:
>$(DC) logs -f --tail=150

composer-install:
>$(DC) exec app composer install --no-interaction --prefer-dist

jwt:
>$(DC) exec app mkdir -p config/jwt
>$(DC) exec app php bin/console lexik:jwt:generate-keypair --overwrite || true
>$(DC) exec app chmod 644 config/jwt/*.pem || true
>$(DC) restart app

migrate:
>$(DC) exec app php bin/console doctrine:migrations:migrate -n || true

schema-update:
>$(DC) exec app php bin/console doctrine:schema:update --force || true

admin-hash:
>$(DC) exec app php bin/console security:hash-password || true

# Beispiel: make seed-admin ENV=stage EMAIL=admin@example.com PASS=TopSecret
seed-admin:
>@if [ -z "$(EMAIL)" ] || [ -z "$(PASS)" ]; then echo "Bitte EMAIL=... PASS=... angeben"; exit 2; fi
>@HASH=`$(DC) exec -T app php bin/console security:hash-password '$(PASS)' | awk '{print $$NF}'`; \
>$(DC) exec db bash -lc "mysql -usymfony -psymfony symfony_microservice -e \
>  \"INSERT INTO app_user (email, roles, password) VALUES ('$(EMAIL)', '[\\\"ROLE_ADMIN\\\",\\\"ROLE_USER\\\"]', '$$HASH') \
>  ON DUPLICATE KEY UPDATE roles='[\\\"ROLE_ADMIN\\\",\\\"ROLE_USER\\\"]', password='$$HASH';\""; \
>echo "Admin-User gesetzt: $(EMAIL)"

test:
>$(DC) exec app php bin/phpunit -v || true

cc:
>$(DC) exec app php bin/console cache:clear || true

bootstrap: build up composer-install jwt migrate

status:
>$(DC) ps

url:
>@echo "API Doc:   http://localhost:8080/api/doc"
>@echo "API JSON:  http://localhost:8080/api/doc.json"
>@echo "Adminer:   http://localhost:8081"

exec:
>$(DC) exec app sh -lc '$(CMD)'

sf:
>$(DC) exec app php bin/console $(ARGS)

phpunit:
>$(DC) exec app bash -lc 'APP_ENV=test APP_DEBUG=1 php bin/console cache:clear --no-warmup'
>$(DC) exec app bash -lc 'APP_ENV=test APP_DEBUG=1 XDEBUG_MODE=off php bin/phpunit $(ARGS) --display-deprecations'
