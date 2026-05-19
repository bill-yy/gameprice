# Cloudflare Worker: Scrape Proxy

Este Worker actúa como proxy para bypassar los bloqueos de Cloudflare en IPs de datacenter (como las de Dokploy).

## Tiendas soportadas

- `www.kinguin.net`
- `www.g2a.com`
- `www.gamivo.com`
- `us.gamesplanet.com`
- `www.gamesplanet.com`
- `www.instant-gaming.com`

## Deploy

### Opción 1: Cloudflare Dashboard (más fácil)

1. Ve a [Cloudflare Dashboard](https://dash.cloudflare.com) > Workers & Pages
2. Clic en **"Create a Service"**
3. Nombre: `gameprice-scrape-proxy`
4. Clic en **"Create service"**
5. En el editor, reemplaza el código por el contenido de `index.js`
6. Clic en **"Save and Deploy"**
7. Copia la URL del worker (ej: `https://gameprice-scrape-proxy.tu-usuario.workers.dev`)

### Opción 2: Wrangler CLI

```bash
# Instalar wrangler
npm install -g wrangler

# Login
wrangler login

# Deploy
cd workers/scrape-proxy
wrangler deploy
```

## Configurar en la app

1. Ve a Dokploy > gameprice-app > Environment Variables
2. Añade:
   - `CLOUDFLARE_WORKER_URL=https://gameprice-scrape-proxy.tu-usuario.workers.dev`
3. Guarda y redeploy

## Cómo funciona

```
App Dokploy (IP bloqueada)
    ↓
Llama al Worker URL con ?url=https://www.kinguin.net/...
    ↓
Worker (IP de Cloudflare, NUNCA bloqueada)
    ↓
Hace la petición real a Kinguin/G2A/Gamivo
    ↓
Devuelve la respuesta JSON/HTML a la app
```

## Límites

- Plan gratuito de Cloudflare Workers: **100,000 requests/día**
- Con 10 scrapers × 50 juegos = ~500 requests por ejecución
- Eso da para **200 ejecuciones/día** (más que suficiente)

## Test manual

```bash
curl "https://TU-WORKER.workers.dev/?url=https://www.kinguin.net/svc/search/api/v1/products?q=witcher&limit=5"
```
