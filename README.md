# Sidcraft RankBoost

Hands-off SEO for WordPress pages and posts: automatic titles, descriptions, social cards, structured data, sitemap tweaks, redirects, image alt text, a daily audit with one-click fixes, a Broken Links tab, a Broken URLs tab and rule-based suggestions. No AI service is used.

- WordPress.org readme: [`readme.txt`](readme.txt)
- Requires WordPress 6.2+ and PHP 7.4+
- License: GPL-2.0-or-later

## Install from source

Copy this folder into `wp-content/plugins/sidcraft-rankboost/` (the folder name must be `sidcraft-rankboost`) and activate it.

## Release ZIP

```bash
cd ..
mkdir -p build && cp -r sidcraft-rankboost build/sidcraft-rankboost
rm -rf build/sidcraft-rankboost/.git build/sidcraft-rankboost/README.md build/sidcraft-rankboost/.distignore
(cd build && zip -r sidcraft-rankboost.zip sidcraft-rankboost)
```

Support the project: https://paypal.me/SSiddiqi275
