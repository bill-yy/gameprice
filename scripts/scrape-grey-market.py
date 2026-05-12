#!/usr/bin/env python3
"""
Grey Market Price Scraper for GamePrice
Scrapes real prices from Instant Gaming and Kinguin using Playwright.

Usage:
    python3 scrape-grey-market.py

Requirements:
    pip install playwright
    playwright install chromium

Output:
    Writes to data/grey_market_prices.json
"""

import asyncio
import json
import re
import urllib.parse
from playwright.async_api import async_playwright


async def scrape_instant_gaming(page, game_title: str):
    """Search Instant Gaming and return best price"""
    try:
        search_url = f"https://www.instant-gaming.com/en/search/?query={urllib.parse.quote(game_title)}"
        await page.goto(search_url, wait_until="networkidle", timeout=30000)
        await asyncio.sleep(2)

        # Check if there are results
        result_link = await page.query_selector('a[href*="/en/"] h3')
        if not result_link:
            return None

        # Click first result to get price
        first_result = await page.query_selector('.search-results a[href^="/en/"]')
        if not first_result:
            return None

        href = await first_result.get_attribute('href')
        if not href:
            return None

        product_url = f"https://www.instant-gaming.com{href}"
        await page.goto(product_url, wait_until="networkidle", timeout=30000)
        await asyncio.sleep(2)

        # Extract price
        price_el = await page.query_selector('.price, [data-price], .current-price')
        if price_el:
            price_text = await price_el.text_content()
            price_match = re.search(r'([\d,.]+)', price_text)
            if price_match:
                price = float(price_match.group(1).replace(',', '.'))
                return {
                    'store': 'instant-gaming',
                    'title': game_title,
                    'price_eur': price,
                    'url': product_url,
                }
    except Exception as e:
        print(f"Instant Gaming error for {game_title}: {e}")
    return None


async def scrape_kinguin(page, game_title: str):
    """Search Kinguin and return best price"""
    try:
        search_url = f"https://www.kinguin.net/catalogsearch/result/?q={urllib.parse.quote(game_title)}"
        await page.goto(search_url, wait_until="networkidle", timeout=30000)
        await asyncio.sleep(3)

        # Check for Cloudflare challenge
        if "Just a moment" in await page.title():
            print(f"Kinguin Cloudflare challenge for {game_title}")
            return None

        # Extract first product
        product = await page.query_selector('.product-item-info')
        if not product:
            return None

        price_el = await product.query_selector('.price')
        link_el = await product.query_selector('a')

        if price_el and link_el:
            price_text = await price_el.text_content()
            href = await link_el.get_attribute('href')
            price_match = re.search(r'([\d,.]+)', price_text)
            if price_match and href:
                price = float(price_match.group(1).replace(',', '.'))
                return {
                    'store': 'kinguin',
                    'title': game_title,
                    'price_eur': price,
                    'url': href if href.startswith('http') else f"https://www.kinguin.net{href}",
                }
    except Exception as e:
        print(f"Kinguin error for {game_title}: {e}")
    return None


async def main():
    # Load game list from existing cheapshark data or API
    games = [
        "Baldur's Gate 3",
        "Elden Ring",
        "Counter-Strike 2",
        "The Witcher 3",
        "Cyberpunk 2077",
        "Red Dead Redemption 2",
        "Grand Theft Auto V",
        "Dark Souls III",
        "Hogwarts Legacy",
        "Starfield",
    ]

    results = []

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            viewport={"width": 1920, "height": 1080},
        )
        page = await context.new_page()

        for game in games:
            print(f"Scraping {game}...")

            ig_result = await scrape_instant_gaming(page, game)
            if ig_result:
                results.append(ig_result)
                print(f"  IG: €{ig_result['price_eur']}")

            kinguin_result = await scrape_kinguin(page, game)
            if kinguin_result:
                results.append(kinguin_result)
                print(f"  Kinguin: €{kinguin_result['price_eur']}")

            await asyncio.sleep(2)

        await browser.close()

    # Save results
    output = {"scraped_at": asyncio.get_event_loop().time(), "results": results}
    with open("data/grey_market_prices.json", "w") as f:
        json.dump(output, f, indent=2)

    print(f"\nDone! Scraped {len(results)} prices.")


if __name__ == "__main__":
    asyncio.run(main())
