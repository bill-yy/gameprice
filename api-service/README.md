# GamePrice Scraping API Service

Standalone Laravel API service for game price comparison across multiple stores. RapidAPI-ready for monetization.

## Stores Supported

| Store | ID | Currency |
|-------|----|----------|
| Eneba | `eneba` | EUR |
| Instant Gaming | `instant-gaming` | EUR |
| CheapShark | `cheapshark` | USD |
| G2A | `g2a` | EUR |
| Kinguin | `kinguin` | EUR |

## API Endpoints

### Health check
```
GET /api/health
```
Returns `{"status": "ok"}` — no API key required. Use this for uptime monitoring.

### Search all stores
```
GET /api/v1/search?q=elden ring
```

### Search specific store
```
GET /api/v1/prices/g2a?q=elden ring
```

### List available stores
```
GET /api/v1/stores
```

### Best deals
```
GET /api/v1/deals
```

### Response format
```json
{
  "success": true,
  "query": "elden ring",
  "results": [
    {
      "store": "Instant Gaming",
      "name": "Elden Ring",
      "price": 35.99,
      "original_price": 59.99,
      "discount_percent": 40,
      "currency": "EUR",
      "url": "https://www.instant-gaming.com/en/...",
      "in_stock": true,
      "platform": "PC"
    }
  ],
  "meta": {
    "count": 1,
    "stores_searched": 5
  }
}
```

## Authentication

Include your API key in the `X-API-Key` header:
```
X-API-Key: your-api-key-here
```

Rate limit: 100 requests/hour (configurable via `API_RATE_LIMIT` env var).

## Dokploy Deployment

### Prerequisites
- A Dokploy instance with a PostgreSQL database provisioned

### Steps

1. **Create a new application** in Dokploy, pointing to this repository's `/api-service/` directory (or the monorepo root with build context set to `/api-service`).

2. **Set the Dockerfile path** to `api-service/Dockerfile` (if deploying from monorepo root).

3. **Configure environment variables** in Dokploy:
   ```
   APP_KEY=base64:your-generated-key
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.yourdomain.com
   
   DB_CONNECTION=pgsql
   DB_HOST=<dokploy-postgres-host>
   DB_PORT=5432
   DB_DATABASE=<database-name>
   DB_USERNAME=<username>
   DB_PASSWORD=<password>
   
   API_RATE_LIMIT=100
   API_KEYS=key-one,key-two,key-three
   ```

4. **Generate an APP_KEY** if needed:
   ```bash
   php artisan key:generate --show
   ```

5. **Deploy** — the container entrypoint will automatically run migrations and start nginx + php-fpm via supervisor.

6. **Set up a health check** in Dokploy pointing to `GET /api/health` (returns `{"status": "ok"}`).

### Docker Compose (local development)

```bash
docker compose up -d
curl http://localhost:8000/api/health
curl -H "X-API-Key: dev-key-change-me-in-production" http://localhost:8000/api/v1/stores
```

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_KEY` | — | Laravel encryption key (required) |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `5432` | Database port |
| `DB_DATABASE` | `gameprice_api` | Database name |
| `DB_USERNAME` | `gameprice` | Database user |
| `DB_PASSWORD` | `secret` | Database password |
| `API_RATE_LIMIT` | `100` | Requests per hour |
| `API_KEYS` | `dev-key-...` | Comma-separated valid API keys |

## RapidAPI Monetization Setup

### Step 1: Deploy the API
Deploy this service to any hosting provider:
- **Dokploy** (recommended, see above)
- **Railway** / **Render** / **Fly.io** (easiest with Docker)
- **AWS** (ECS Fargate or EC2)
- **DigitalOcean** (App Platform with Dockerfile)

### Step 2: Create a RapidAPI Provider account
1. Go to [RapidAPI Provider Hub](https://rapidapi.com/hub)
2. Sign up as a Provider
3. Click "Add New API"
4. Fill in API details (name, category: Games, description)

### Step 3: Configure the API on RapidAPI
1. Under **Endpoints**, add each endpoint:
   - `GET /api/v1/search` — param `q` (required)
   - `GET /api/v1/prices/{store}` — param `q` (required), path param `store`
   - `GET /api/v1/stores`
   - `GET /api/v1/deals`
2. Set your **Base URL** to your deployed service URL
3. Configure **pricing plans**:
   - **Free**: 100 requests/month (attracts users)
   - **Pro**: $9.99/month — 5,000 requests
   - **Ultra**: $29.99/month — 25,000 requests
   - **Mega**: $79.99/month — unlimited
4. RapidAPI handles billing, key management, and rate limiting on their end

### Step 4: RapidAPI Key forwarding
RapidAPI sends requests with the `X-RapidAPI-Proxy-Secret` header. You can:
- Option A: Set `API_KEYS=` (empty) to disable auth and rely on RapidAPI's gateway
- Option B: Use RapidAPI's header mapping to forward as `X-API-Key`

### Step 5: Monitoring
- Use RapidAPI Provider Dashboard for analytics, revenue tracking
- Monitor your scraper service health independently via `/api/health`
- Set up alerts for downtime

## Architecture

```
Request → Rate Limit Middleware → API Key Middleware → Controller → Scraper → Response
```

All scrapers run independently and fail gracefully. If one store is down, others still return results.
