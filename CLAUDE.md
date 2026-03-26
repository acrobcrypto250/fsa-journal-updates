# CLAUDE.md — FundedControl Project Context
## Complete Reference for Claude Code (AI Development Assistant)

**Product:** FundedControl (formerly FSA Trading Journal)
**Developer:** Acrob — Solo developer, crypto trader, Kigali, Rwanda
**Experience:** 10 years PHP
**Last Updated:** March 2026

---

## 1. PROJECT OVERVIEW

### What Is FundedControl?

A professional trading journal SaaS built specifically for **prop firm traders**. Traders log every trade, track challenge progress, manage risk limits, and review performance — all inside one disciplined tool.

**Core problem it solves:** Prop firm traders fail challenges because they have no structured accountability system. FundedControl gives them real-time risk alerts, rule enforcement, and performance analytics designed around prop firm rules.

### Live Details

| Field | Value |
|-------|-------|
| Live URL | https://journal.acrobcrypto.com |
| Updater | https://journal.acrobcrypto.com/updater.php |
| GitHub Repo | https://github.com/acrobcrypto250/fsa-journal-updates |
| Domain (rebranding) | fundedcontrol.com |
| Blog | acrobcrypto.com |
| DB Name | `theittav_journal` on `localhost` |
| Current Version | v3.0.0 |

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1 |
| Database | MySQL (PDO, prepared statements) |
| Frontend | Vanilla JS + Chart.js |
| Hosting | Namecheap shared hosting (cPanel) |
| No frameworks | No Laravel, no React, no Composer |

---

## 2. ARCHITECTURE — v3.0.0 MODULAR BACKEND

### The Core Principle

Every feature is an **independent PHP controller class**. The router maps API actions to controller methods. Adding a feature = creating a new file + one line in `router.php`. Zero edits to existing files.

### Request Flow

```
Browser (JS api() call)
    ↓
includes/api.php        ← 14-line thin wrapper
    ↓
includes/config.php     ← DB connection (NEVER in GitHub)
includes/helpers.php    ← Shared utility functions
    ↓ CSRF check + requireLogin()
includes/router.php     ← Maps action → [Controller, method]
    ↓
includes/controllers/   ← Independent controller class
    ↓
JSON Response → Browser
```

### File Structure

```
fundedcontrol.com/
│
├── index.php                          ← App shell: sidebar + page router
├── login.php                          ← Login page
├── register.php                       ← NEW: Registration + email verification
├── logout.php                         ← Session destroy + redirect
├── updater.php                        ← GitHub auto-updater (DO NOT MODIFY)
├── version.json                       ← Version tracking
│
├── includes/
│   ├── config.php                     ← ⛔ DB CREDENTIALS — NEVER IN GITHUB
│   ├── api.php                        ← Thin API entry (14 lines max)
│   ├── helpers.php                    ← Shared functions
│   ├── router.php                     ← Action → controller routing
│   │
│   └── controllers/
│       ├── ProfileController.php      ← get_user, update_profile (95 lines)
│       ├── ChallengeController.php    ← CRUD challenges, switch (108 lines)
│       ├── TradeController.php        ← CRUD trades, scoped to challenge (121 lines)
│       ├── StatsController.php        ← Statistics (90 lines)
│       ├── AlertController.php        ← Risk alerts (54 lines)
│       ├── CalculatorController.php   ← Position size calc (27 lines)
│       ├── PairController.php         ← Pair management (40 lines)
│       ├── ImportController.php       ← Excel batch import (35 lines)
│       ├── StrategyController.php     ← Strategy tester (47 lines)
│       ├── ReviewController.php       ← Weekly reviews (34 lines)
│       └── OnboardingController.php   ← NEW: Setup wizard
│
├── pages/                             ← HTML only, no logic (Phase 2)
│   ├── dashboard.php
│   ├── trades.php
│   ├── stats.php
│   ├── calculator.php
│   ├── strategy.php
│   ├── review.php
│   ├── profile.php
│   ├── challenges.php
│   └── onboarding.php
│
├── modals/                            ← Modal HTML only (Phase 2)
│   ├── trade-modal.php
│   ├── trade-view-modal.php
│   ├── challenge-modal.php
│   ├── review-modal.php
│   ├── strategy-modal.php
│   ├── pairs-modal.php
│   ├── import-modal.php
│   └── checklist-modal.php
│
├── js/
│   ├── app.js                         ← Core: api(), nav, toast (100 lines max)
│   ├── dashboard.js
│   ├── trades.js
│   ├── stats.js
│   ├── calculator.js
│   ├── strategy.js
│   ├── review.js
│   ├── profile.js
│   ├── challenges.js
│   ├── import.js
│   └── onboarding.js
│
├── css/
│   ├── style.css                      ← Core layout, variables, components
│   └── brand.css                      ← FundedControl colors + fonts (Phase 3)
│
├── media/
│   └── uploads/{user_id}/             ← User trade screenshots
│
└── backups/                           ← Auto-created by updater
```

---

## 3. DATABASE SCHEMA

### Tables

**users**
```sql
id, username, password, display_name, avatar_color, bio,
account_balance, starting_balance, max_drawdown_pct,
daily_loss_limit, risk_per_trade_pct, prop_firm, challenge_phase,
email, email_verified, verification_token, created_at,
onboarding_completed
```
> Note: `account_balance` through `challenge_phase` are legacy fields kept for backward compat. New data uses the `challenges` table.

**challenges** (added v2.3.0)
```sql
id, user_id, name, prop_firm, challenge_phase,
starting_balance, current_balance, max_drawdown_pct,
daily_loss_limit, risk_per_trade_pct, profit_target_pct,
status (active/completed/failed), is_active (0/1), created_at
```

**trades**
```sql
id, user_id, challenge_id, trade_date, session, time_in, time_out,
pair, direction, entry_price, stop_loss, take_profit, exit_price,
lot_size, risk_amount, fees, pnl, net_pnl, r_multiple,
result, confidence, exec_score, fib_level, fsa_rules,
notes, screenshot
```

**pairs**
```sql
id, user_id, symbol, active (0/1)
```

**strategy_tests**
```sql
id, user_id, strategy_name, timeframe, market,
rule1-rule5, test_date, pair, direction, r1-r5,
result, fib_level, r_multiple, net_pnl, session, notes, created_at
```

**weekly_reviews**
```sql
id, user_id, week_start, week_end, process_score, mindset_score,
key_lesson, what_went_well, what_to_improve, rules_followed
```

**daily_limits**
```sql
user_id, log_date, daily_pnl, trades_count
```

### Data Relationships

```
users (1) ──→ (many) challenges
challenges (1) ──→ (many) trades
users (1) ──→ (many) pairs
users (1) ──→ (many) strategy_tests
users (1) ──→ (many) weekly_reviews
```

### Challenge Scoping Rule

All trade queries must include:
```sql
WHERE user_id = ? AND (challenge_id = ? OR challenge_id IS NULL)
```
The `OR challenge_id IS NULL` handles trades from before v2.3.0.

---

## 4. API ENDPOINTS REFERENCE

### How JavaScript Calls the API

```javascript
// GET
const data = await api('get_trades');

// GET with parameters
const data = await api('get_trades&pair=BTCUSDT&from=2026-03-01');

// POST with JSON body
const data = await api('add_trade', 'POST', { pair: 'BTCUSDT', direction: 'Long' });

// POST with file upload
const fd = new FormData();
fd.append('screenshot', fileInput.files[0]);
const resp = await fetch('includes/api.php?action=add_trade', { method: 'POST', body: fd });
```

### Endpoint Map

| Action | Method | Controller | Description |
|--------|--------|-----------|-------------|
| `get_user` | GET | ProfileController | Current user + active challenge |
| `update_profile` | POST | ProfileController | Name, avatar, bio, password |
| `update_settings` | POST | ProfileController | Legacy compat — profile + challenge |
| `get_challenges` | GET | ChallengeController | List all challenges |
| `get_active_challenge` | GET | ChallengeController | Active challenge details |
| `add_challenge` | POST | ChallengeController | Create challenge |
| `update_challenge` | POST | ChallengeController | Edit challenge |
| `delete_challenge` | POST | ChallengeController | Delete challenge + trades |
| `switch_challenge` | POST | ChallengeController | Set active challenge |
| `get_trades` | GET | TradeController | List trades (filtered, challenge-scoped) |
| `add_trade` | POST | TradeController | Log new trade |
| `update_trade` | POST | TradeController | Edit trade |
| `delete_trade` | POST | TradeController | Delete trade + screenshot |
| `get_stats` | GET | StatsController | Full stats for active challenge |
| `get_alerts` | GET | AlertController | Today's risk alerts |
| `calculate_risk` | POST | CalculatorController | Position size calculator |
| `get_pairs` | GET | PairController | List active pairs |
| `add_pair` | POST | PairController | Add trading pair |
| `delete_pair` | POST | PairController | Soft-delete pair |
| `import_trades` | POST | ImportController | Batch import from Excel (max 500) |
| `get_strategy_trades` | GET | StrategyController | List strategy tests |
| `get_strategy_stats` | GET | StrategyController | Strategy test statistics |
| `add_strategy_trade` | POST | StrategyController | Log strategy test |
| `delete_strategy_trade` | POST | StrategyController | Delete strategy test |
| `get_reviews` | GET | ReviewController | List weekly reviews |
| `save_review` | POST | ReviewController | Create/update review |

---

## 5. HELPER FUNCTIONS REFERENCE

### helpers.php

| Function | Purpose |
|----------|---------|
| `csrfCheck()` | Validates Origin/Referer on form POSTs |
| `num($val, $default)` | Safely converts to float |
| `validId($val)` | Validates positive integer ID |
| `safeMediaDir($uid)` | Returns upload dir, creates if needed |
| `handleScreenshot($uid)` | Upload: 5MB limit + MIME check + random filename |
| `getActiveChallenge()` | Returns active challenge row for current user |
| `jsonInput()` | Reads JSON POST body |
| `jsonResponse($data)` | Sends JSON + exits |
| `jsonError($msg)` | Sends error JSON + exits |

### config.php (already exists, never edit)

| Function | Purpose |
|----------|---------|
| `getDB()` | Returns PDO database connection |
| `uid()` | Returns current user's ID from session |
| `currentUser()` | Returns full user row |
| `requireLogin()` | Redirects to login if not authenticated |

---

## 6. HOW TO ADD A NEW FEATURE

### Pattern (3 steps, zero edits to existing files)

**Step 1: Create controller**
```php
// includes/controllers/AiCoachController.php
class AiCoachController {
    private $db;
    private $uid;

    public function __construct() {
        $this->db = getDB();
        $this->uid = uid();
    }

    public function getAdvice() {
        jsonResponse(['advice' => 'Wait for confirmation candle']);
    }
}
```

**Step 2: Add one line to router.php**
```php
'get_ai_advice' => ['AiCoachController', 'getAdvice'],
```

**Step 3: Call from JavaScript**
```javascript
const advice = await api('get_ai_advice');
```

That's it. No risk of breaking anything else.

---

## 7. UPDATE WORKFLOW (MANDATORY — NEVER SKIP)

Every code change follows this exact process:

1. **Claude provides complete updated files** (never partial snippets)
2. **Acrob uploads to GitHub:**
   - `version.json` → repo root (with bumped version number)
   - Changed files → `app/` folder matching live directory structure
3. **Acrob visits** `updater.php` → Check for Updates → Update Now
4. Live in seconds — no FTP, no manual file replacement

### version.json Structure

```json
{
    "version": "3.0.1",
    "date": "2026-03-21",
    "changelog": "Description of changes",
    "files": [
        "index.php",
        "includes/router.php",
        "includes/controllers/TradeController.php"
    ],
    "db_migrations": [
        {
            "name": "add_email_to_users",
            "sql": "ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL"
        }
    ]
}
```

### ⛔ NEVER include in version.json files list:
- `includes/config.php` — has DB credentials
- `media/uploads/` — user data
- `backups/` — backup files

### Protected Files (updater never overwrites)

- `includes/config.php` — marked `"critical": true`
- `media/uploads/` — user screenshots
- `backups/` — auto-backups

---

## 8. SECURITY RULES

| Feature | Implementation |
|---------|---------------|
| CSRF Protection | Origin/Referer validation via `csrfCheck()` |
| Input Validation | All numeric fields through `num()` |
| ID Validation | All IDs through `validId()` |
| Passwords | `password_hash()` + `password_verify()` |
| SQL Injection | Prepared statements with `?` everywhere |
| User Isolation | Every query includes `WHERE user_id=?` |
| File Uploads | 5MB limit, extension whitelist, MIME verification |
| Secure Filenames | `bin2hex(random_bytes(16))` |
| Import Limits | Max 500 trades per batch |
| Error Sanitization | No raw user input in error messages |
| Directory Traversal | `basename()` on all file paths |

---

## 9. BRAND IDENTITY — FUNDEDCONTROL

### Identity

| Element | Value |
|---------|-------|
| Product name | FundedControl |
| Former name | FSA Trading Journal |
| Tagline | Control Your Trading. Get Funded. Stay Funded. |
| Alt tagline | Discipline is the edge. |
| Positioning | Discipline-first, no hype, real execution |
| Style | "Light Authority" — Chase + Bloomberg inspired |

### Brand Colors (CSS Variables)

```css
:root {
    --fc-bg:             #FAFBFC;           /* Page background — off-white */
    --fc-card:           #FFFFFF;           /* Card backgrounds */
    --fc-border:         #E2E8F0;           /* Borders */
    --fc-sidebar:        #0B1D3A;           /* Sidebar — deep blue */
    --fc-primary:        #1A56DB;           /* Primary buttons/links */
    --fc-success:        #0FA958;           /* Profit, wins — green */
    --fc-danger:         #DC3545;           /* Loss, risk — red */
    --fc-warning:        #F59E0B;           /* Caution — amber */
    --fc-info:           #3B82F6;           /* Info — blue */
    --fc-text:           #0B1D3A;           /* Primary text */
    --fc-muted:          #6C7A8D;           /* Secondary text */
    --fc-text-light:     #F0F3F7;           /* Text on dark bg */
    --fc-sidebar-muted:  #7A8FA5;           /* Sidebar nav items */
    --fc-active:         rgba(26,86,219,0.3); /* Active nav bg */
    --fc-badge-win:      #E3F2E8;           /* Win badge bg */
    --fc-badge-win-text: #1B7A3D;           /* Win badge text */
    --fc-badge-loss:     #FDEAEA;           /* Loss badge bg */
    --fc-badge-loss-text:#DC3545;           /* Loss badge text */
}
```

### Brand Fonts

```
https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap
```

| Font | Weight | CSS Variable | Usage |
|------|--------|-------------|-------|
| Outfit | 600 | `--fc-font-display` | Headlines, titles, logo |
| Outfit | 400 | `--fc-font-body` | Body, tables, labels, buttons |
| JetBrains Mono | 500 | `--fc-font-mono` | Numbers, P&L, balance, %, R |

### UI Rules
- Background: off-white (`#FAFBFC`) — NOT dark
- Cards: white with `#E2E8F0` borders, `8px` radius
- Sidebar: the ONLY dark element — provides authority contrast
- Green: ONLY for profit/wins — never decorative
- Red: ONLY for loss/danger — never decorative

---

## 10. FEATURE ROADMAP & CURRENT PRIORITIES

### Version History

| Version | Date | What Changed |
|---------|------|-------------|
| v2.0.0 | 2026-03-10 | Initial FSA Journal — monolithic |
| v2.2.5 | 2026-03-17 | Screenshot upload fix, session fix |
| v2.2.7 | 2026-03-19 | Security hardening (CSRF, validation, file upload) |
| v2.3.0 | 2026-03-20 | Challenge system + multi-challenge support |
| v3.0.0 | 2026-03-20 | Modular backend — 11 controllers replace monolithic api.php |

### Build Phases (Current Focus)

**Phase 2 — Frontend Split (in progress)**
- Split `index.php` (540 lines) into `pages/` files
- Split modals into `modals/` files
- Split `app.js` (800+ lines) into JS modules per page
- Zero visual changes — pure refactor

**Phase 3 — Brand Refresh**
- Create `css/brand.css`
- Rebrand login.php and sidebar to FundedControl
- Apply CSS variables to all components

**Phase 4 — New Features (Week 1 tasks)**
- [ ] Registration (`register.php` + `AuthController`)
- [ ] Email verification via Namecheap SMTP
- [ ] Onboarding wizard (`OnboardingController`) — 5 min setup
- [ ] Universal prop firm setup (user sets own rules)
- [ ] Custom strategy rules (not just FSA)
- [ ] Any trading pair support (Forex, Indices, Crypto)
- [ ] Screenshot upload fix (`MEDIA_BASE_DIR` bug — see bugs section)

### SaaS Pricing Plan

| Tier | Price | Limit |
|------|-------|-------|
| Free | $0 | 7 trades |
| Pro | $15/month | Unlimited |
| Elite | $29/month | Unlimited + AI features |

**Target:** 1,000 users = $19,200/month revenue

---

## 11. KNOWN BUGS

### Bug 1: MEDIA_BASE_DIR constant (config.php line 15)

```php
// BROKEN — uses DIR (wrong constant)
define('MEDIA_BASE_DIR', DIR . '/../media/uploads/');

// FIXED — should be __DIR__
define('MEDIA_BASE_DIR', __DIR__ . '/../media/uploads/');
```
> ⚠️ config.php is NEVER uploaded to GitHub. Fix must be applied manually in cPanel File Manager.

---

## 12. DEBUGGING GUIDE

### Backend Error
1. F12 → Network tab → Find failing API call → Check Response
2. Error tells you which controller to open (they are 27–121 lines each)
3. Example: `get_trades` fails → open `TradeController.php`

### Frontend Error
1. F12 → Console tab → Red error shows function name + file
2. Hard refresh first: `Ctrl+Shift+R`

### Common Issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| "Loading..." in sidebar | No challenges in DB | Create one via Challenges page |
| Function not defined | Browser cache | Hard refresh Ctrl+Shift+R |
| 500 error on API | PHP syntax error | Check cPanel Error Log |
| Screenshots not saving | Permission issue | Set `media/uploads/` to 755 |
| "Unknown action" | Route not in router.php | Add route line |

---

## 13. DEVELOPMENT RULES (ABSOLUTE)

1. **Always give complete files** — never partial code snippets
2. **Always bump version number** with every update
3. **Always use GitHub updater workflow** — never manual file replacement
4. **config.php is NEVER in GitHub** — ever, under any circumstances
5. **Never put logic in api.php** — it's a 14-line wrapper
6. **Never put logic in index.php** — it's an HTML shell
7. **One controller per domain** — never combine features
8. **Shared functions go in helpers.php** — not duplicated
9. **Database changes go through version.json migrations** — never manual SQL
10. **Brand lives in CSS variables** — never hardcode colors
11. **Always test after deploy** — dashboard, add trade, challenges, profile, calculator
12. **Never edit updater.php** — it's battle-tested

---

## 14. DEVELOPER PROFILE

| Field | Detail |
|-------|--------|
| Name | Acrob |
| Location | Kigali, Rwanda (GMT+2) |
| PHP Experience | 10 years |
| Team | Solo developer |
| Trading | Crypto — prop firm (BitFunded) |
| Strategy | FSA — Fibonacci + S/R + Anchored VWAP |
| Trading pairs | BTCUSDT, ETHUSDT, BNBUSDT |
| Sessions | London 10-12 / NY 15-19 (GMT+2) |

### FSA Trading Strategy (5 Rules — ALL must be true)

1. 4H context clear — bullish or bearish bias
2. Price at Fib level on 1H (0.382, 0.5, or 0.618)
3. S/R confluence at that level
4. Price ABOVE Anchored VWAP for long / BELOW for short
5. 15M rejection candle CLOSED (pin bar or engulfing)

### Prop Firm Details (BitFunded $10K Challenge)

| Setting | Value |
|---------|-------|
| Phase | 1 |
| Profit Target | 8% |
| Max Drawdown | 10% |
| Daily Drawdown | 5% |
| Current Balance | ~$9,384 |
| Risk — Recovery | 0.25% per trade |
| Risk — Normal | 0.50% per trade |
| Risk — Passing | 1.00% per trade |

---

## 15. NAMECHEAP SMTP CONFIG (for email verification)

Hosting: Namecheap shared hosting cPanel
Use PHP `mail()` or `PHPMailer` with cPanel SMTP credentials.
SMTP host is typically `mail.acrobcrypto.com` or the server's hostname.
Credentials are stored in `includes/config.php` — ask Acrob for exact values when implementing.

---

## 16. QUICK REFERENCE — WHAT TO DO

### When adding a feature:
1. Create `includes/controllers/FeatureNameController.php`
2. Add one line to `includes/router.php`
3. Create `pages/feature.php` (HTML only)
4. Create `js/feature.js`
5. Include new JS file in `index.php`
6. Update `version.json` with new version + all changed files

### When fixing a bug:
1. Identify the controller or file
2. Fix the file
3. Bump version
4. Upload to GitHub via updater workflow

### When adding a DB column:
```json
"db_migrations": [
    {
        "name": "add_column_name_to_table",
        "sql": "ALTER TABLE table ADD COLUMN column_name TYPE DEFAULT NULL"
    }
]
```
Add to `version.json` — updater runs it automatically, skips gracefully if already exists.

---

*FundedControl — Control Your Trading. Get Funded. Stay Funded.*
*Built by Acrob — a trader who's been in the red and came back disciplined.*
