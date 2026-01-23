# Laravel All-In-One

A Laravel starter repository with integrated PostgreSQL, Redis, RabbitMQ, Elasticsearch, and Kibana services.

## Requirements

Before you begin, ensure you have the following installed:

- **Docker** (with Docker Compose)
- **PHP** 8.3 or higher

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
