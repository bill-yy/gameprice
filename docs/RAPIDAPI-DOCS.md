# GamePrice API

Real-time game price comparison across 8 digital stores.

## Base URL

```
https://baratoya.billytech.es/api/v1
```

## Authentication

All endpoints (except `/health`) require an API key via the `X-API-Key` header.

```http
X-API-Key: your-api-key-here
```

**Response on missing/invalid key:**
```json
{
  "success": false,
  "error": "Missing API key. Provide X-API-Key header."
}
```

---

## Endpoints

### Health Check

```http
GET /api/health
```

No authentication required.

**Response:**
```json
{
  "status": "ok"
}
```

---

### List Stores

```http
GET /api/v1/stores
```

Returns all supported game stores and their currencies.

**Response:**
```json
{
  "success": true,
  "stores": [
    {
      "id": "eneba",
      "name": "Eneba",
      "url": "https://www.eneba.com",
      "currency": "EUR"
    },
    {
      "id": "instant-gaming",
      "name": "Instant Gaming",
      "url": "https://www.instant-gaming.com",
      "currency": "EUR"
    }
  ]
}
```

**Supported stores:** `eneba`, `instant-gaming`, `cheapshark`, `g2a`, `kinguin`, `cdkeys`, `psn-store`, `xbox-store`

---

### Search All Stores

```http
GET /api/v1/search?q={query}
```

Searches across all 8 stores simultaneously and returns the best prices.

**Query Parameters:**

| Parameter | Type   | Required | Description         |
|-----------|--------|----------|---------------------|
| `q`       | string | Yes      | Game name to search |

**Example Request:**
```http
GET /api/v1/search?q=elden+ring
X-API-Key: your-api-key-here
```

**Example Response:**
```json
{
  "success": true,
  "query": "elden ring",
  "results": [
    {
      "store": "Eneba",
      "name": "ELDEN RING",
      "price": 29.99,
      "original_price": 59.99,
      "discount_percent": 50,
      "currency": "EUR",
      "url": "https://www.eneba.com/...",
      "in_stock": true,
      "platform": "Steam"
    }
  ],
  "meta": {
    "count": 66,
    "stores_searched": 8,
    "cached": true
  }
}
```

**Notes:**
- Results are sorted by price (lowest first)
- Products with price = 0 are automatically filtered out
- First request for a query may take ~7s (live scraping)
- Subsequent requests return instantly from cache (30 min TTL)

---

### Search by Store

```http
GET /api/v1/prices/{store}?q={query}
```

Search a specific store only.

**Path Parameters:**

| Parameter | Description                          |
|-----------|--------------------------------------|
| `store`   | Store ID (e.g. `cheapshark`, `eneba`)|

**Query Parameters:**

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| `q`       | string | Yes      | Game name   |

**Example Request:**
```http
GET /api/v1/prices/cheapshark?q=witcher
X-API-Key: your-api-key-here
```

**Example Response:**
```json
{
  "success": true,
  "query": "witcher",
  "store": "cheapshark",
  "results": [
    {
      "store": "CheapShark",
      "name": "The Witcher: Enhanced Edition",
      "price": 1.49,
      "original_price": 9.99,
      "discount_percent": 85,
      "currency": "USD",
      "url": "https://www.cheapshark.com/redirect?dealID=...",
      "in_stock": true,
      "platform": "PC",
      "steam_rating": 97,
      "metacritic_score": 86
    }
  ],
  "meta": {
    "count": 20,
    "stores_searched": 1
  }
}
```

---

### Get Deals

```http
GET /api/v1/deals
```

Returns the best current deals from CheapShark (PC games under $15 with biggest savings).

**Example Response:**
```json
{
  "success": true,
  "results": [
    {
      "store": "CheapShark",
      "name": "GravNewton",
      "price": 0.49,
      "original_price": 9.99,
      "discount_percent": 95,
      "currency": "USD",
      "url": "https://www.cheapshark.com/redirect?dealID=...",
      "platform": "PC",
      "steam_rating": 85
    }
  ],
  "meta": {
    "count": 20,
    "cached": true
  }
}
```

**Notes:**
- Cached for 15 minutes
- If live fetch fails, returns cached data or empty array with `success: true`

---

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "error": "Description of the error"
}
```

| HTTP Code | Meaning                              |
|-----------|--------------------------------------|
| `400`     | Bad request (missing `q` parameter)  |
| `401`     | Missing or invalid API key           |
| `404`     | Unknown store                        |
| `500`     | Internal server error (rare)         |

---

## Rate Limits

- **60 requests per minute** per API key
- Rate limit headers included in all responses:
  ```http
  X-RateLimit-Limit: 60
  X-RateLimit-Remaining: 59
  ```

---

## Data Freshness

| Endpoint | Source        | Cache TTL   |
|----------|---------------|-------------|
| `/search` | Live scrapers | 30 minutes  |
| `/prices/{store}` | Live scrapers | No cache    |
| `/deals` | CheapShark API | 15 minutes |
| `/stores` | Static config | No cache    |

---

## Supported Platforms

- PC (Steam, Epic, GOG, etc.)
- PlayStation (PSN Store)
- Xbox (Microsoft Store)

## Supported Currencies

- EUR (Eneba, Instant Gaming, G2A, Kinguin, CDKeys)
- USD (CheapShark)

---

## Example: cURL

```bash
# Search for a game
curl -H "X-API-Key: your-key" \
  "https://baratoya.billytech.es/api/v1/search?q=elden+ring"

# Get deals
curl -H "X-API-Key: your-key" \
  "https://baratoya.billytech.es/api/v1/deals"

# Search specific store
curl -H "X-API-Key: your-key" \
  "https://baratoya.billytech.es/api/v1/prices/cheapshark?q=witcher"
```

## Example: JavaScript (fetch)

```javascript
const API_KEY = 'your-api-key';
const BASE = 'https://baratoya.billytech.es/api/v1';

async function searchGame(name) {
  const res = await fetch(`${BASE}/search?q=${encodeURIComponent(name)}`, {
    headers: { 'X-API-Key': API_KEY }
  });
  return res.json();
}

searchGame('elden ring').then(console.log);
```

## Example: Python (requests)

```python
import requests

API_KEY = 'your-api-key'
BASE = 'https://baratoya.billytech.es/api/v1'

def search_game(name):
    res = requests.get(
        f"{BASE}/search",
        params={"q": name},
        headers={"X-API-Key": API_KEY}
    )
    return res.json()

print(search_game("elden ring"))
```
