# Task 21: Add console store scrapers (PSN, Xbox, CDKeys)

## Context
GamePrice currently scrapes PC key stores (Eneba, Instant Gaming, G2A, Kinguin, CheapShark). Many games are console-exclusive (PS5, Xbox) and have no PC prices.

## What exists
- `app/Services/Scrapers/` directory with PC store scrapers
- Each scraper has `search(string $query): ?array`
- `app/Jobs/FetchPricesForGame.php` calls all scrapers and saves to `products` table

## What to build
1. Create `app/Services/Scrapers/CDKeysScraper.php`:
   - Search: `https://www.cdkeys.com/catalogsearch/result/?q={query}`
   - Extract product name, price, URL
   - Return format: `[[name, price, url, store]]`
   
2. Create `app/Services/Scrapers/PSNStoreScraper.php`:
   - Search PSN Store API or web: `https://store.playstation.com/es-es/search/{query}`
   - Extract game name, price, platform (PS5/PS4), URL
   - Handle "Only on PlayStation" exclusives
   
3. Create `app/Services/Scrapers/XboxStoreScraper.php`:
   - Search Xbox Store: `https://www.xbox.com/es-ES/games/store/search?q={query}`
   - Extract name, price, platform (Xbox Series X｜S), URL
   
4. Update `app/Jobs/FetchPricesForGame.php` to also call these 3 new scrapers
   - Add them alongside existing scrapers
   - Save results with correct platform info (PS5, Xbox Series X|S, etc.)

## Important constraints
- Do NOT break existing scrapers
- Use same return format `[[name, price, url, store]]`
- Handle failures gracefully (return empty array on error)
- Respect robots.txt and add delays between requests (200ms)
- User-Agent: `GamePriceBot/1.0 (price comparison service)`
