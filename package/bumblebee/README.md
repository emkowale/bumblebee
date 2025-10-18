# Bumblebee
**Author:** Eric Kowalewski  
**Plugin URI:** https://github.com/emkowale/bumblebee  
**Version:** 1.2.99

## What this plugin does
- Adds **Create a Product** flow (variable products: **Color × Size**) with inline validation (no alerts).
- Saves human-readable custom fields: **Company Name**, **Production**, **Print Location**, **Site Slug**, **Original Art**, **Vendor Code**.
- **Brand** auto-creates/assigns `{Company Name} Merch` (uses WP site title).
- Converts media to **WebP** and renames + sets all media meta to:  
  `{Company Name} {Product Title} brought to you by The Bear Traxs thebeartraxs.com`.

## Settings
- Primary/Secondary OpenAI keys (with test buttons placeholder)
- **AI Generation** toggle (`bumblebee_disable_ai`)
- **Copy Styles** whitelist
- **Orphaned Media Cleanup** (Preview + Delete)

## SKU
`{site_slug}-{productId}-{variationId}` where `site_slug` is the first subdomain of `home_url()` or `site`.

## Updater
This plugin includes a lightweight **GitHub updater** that checks **emkowale/bumblebee** releases and surfaces updates in WP Admin → Plugins.  
- It looks for an asset named `bumblebee-vX.Y.Z.zip` on the latest GitHub Release.  
- Fallback: uses GitHub `zipball_url`.

## Build / Install
1. Zip the directory as `bumblebee-vX.Y.Z.zip` and upload via WP → Plugins → Add New → Upload.
2. Folder must be named `bumblebee/` and main file `bumblebee.php` with **Plugin Name: Bumblebee**.

## Changelog
See `CHANGELOG.md`.

— Generated 2025-10-17
