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


## Mockup image sources

Each WordPress user can choose **WordPress Media Library** or **Google Drive** in
Bumblebee Settings or on Create a Product. The choice saves immediately for that
user and applies to the next image they select. Switching sources does not alter
existing products or already-selected color images; both sources can be used in
one product.

For Drive, paste a file link or file ID in the image chooser. Without a private
connection, share that image as **Anyone with the link**. Supported images are
PNG, JPG, and WebP, up to 20 MB and 40 million pixels. Selected images become
public product images regardless of their original Drive sharing permissions.

To browse a private folder, an administrator configures the mockup folder ID and
the absolute path to a service-account JSON key in Bumblebee Settings. Enable the
Google Drive API for that service account's project and share the folder with its
service-account email as Viewer. Keep the JSON file outside every public web root
and readable by the site's PHP user. Alternatively, set
`BUMBLEBEE_DRIVE_SERVICE_ACCOUNT_FILE` in `wp-config.php`. Credentials are read on
the server and never sent to the browser or included in releases. The browser
lists supported images directly inside the configured folder, with pagination.

Drive remains the source of these images. Bumblebee serves a local cache from
`uploads/bumblebee-drive-cache` and stores a hidden WordPress attachment record
for WooCommerce product, variation, and Soundwave compatibility. Those records
are excluded from Media Library grid/list views and the normal image chooser.
Drive caches skip the normal Media Library WebP conversion to preserve their
source references. They are refreshed on selection after 24 hours, or in the
background through WP-Cron when a stale image is used. A Drive outage keeps the
last successful cached image; failed background refreshes retry after 24 hours.
Cache filenames remain stable, so a separate page cache or CDN may need purging
after replacing an existing Drive image.
