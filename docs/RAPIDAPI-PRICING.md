# GamePrice API — RapidAPI Pricing Strategy

## 🎯 Positioning

**GamePrice** is the only game price comparison API that searches 8 major digital stores in real-time, including grey market stores like Eneba and G2A where the best deals usually hide.

**Value proposition:** Save your users money by showing them the cheapest legitimate game keys across the entire market, not just Steam.

---

## 📊 Pricing Tiers

### Free — Developer
**Price:** $0/month

Perfect for testing, prototypes, and small personal projects.

| Feature | Limit |
|---------|-------|
| Daily requests | 100 |
| Rate limit | 10/min |
| Endpoints | All |
| Cache TTL | 30 min |
| Support | Community (GitHub issues) |

**Who is it for:** Indie devs building game-related tools, students, hobbyists testing integrations.

---

### Basic — Hobbyist  
**Price:** $9.99/month (~$0.33/day)

For active projects with real users.

| Feature | Limit |
|---------|-------|
| Daily requests | 10,000 |
| Rate limit | 60/min |
| Endpoints | All |
| Cache TTL | 30 min |
| Support | Email (48h response) |

**Value:** ~300K requests/month. Enough for a mid-size price tracker or affiliate site.

**Who is it for:** Bloggers with game deal sections, small affiliate sites, Discord bots, mobile apps.

---

### Pro — Professional
**Price:** $29.99/month (~$1/day)

For serious businesses that need speed and reliability.

| Feature | Limit |
|---------|-------|
| Daily requests | 100,000 |
| Rate limit | 120/min |
| Endpoints | All + Webhooks |
| Cache TTL | 15 min (fresher data) |
| Support | Priority (24h response) |
| Webhooks | ✅ Price drop alerts |

**Value:** ~3M requests/month. Cache refreshes twice as fast. Webhooks notify you instantly when a game's price drops below a threshold.

**Who is it for:** Large affiliate networks, game review sites, price tracking platforms, Telegram/Discord deal channels with 10K+ users.

---

### Ultra — Enterprise
**Price:** $99.99/month

For companies that need the absolute best performance and custom features.

| Feature | Limit |
|---------|-------|
| Daily requests | Unlimited |
| Rate limit | 300/min |
| Endpoints | All + Webhooks + Historical |
| Cache TTL | 5 min (near real-time) |
| Support | Dedicated Slack channel |
| Webhooks | ✅ Price drop alerts |
| Historical data | ✅ 90-day price history |
| White-label | ✅ Remove branding |

**Value:** Near real-time data. Historical price charts for your users. Dedicated support.

**Who is it for:** Major affiliate networks, game journalism sites, comparison platforms, resellers.

---

## 💡 Pricing Psychology

### Why this pricing works:

1. **Free is truly free** — No credit card required. Builds trust.
2. **Basic is 10× cheaper per request than Pro** — Users feel smart upgrading.
3. **Pro has webhooks** — This is the "killer feature" that justifies the jump from Basic.
4. **Ultra is 3× Pro** — But unlimited + historical data makes it feel like a bargain for enterprises.
5. **$9.99 is an impulse buy** — Less than a Netflix subscription. Easy yes.

### Conversion funnel:
```
Free (100/day) → hits limit → "Upgrade to Basic for 100× more"
Basic (10K/day) → wants webhooks → "Upgrade to Pro for price alerts"
Pro (100K/day) → needs historical charts → "Upgrade to Ultra"
```

---

## 🏆 Competitive Analysis

| Service | Price | Stores | Real-time | Grey Market |
|---------|-------|--------|-----------|-------------|
| **GamePrice** | $0-$99 | 8 | ✅ Yes | ✅ Yes |
| CheapShark API | Free | 60+ | ❌ Static | ❌ No |
| IsThereAnyDeal | N/A | 30+ | ❌ Static | ❌ No |
| Steam API | Free | 1 (Steam) | ✅ Yes | ❌ No |

**Our differentiator:** We're the only API that searches grey market stores (Eneba, G2A, Kinguin, Instant Gaming) where prices are typically 30-70% cheaper than official stores.

---

## 📈 Revenue Projections

| Tier | Monthly Price | Conversion Rate | Avg Users | Monthly Revenue |
|------|--------------|-----------------|-----------|-----------------|
| Free | $0 | 100% (entry) | 5,000 | $0 |
| Basic | $9.99 | 5% | 250 | $2,497 |
| Pro | $29.99 | 2% | 100 | $2,999 |
| Ultra | $99.99 | 0.5% | 25 | $2,499 |
| **Total** | | | | **~$8,000/mo** |

Conservative estimate with 5,000 free users.

---

## 🚀 RapidAPI Listing Tips

### Title:
**"GamePrice — Real-Time Game Price Comparison API (8 Stores, Grey Market Included)"**

### Short description:
"Search 8 digital game stores simultaneously and find the cheapest prices. Includes Eneba, G2A, Instant Gaming, Kinguin, and more. Save your users 30-70% on every game."

### Keywords:
game prices, video game deals, steam prices, cheap games, game comparison, game deals API, game prices API, grey market prices, CD key prices, game affiliate API

### Categories:
- Gaming
- Entertainment
- Data
- eCommerce

### Endpoint descriptions for RapidAPI:
Use the OpenAPI spec (`docs/openapi.yaml`) which already has detailed descriptions.

---

## 🎁 Launch Promotion

**"Early Adopter Discount"**
- First 100 Pro subscribers get 50% off for life
- Use coupon code: `RAPIDLAUNCH50`
- First 50 Ultra subscribers get 3 months free

This creates urgency and helps us get initial traction + reviews on RapidAPI.

---

## 📋 Setup Checklist for RapidAPI

- [ ] Create RapidAPI account
- [ ] Add API with OpenAPI spec
- [ ] Upload pricing tiers
- [ ] Set up webhook endpoint (for Pro/Ultra)
- [ ] Configure rate limits per plan
- [ ] Add example responses
- [ ] Write FAQ section
- [ ] Enable "Test Endpoint" feature
- [ ] Add code samples (curl, JS, Python)
- [ ] Launch on Product Hunt / Hacker News
