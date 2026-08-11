# Laravel All-In-One

A Laravel starter repository with integrated PostgreSQL, Redis, RabbitMQ, Elasticsearch, and Kibana services.

## Requirements

Before you begin, ensure you have the following installed:

- **Docker** (with Docker Compose)
- **PHP** 8.3 or higher
- **Node.js** 20.19+ / 22.12+ (required by Vite 8)

## Version Matrix

The PHP client libraries are pinned to the same major version as the container
image they talk to, so the whole stack stays compatible:

| Service       | Container image           | PHP client                                        |
|---------------|---------------------------|---------------------------------------------------|
| PostgreSQL    | `postgres:18.4`           | `laravel/framework` (pdo_pgsql)                   |
| Redis         | `redis:8.10`              | `predis/predis` ^3.5                              |
| RabbitMQ      | `rabbitmq:4.3-management` | `vladimir-yuldashev/laravel-queue-rabbitmq` ^15.0 |
| Elasticsearch | `elasticsearch:9.4.4`     | `elasticsearch/elasticsearch` ^9.5                |
| Kibana        | `kibana:9.4.4`            | —                                                 |

Framework: **Laravel 13** on **PHP 8.3+**.

> The Elasticsearch PHP client sends a `compatible-with=<major>` header on every
> request, so the client major must match the server major. Client 9.x ↔ server
> 9.x is the supported pairing here.

## Quick Start

After cloning this repository, simply run:

```bash
php setup.php
```

This single command will automatically:

- ✅ Install all dependencies
- ✅ Set up and start Docker containers for:
  - PostgreSQL
  - Redis
  - RabbitMQ
  - Elasticsearch
  - Kibana
- ✅ Run Laravel migrations (default tables will be migrated to PostgreSQL)

## Service Access

Once setup is complete, you can access:

- **Laravel App**: http://127.0.0.1:8000
- **RabbitMQ Management**: http://127.0.0.1:15672
- **Elasticsearch**: http://127.0.0.1:9200
- **Kibana**: http://127.0.0.1:5601

### PostgreSQL Database Connection

- **Host**: 127.0.0.1
- **Port**: 5432
- **User**: laravel
- **Password**: laravel
- **Database**: laravel

## Start Development

```bash
php artisan serve
```

## Check Service Connections

```bash
php artisan connections:check
```

---

**Note**: Docker containers must be running for services to work. Use `docker compose ps` to check container status.
