# Bumblebee — WooCommerce Product Builder

Product builder for WooCommerce with:
- One-click **Create a Product** flow
- Auto **title prefix** with Company Name
- **WebP conversion**
- Attachment parenting for original art
- Deterministic parent + variation **SKUs**
- **AI** single-shot generation of **Description**, **Short description**, and **Product tags**
  - Inputs: product title, final image, vendor code
  - Never includes vendor names, sizes, or colors; never mentions “Bear Traxs”
  - Primary→Secondary API key failover
- Settings: primary/secondary OpenAI keys, enable/disable AI, copy styles, and **key test** buttons with spinner ✔/✖

> **Tested up to WordPress 6.8.3**. Requires WooCommerce and PHP ≥ 7.4 (PHP 8.3 recommended).

---

## Quick Start

1. **Clone**
   ```bash
   git clone https://github.com/emkowale/bumblebee.git
   ```
2. **Install** the plugin folder into `/wp-content/plugins/bumblebee/` and activate “Bumblebee” in WP Admin.
3. **Configure**
   - Go to **Bumblebee → Settings**
   - Enter **Primary** and **Secondary** OpenAI API keys
   - Select at least one **Copy Style** (defaults to Friendly + Concise)
   - (Optional) Disable AI if you only want the media/variation pipeline
   - Click **Test Primary/Secondary Key** (spinner + ✔/✖)
4. **Create a Product**
   - Use **Bumblebee → Create a Product**
   - Upload/select your product image + art, colors, sizes, vendor code
   - Submit → Bumblebee creates parent + variations and **applies AI content** once

---

## How It Works

### File Map
- `bumblebee.php` — plugin bootstrap, update checker, admin menus, includes
- `includes/create.php` — admin UI for the creation form
- `includes/create_handler.php` — AJAX handler:
  - Converts media to WebP
  - Builds attributes & variations; sets SKUs
  - Parents art to the product
  - Calls **`BB_AI_Content::generate()` once** and updates:
    - `post_content` (Description)
    - `post_excerpt` (Short description)
    - `product_tag` taxonomy (Tags)
- `includes/ai.php` — the AI engine:
  - Reads keys from `Bumblebee → Settings`
  - Enforces content rules (no vendor/size/color/“Bear Traxs”)
  - Calls **OpenAI Chat Completions** with a **single JSON response** containing all fields
  - Immediate **failover** on HTTP 429
- `includes/settings.php` — settings page + **key test** AJAX
- `assets/settings.js` — inline spinner by the test buttons

### One Request Per Product
The AI payload requests:
```json
{ "description": "...", "short_description": "...", "tags": ["..."] }
```
No multiple calls. No background jobs.

---

## Development

### Requirements
- WordPress 6.8.3
- PHP 7.4+ (8.3 recommended)
- WooCommerce

### Local Build & Lint
Use your preferred PHP linter/formatter. No build step is required.

### Releasing
We keep versions in `bumblebee.php` (`BUMBLEBEE_VERSION`) and **`readme.txt`** (`Stable tag`).  
Use `release.sh`:

```bash
./release.sh v1.3.1
```

Expected actions:
- Tag `v1.3.1`
- Update version constants/files
- Build/upload zip (if configured)

> **Don’t change `release.sh`** – it already works well.

---

## Changelog

See **`CHANGELOG.md`** for human-readable changes.

---

## License

GPLv2 or later. © Eric Kowalewski. See `LICENSE`.
