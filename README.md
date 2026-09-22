# SEO Toolkit

**by the Rhapsody Core Team**

Generates `sitemap.xml` and `robots.txt` directly from your site's own route table — no hand-maintained URL lists, no separate config file to keep in sync as you add routes. Also ships a small `SchemaBuilder` helper class for building correctly-shaped [schema.org](https://schema.org) structured data.

- **Category:** SEO
- **Requires:** PHP ^8.4.1, `arout/rhapsody-core` ^2.0

---

## What this module does

Once installed, your site gets two new routes:

- **`/sitemap.xml`** — a standard XML sitemap listing every public `GET` route on your site, built fresh on each request by reading the live route table. Routes with parameters (like `/blog/{slug}`), routes behind excluded middleware, and routes served by excluded controllers are automatically left out — see [Configuration](#configuration) below for how to control that.
- **`/robots.txt`** — a `robots.txt` file with a configurable `Disallow` list and a `Sitemap:` line pointing back at the sitemap above, so crawlers can discover both from one place.

It also ships a `SchemaBuilder` support class your own app controllers can use directly — see [Using SchemaBuilder](#using-schemabuilder) below.

---

## Installation

### 1. Require the package

```bash
composer require arout/seo-toolkit
```

This puts the code on disk and makes it *discoverable*, but it does **not** activate it yet — that's a deliberate, separate step in Rhapsody's module system.

### 2. Confirm it's discovered

```bash
php rhapsody module:list
```

You should see:

```
arout/seo-toolkit     v1.0.0     not installed
```

### 3. Activate it

```bash
php rhapsody module:install arout/seo-toolkit
```

This runs the module's one-time setup (seeding default settings) and marks it active. From your very next request onward, `/sitemap.xml` and `/robots.txt` will be live.

### 4. Verify

```bash
curl http://your-site.test/sitemap.xml
curl http://your-site.test/robots.txt
```

---

## Configuration

All settings live in one file, created automatically on install:

```
storage/modules/arout-seo-toolkit/settings.json
```

You can hand-edit this file directly at any time — changes take effect on the next request, nothing needs restarting or recompiling. (A point-and-click settings screen is planned once Rhapsody's admin UI renderer ships; until then, editing the JSON file is the supported way to change these.)

| Key | Type | Default | What it does |
|-----|------| --------| -------------|
|
| `excluded_prefixes` | comma-separated string | `/auth,/login,/forgot-password,/reset-password,/payment` | Any route whose path starts with one of these prefixes is left out of the sitemap. |
| `excluded_controllers` | comma-separated FQCNs | `Rhapsody\Core\Controllers\DocsController` | Any route handled by one of these controller classes is left out — useful for excluding core's own internal pages even if they happen to share a URL prefix with your own routes. |
| `excluded_middleware` | comma-separated string | `auth` | Any route behind one of these middleware keys is left out (e.g. logged-in-only pages have no business in a public sitemap). |
| `change_frequency` | one of: `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never` | `weekly` | The `<changefreq>` value written for every URL in the sitemap. |
| `robots_disallow` | comma-separated string | `/auth,/login` | Paths written as `Disallow:` lines in `robots.txt`. |

**Example — excluding an additional admin section:**

```json
{
    "excluded_prefixes": "/auth,/login,/forgot-password,/reset-password,/payment,/admin",
    "excluded_controllers": "Rhapsody\\Core\\Controllers\\DocsController",
    "excluded_middleware": "auth",
    "change_frequency": "weekly",
    "robots_disallow": "/auth,/login,/admin"
}
```

---

## Using `SchemaBuilder`

`SchemaBuilder` is a plain helper class — it isn't gated by any permission and doesn't require the module to be installed to use; it's just a normal class the module's Composer package makes available. Its whole job is returning a correctly-shaped array for `$this->schema->add()`, so you don't have to look up or hand-build schema.org's nesting rules yourself.

```php
use Arout\SeoToolkit\Support\SchemaBuilder;

// Inside a controller action, after you already have $item loaded:
$this->schema->add('Product', SchemaBuilder::product(
    name: $item->getTitle(),
    description: $item->getDescription(),
    image: $item->getImageUrl(),
    price: $item->getPrice(),
    currency: 'USD',
    ratingValue: $item->getAverageRating(),   // omit if you have no ratings yet
    ratingCount: $item->getReviewCount(),
));
```

Available builders: `product()`, `article()`, `breadcrumbs()`, `faqPage()`. Each just returns an array — you still call `$this->schema->add($type, $data)` yourself, exactly as you would without this module.

---

## Uninstalling

```bash
php rhapsody module:uninstall arout/seo-toolkit --force
composer remove arout/seo-toolkit
```

The first command deactivates the module (your routes stop responding); the second removes the code from `vendor/` entirely. `settings.json` is left in place harmlessly if you skip the second step and reinstall later.

---

## Notes & known limitations

- The sitemap is generated fresh on every request rather than cached — this keeps it always accurate as routes change, at the cost of a small amount of work per sitemap request (which crawlers, not visitors, are the ones triggering).
- `sitemap.xml`/`robots.txt` require `APP_URL` to be set in your environment, since sitemap entries need absolute URLs.
- A redirect manager was considered for this release but isn't included — Rhapsody's module system doesn't yet expose a way for a module to intercept arbitrary incoming URLs (that capability is on the core framework's own roadmap). This may ship in a future release once that's available.

---

## License

Proprietary — part of the Rhapsody Marketplace's official "Rhapsody Core Team" module line.
