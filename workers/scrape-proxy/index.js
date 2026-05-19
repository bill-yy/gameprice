/**
 * Cloudflare Worker: Scrape Proxy
 * 
 * Bypasses Cloudflare blocks on datacenter IPs by routing requests
 * through Cloudflare's own IP ranges (which are never blocked).
 * 
 * Deploy: Copy this into Cloudflare Dashboard > Workers & Pages > Create Service
 * Or use: npx wrangler deploy
 */

const ALLOWED_HOSTS = [
  'www.kinguin.net',
  'www.g2a.com',
  'www.gamivo.com',
  'us.gamesplanet.com',
  'www.gamesplanet.com',
  'www.instant-gaming.com',
];

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

export default {
  async fetch(request, env, ctx) {
    // Handle CORS preflight
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, X-API-Key',
          'Access-Control-Max-Age': '86400',
        },
      });
    }

    let targetUrl;
    let targetMethod = request.method;
    let targetHeaders = {};
    let targetBody = null;

    const url = new URL(request.url);

    // Support both GET (?url=) and POST (JSON body)
    if (request.method === 'POST') {
      try {
        const body = await request.json();
        targetUrl = body.url;
        if (body.method) targetMethod = body.method;
        if (body.headers) targetHeaders = body.headers;
        if (body.body) targetBody = JSON.stringify(body.body);
      } catch (e) {
        return jsonResponse({ error: 'Invalid JSON body. Expected: {url, method?, headers?, body?}' }, 400);
      }
    } else {
      targetUrl = url.searchParams.get('url');
    }

    if (!targetUrl) {
      return jsonResponse({ error: 'Missing url parameter. Use ?url= for GET or JSON body {url: "..."} for POST' }, 400);
    }

    // Validate target host
    let target;
    try {
      target = new URL(targetUrl);
    } catch {
      return jsonResponse({ error: 'Invalid URL' }, 400);
    }

    if (!ALLOWED_HOSTS.includes(target.host)) {
      return jsonResponse({ error: `Host not allowed: ${target.host}` }, 403);
    }

    // Build request to target
    const init = {
      method: targetMethod,
      headers: {
        'User-Agent': USER_AGENT,
        'Accept': 'application/json, text/html, */*',
        'Accept-Language': 'en-US,en;q=0.9',
        'Accept-Encoding': 'gzip, deflate, br',
        'Referer': `${target.protocol}//${target.host}/`,
        'Origin': `${target.protocol}//${target.host}`,
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
        'Sec-Fetch-Dest': 'document',
        'Sec-Fetch-Mode': 'navigate',
        'Sec-Fetch-Site': 'same-origin',
        'Cache-Control': 'no-cache',
      },
    };

    // Override with user-provided headers
    for (const [key, value] of Object.entries(targetHeaders)) {
      init.headers[key] = value;
    }

    // Forward body for POST/PUT requests
    if (targetBody && (targetMethod === 'POST' || targetMethod === 'PUT')) {
      init.body = targetBody;
    }

    try {
      const response = await fetch(targetUrl, init);

      // Build response headers
      const responseHeaders = new Headers();
      responseHeaders.set('Access-Control-Allow-Origin', '*');
      responseHeaders.set('Access-Control-Expose-Headers', '*');

      // Forward important headers
      const forwardHeaders = ['content-type', 'cache-control', 'etag'];
      for (const h of forwardHeaders) {
        const val = response.headers.get(h);
        if (val) responseHeaders.set(h, val);
      }

      return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: responseHeaders,
      });
    } catch (err) {
      return jsonResponse({ error: 'Proxy fetch failed', details: err.message }, 502);
    }
  },
};

function jsonResponse(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      'Content-Type': 'application/json',
      'Access-Control-Allow-Origin': '*',
    },
  });
}
