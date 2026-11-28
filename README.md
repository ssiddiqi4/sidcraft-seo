# Sidcraft Search Boost Suite

Hands-off SEO for WordPress pages and posts: automatic titles, descriptions, social cards, structured data, sitemap tweaks, redirects, image alt text, a daily audit with one-click fixes, a Broken Links tab (with a folder fix), a Broken URLs tab, an Unlinked & Archive tab and rule-based suggestions. No AI service is used.

- WordPress.org readme: [`readme.txt`](readme.txt)
- Requires WordPress 6.2+ and PHP 7.4+
- License: GPL-2.0-or-later

## Install from source

Copy this folder into `wp-content/plugins/sidcraft-search-boost-suite/` (the folder name must be `sidcraft-search-boost-suite`) and activate it.

## Release ZIP

```bash
cd ..
mkdir -p build && cp -r sidcraft-search-boost-suite build/sidcraft-search-boost-suite
rm -rf build/sidcraft-search-boost-suite/.git build/sidcraft-search-boost-suite/README.md build/sidcraft-search-boost-suite/.distignore
(cd build && zip -r sidcraft-search-boost-suite.zip sidcraft-search-boost-suite)
```

Support the project: https://paypal.me/SSiddiqi275
