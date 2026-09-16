# GG Garage — PHP-only landing page build

Production package for **https://gggarage.lv/**.

## Important change

This version uses **only `index.php` page files**. There is no `index.html` dependency and no PHP wrapper that tries to load another page file.

- `/index.php` — complete Latvian homepage
- `/en/index.php` — complete English homepage
- `/lv/index.php` — complete Latvian alternate homepage
- `/api/contact.php` — repair request form endpoint

The full GG Garage page markup is directly inside each `index.php`; there is no secondary page file for the homepage to load.

The page files contain normal HTML and use a `.php` extension, which is compatible with standard PHP hosting. The contact endpoint remains compatible with PHP 5.6+.

## Deploy

1. Back up the existing site.
2. Delete the old GG Garage files in the domain document root, especially old `index.html`, old `index.php`, and any obsolete root `.htaccess` from previous versions.
3. Upload the **contents of this folder** directly into the document root (`public_html`, `htdocs`, or the folder assigned to `gggarage.lv`).
4. Confirm that the root contains:
   - `index.php`
   - `config.php`
   - `assets/`
   - `api/`
   - `en/`
   - `lv/`
   - `storage/`
5. There should be **no `index.html`** in the root, `/en/`, or `/lv/`.
6. Test:
   - `https://gggarage.lv/`
   - `https://gggarage.lv/en/`
   - `https://gggarage.lv/lv/`
7. Submit one test repair request.

## Contact form

Endpoint: `/api/contact.php`

Recipient: `gggarage@gmail.com`

Backup submissions can be written to `/storage/requests.php` when enabled and writable.

## Server note

A normal PHP host includes `index.php` in `DirectoryIndex`. If the domain still does not open `/index.php` automatically, check hosting settings or an old root `.htaccess`. The package itself does not require a root `.htaccess`.


## Final visual update

This refreshed build includes:

- lighter overall dark theme for improved readability
- smaller footer logo with correct proportions
- new hero background image to give the top section more visual depth
- cache-busted CSS version (`main.css?v=1.1.0`)


## Final release checks

- complete Latvian homepage in `/index.php`
- complete English homepage in `/en/index.php`
- Latvian alternate page in `/lv/index.php`
- no `index.html` files
- lighter premium dark theme
- hero workshop background image included locally
- smaller proportional footer logo
- repair form endpoint included
- form backup file protected from direct browser access
- no Composer or npm dependencies


## Logo sizing fix

This release uses the clean PNG logo instead of the previous WebP asset and sizes every logo by fixed height with automatic width to preserve the original proportions. Header, hero, and footer sizes are independently tuned, with a smaller footer mark. CSS cache version is 1.2.0.
