# Sidcraft AutoSEO

Hands-off SEO for WordPress pages and posts: automatic titles, descriptions, social cards, structured data, sitemap tweaks, redirects, image alt text, a daily audit with one-click fixes, a Broken Links tab, a Broken URLs tab and rule-based suggestions. No AI service is used.

- WordPress.org readme: [`readme.txt`](readme.txt)
- Requires WordPress 6.2+ and PHP 7.4+
- License: GPL-2.0-or-later

## Install from source

Copy this folder into `wp-content/plugins/sidcraft-autoseo/` (the folder name must be `sidcraft-autoseo`) and activate it.

## Release ZIP

```bash
cd ..
mkdir -p build && cp -r sidcraft-seo build/sidcraft-autoseo
rm -rf build/sidcraft-autoseo/.git build/sidcraft-autoseo/README.md build/sidcraft-autoseo/.distignore
(cd build && zip -r sidcraft-autoseo.zip sidcraft-autoseo)
```

Support the project: https://paypal.me/SSiddiqi275
