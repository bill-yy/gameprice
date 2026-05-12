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

## Quick Start

### Docker (recommended)
```bash
docker compose up -d
curl http://localhost:8000/api/v1/stores
```

### Manual
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8000
```

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `API_RATE_LIMIT` | `100` | Requests per hour |
| `API_KEYS` | `dev-key-...` | Comma-separated valid API keys |

## RapidAPI Monetization Setup

### Step 1: Deploy the API
Deploy this service to any hosting provider:
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
- Monitor your scraper service health independently
- Set up alerts for downtime

### Revenue tips
- Start with a generous free tier to attract subscribers
- Cache popular game results to reduce scraper load
- Add a `/api/v1/alerts` endpoint for price drop notifications (future feature)
- Track which games are searched most to optimize caching

## Architecture

```
Request → Rate Limit Middleware → API Key Middleware → Controller → Scraper → Response
```

All scrapers run independently and fail gracefully. If one store is down, others still return results.
