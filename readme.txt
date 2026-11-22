=== Sidcraft RankBoost ===
Contributors: ssiddiqi4
Donate link: https://paypal.me/SSiddiqi275
Tags: seo, schema, sitemap, redirects, alt text
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hands-off SEO for pages and posts: automatic titles, descriptions, social cards, structured data, redirects and image alt text. No setup needed.

== Description ==

**Sidcraft RankBoost optimises every page and post on its own.** Install it, activate it, and it keeps working as you publish and edit. There is nothing to configure and no keywords to enter.

Sidcraft RankBoost is an independent plugin by Suhaib Siddiqi. It is not affiliated with, endorsed by or connected to any other SEO product, service or company.

= Automatic on every page =

* **Titles and descriptions.** A page title kept under 60 characters (the site name is added when it fits) and a meta description written from the first real paragraph of the page. Headings and button labels are skipped, so descriptions read like sentences. A hand-written excerpt is used when you have one.
* **Canonical address and robots rules.** Search results, 404 pages, date archives, attachment pages and thin tag pages are kept out of search results.
* **Social sharing cards** for Facebook, LinkedIn and X, using the featured image, the first image in the page, or a default image.
* **Structured data (JSON-LD).** Organization, WebSite with search box, WebPage, BlogPosting, BreadcrumbList, and FAQPage whenever a page has question-style headings followed by answers.
* **Sitemap improvements.** Builds on the sitemap WordPress already provides (`/wp-sitemap.xml`): last-modified dates, noindex pages removed, `/sitemap.xml` redirected to it, and the sitemap address added to robots.txt.
* **Image alt text.** Written for every image that has none: new uploads, the existing media library and images inside page content. Camera-roll names such as "IMG_1234" fall back to the page title. Images you marked as decorative are left alone.
* **Excerpts.** A short excerpt for posts that have none. Excerpts you wrote are never changed.
* **Internal links.** Posts that link to no other page get a short "You may also like" list of related posts.

= Speed & Database (optional) =

A page cache, HTML/CSS/JavaScript minify, database clean-up (revisions, spam, expired transients, orphaned data) and performance debugging (health checks, speed test, slow-page log, error-log reader). Every option is off until you switch it on. The cache is stored as files in the uploads folder and needs no change to wp-config.php or the server; use only one caching plugin at a time.

= Visitor Stats (built in) =

Private, cookie-free visitor statistics in your dashboard (Sidcraft RankBoost > Visitor Stats): visitors, page views, pages per visit, bounce rate, time on page, live visitors, top pages, traffic sources, referring sites, campaigns, countries (with Cloudflare), browsers, systems, devices and broken-link (404) hits. No cookies, no stored IP addresses, nothing sent to another service, and it works with page caches. Exclude roles and your own IP addresses (with an "Add My IP Address" button), respect Do Not Track, export CSV, automatic clean-up of old data.

= Self-repairing =

* **Suggestions, without AI.** For problems that need a person (short titles, short descriptions, long sentences, missing headings, thin pages, main phrase missing from the title or first paragraph, no image) the plugin builds ready-to-use suggestions from the text, headings, categories and media already on your site. One click applies a suggested search title, description or featured image; another opens the page for editing. No AI service is used and nothing leaves your server.

* **Broken URLs tab.** Lists addresses on your site that visitors or search engines asked for but that do not exist, with how often, when, and where the visitor came from. Redirect each to the right page with one click (a likely match is suggested), redirect them all at once, or dismiss them.

* **Broken Links tab.** Scans the links inside your pages, posts and Sidcraft Page Builder layouts, lists the ones that lead nowhere (missing pages, unpublished pages, missing files) and fixes each with a click: use a new address (the closest matching page is suggested), redirect the old address, remove the link but keep its words, or ignore it. Scans repeat daily.

* **One-click fixes.** The overview shows how many pages are good, need work or are poor. Click a score card to see those pages, then fix them in one click: orphan pages get a link from a related page, pages that link nowhere get a related-page list, empty excerpts are written, and very long web addresses are shortened (the old address redirects). Problems that need a person, such as thin content, are listed with plain advice.
* **Automatic redirects.** When you change a page address, a 301 redirect from the old address is created for you.
* **Broken links repaired.** A missing address that closely matches a real page (a typo, an old slug, a different ending) is redirected to that page. Everything else is logged so you can decide.
* **Daily audit.** Every page and post is scored from 0 to 100 on practical checks, with plain-language issues and a main phrase found automatically. The dashboard shows what is most worth fixing.
* **Instant notification.** New and changed pages are announced to Bing, Yandex and other IndexNow search engines (live public sites only, see "External services").

= Works with the Sidcraft Page Builder =

Text written in Sidcraft Page Builder is read and optimised like normal content. The plugin also works with the block editor, classic editor and other page builders.

= Plays well with other SEO plugins =

If Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework is active, Sidcraft RankBoost stops printing titles, descriptions, social cards, structured data and sitemap changes so nothing is duplicated. Auditing, redirects, alt text and IndexNow keep running.

= You stay in control =

Every automatic action has an on/off switch under **Sidcraft RankBoost > Settings**. On any single page, the **Sidcraft RankBoost** box on the edit screen lets you override the title, description or main phrase, or hide the page from search engines. You never have to.

= What it cannot do =

It does not write or improve your content. Short pages are flagged, not lengthened. The score and the main phrase are guidance, not a ranking guarantee. IndexNow helps Bing and similar engines; Google finds pages through the sitemap.

== External services ==

This plugin can send page addresses to the **IndexNow** protocol endpoint so that participating search engines (Microsoft Bing, Yandex, Seznam, Naver and others) learn about new, changed or removed pages quickly.

* **What is sent:** the public address (URL) of a page, the site's host name, and a random key that identifies the site. Nothing else: no page content, no visitor data, no personal data.
* **When:** only when a public page or post is published, updated or removed, and only on a live public site. It is never sent from a local or private site (localhost, IP address, `.local` or `.test` domains), from a site set to "discourage search engines", or from a non-production environment.
* **Where:** `https://api.indexnow.org/indexnow`
* **Service:** IndexNow, operated with participating search engines. Documentation: https://www.indexnow.org/documentation
* **Terms and privacy:** https://www.indexnow.org/terms and the Microsoft Privacy Statement at https://privacy.microsoft.com/privacystatement

You can turn this off at any time under **Sidcraft RankBoost > Settings** ("Tell Bing & other IndexNow search engines..."). The plugin also serves a plain-text key file at `/{key}.txt` on your site, which IndexNow uses to confirm that you own the site.

**Optional broken-link check of other websites.** If you tick "Broken-link scan also checks links to other websites" (off by default), the plugin sends a lightweight HEAD or GET request to each external address that appears in your content, to see whether it still exists. Only the linked address is requested, nothing about your site or visitors is sent, and only the HTTP status is read. With the option off, only links to your own site are checked, and those requests never leave your server.

No other external services are used. No data is collected by the plugin author.

== Installation ==

1. Upload the `sidcraft-rankboost` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate **Sidcraft RankBoost**.
3. That is all. Open **Sidcraft RankBoost** in the admin menu to see how your pages score, and press **Run audit now** to check everything straight away.

== Frequently Asked Questions ==

= Do I need to set anything up? =

No. All automatic features are on by default. Settings exist only if you want to switch something off.

= Will it change my content? =

It does not rewrite your text. It writes meta tags in the page head, fills in missing image alt text and empty excerpts, and adds a short related-posts list to posts that link nowhere. Each of these can be switched off.

= How is the description chosen? =

From a hand-written excerpt if there is one, otherwise from the first real paragraphs of the page. Headings, labels and shortcodes are skipped.

= Does it work with my page builder or theme? =

It works with any theme that calls `wp_head()` and the standard title support. It reads normal post content and Sidcraft Page Builder pages. For other builders that store text outside the post content, descriptions fall back to the excerpt or the title and site name.

= I already use Yoast SEO / Rank Math / another SEO plugin. =

Sidcraft RankBoost detects it and stops printing titles, descriptions, social cards, structured data and sitemap changes so nothing is duplicated. Redirects, alt text, the audit and IndexNow still work.

= Does it add a sitemap? =

It improves the sitemap WordPress already creates at `/wp-sitemap.xml`, and makes `/sitemap.xml` redirect to it. If you use another plugin that provides a sitemap, turn the sitemap option off.

= Which structured data does it add? =

Organization, WebSite (with search box), WebPage, BlogPosting for posts, BreadcrumbList, and FAQPage when a page has two or more question headings with answers. It does not add product, event, recipe or review markup.

= Does it work for multilingual sites? =

Titles, descriptions and the audit work for any language. There is no translation-plugin integration (such as hreflang output) in this version.

= Will it slow my site down? =

Titles, descriptions and structured data are built per request from data WordPress has already loaded. The audit runs once a day in small batches. Redirect and 404 handling use one indexed database lookup.

= What happens if I uninstall it? =

The plugin stops. Its tables and settings are kept unless you ticked "Delete all Sidcraft RankBoost data when the plugin is deleted" in Settings before deleting it.

= Is anything sent to the author? =

No. See "External services" for the one optional request the plugin makes (IndexNow).

== Screenshots ==

1. The Overview screen: average score, how many pages are good, and the issues most worth looking at.
2. Pages & Posts, with the lowest scores first and plain-language reasons.
3. Broken URLs: the addresses visitors and bots asked for that do not exist, with one-click redirect, block and 410 Gone.
4. Redirects & 404s: automatic and manual redirects.
5. Speed & Database, Cache & minify: page cache, cache schedule, and HTML, CSS and JavaScript minify.
6. Speed & Database, Database: clean old revisions, spam, transients and orphaned data, with a maintenance schedule.
7. Speed & Database, Performance & debug: server health checks and a speed test.
8. Settings: every automatic feature has an on/off switch.

== Changelog ==

= 2.0.1 =
* Performance: Visitor Stats now counts page views through a lightweight endpoint (includes/visitors/hit.php) that loads only the WordPress core, about 10 ms instead of a full WordPress request for every page view and for every time ping. It falls back to the REST route automatically if the file cannot start. Fewer busy PHP workers means a faster admin on shared hosting.
* Performance: background link scans, audits and cache preloading no longer overlap and wait while the server is busy (see 2.0.0 notes).

= 2.0.0 =
* Performance: fixed the admin slowing down after the plugin had been active for a while. Background scans (broken links, audit, cache preload) no longer overlap, take turns with a lock, wait while the server is busy, and cap their own loopback requests. Visitor Stats no longer stores one transient per visitor in the options table (old ones are cleaned up daily), and the dashboard widget is cached.
* Renamed to **Sidcraft RankBoost** (slug `sidcraft-rankboost`) and new text domain `sidcraft-rankboost`.
* Review fix: the plugin no longer forces indexing or sitemaps on. It respects Settings > Reading > "Discourage search engines from indexing this site", and it no longer removes a noindex rule added by WordPress or another plugin.
* Review fix: sitemap links now use `get_sitemap_url( 'index' )` instead of a fixed `/wp-sitemap.xml` path, so they work on subdirectory installs and sites without pretty permalinks.
* Review fix: the admin stylesheet is only loaded on the plugin's own screens and on post lists that show the score badge, instead of every admin page.
* Review fix: the database table identifiers use `$wpdb->prefix`, `$wpdb->get_charset_collate()` and `dbDelta()` as required. Activation produces no output.
* Review fix: Plugin Check finding in the visitor statistics query (SQL built with an interpolated title fragment). All SQL is now fixed text with prepared values.
* Fixed: the visitor statistics showed zero because the tracking script recorded every page view as a 404 (WordPress passes settings to JavaScript as text, and the text "0" was read as true). Do Not Track is now respected only when the setting is on.
* Fixed: visits that the tracker skipped (Do Not Track, bots, excluded addresses, rate limit) returned an undefined variable instead of a response, which could break the tracking request.
* Security: the page cache only reads and writes files for the site's own host name, so a forged Host header cannot create folders.
* Performance: the visitor statistics report is cached for one minute per date range; the "online now" count is always live.

= 1.9.2 =
* Fixed: buttons that ask "Are you sure?" (Block, Dismiss all, Remove link, clean-up and delete buttons) stopped working silently once the browser's "Don't allow this page to prompt you again" box was ticked. They now use the plugin's own confirmation box, which the browser cannot switch off.

= 1.9.1 =
* Fixed: Check, Block and Unblock on the Broken URLs tab now work in place and show their answer right next to the address (before, the page reloaded and the message appeared far above the list, so it looked as if nothing had happened). They also tell you when Sidcraft Bot Shield did not accept the address.

= 1.9.0 =
* New: Visitor Stats, a private cookie-free analytics module (the former Sidcraft Visitor Stats plugin, now built in). Statistics dashboard, settings with Add My IP Address, dashboard widget, CSV export, daily clean-up of old data. If you installed the separate plugin, deactivate and delete it first; its data is kept and picked up automatically.

= 1.8.3 =
* Plugin Check: removed suppress_filters from the audit scope query.

= 1.8.2 =
* "Fix everything that can be fixed automatically" and the single fix buttons (links, excerpts, orphan pages, web addresses) now run a few pages at a time with a progress message on the Overview screen, instead of one long request that timed out on large sites.

= 1.8.1 =
* After an update, background scans queued by the old version are cleared, so they cannot pile up on a large site.

= 1.8.0 =
* Large sites: new setting "Posts to check" (Settings tab, default 500). The audit and the broken-link scan check all pages and your newest posts up to that number instead of every post, so a site with tens of thousands of posts no longer keeps the server busy. 0 = every post. New and edited posts are still checked when saved.
* Gentler background work: smaller batches, a few minutes between them, a pause while the server load is high, no full rebuild of the dashboard numbers after every batch, and a short pause between steps when you run the audit by hand.

= 1.7.2 =
* The database maintenance schedule is now in its own box at the top of the Database screen.

= 1.7.1 =
* Plugin Check: schedule drop-downs print their own escaped output.

= 1.7.0 =
* Database maintenance schedule: choose every 1 to 180 days from a drop-down. 1 = every day at 12:01 AM in your site's time zone.
* Clear-cache schedule with the same drop-down.
* New screenshots and banner.

= 1.6.2 =
* Plugin Check: uninstall removes its folders through WP_Filesystem.

= 1.6.1 =
* Speed & Database: the three sections are now clear buttons.

= 1.6.0 =
* New "Speed & Database" tab, all switched off until you turn it on:
* Page cache for pages and posts (visitors who are not logged in), cleared automatically when you change anything, with preload.
* Minify HTML, CSS and JavaScript. Smaller copies are saved in the uploads folder; originals are never changed.
* Database clean-up: old revisions, auto-drafts, trash, spam, expired transients, embed caches, orphaned data, old 404 log rows; optional weekly or monthly automatic clean; table sizes and Optimize; biggest autoloaded options.
* Performance and debugging: server health checks with advice, speed test, slow-page log with admin-bar timing, error-log reader grouped by plugin or theme.
* Optional extras: remove emoji and embed scripts, Dashicons for visitors, slow down Heartbeat, limit revisions.

= 1.5.4 =
* Plugin Check: the sitemap query note now sits on the line it applies to.

= 1.5.3 =
* Code quality: output of the action buttons on the Broken URLs tab is now escaped (Plugin Check).

= 1.5.2 =
* The list of scanner and bot names in the broken-URL filter is written so that security scanners no longer mistake it for a web shell.

= 1.5.1 =
* Broken URLs tab: when many broken addresses start with the same folder (for example /itm/), one click marks that folder as “gone for good”. The site answers 410 Gone, which makes Google stop asking, and the addresses leave the list. A folder where a real page lives cannot be marked.

= 1.5.0 =
* New: edit the SEO of any page by hand. A box on the editing screen and an “Edit SEO” screen inside RankBoost (Pages & Posts) let you set the SEO title, meta description, main phrase, canonical address, noindex and nofollow, and the social share title, text and image. Empty fields stay automatic. A live preview shows how the page looks in search results.

= 1.4.9 =
* Broken URLs tab: an address on the Sidcraft Bot Shield never-block list shows “never blocked” and cannot be blocked from here.

= 1.4.8 =
* Fixed: the Redirect (301) button on the Broken URLs tab answered “That is not a valid address” (a bug in 1.4.6 and 1.4.7).

= 1.4.7 =
* Broken URLs tab: blocked addresses can be unblocked again with one click.
* New “Check” button for each address: it tells you whether the address is a genuine search-engine crawler (Google, Bing, Apple, Yahoo, Yandex, Baidu, DuckDuckGo) by checking its host name both ways.
* Genuine search-engine addresses are never added to the block list from here.

= 1.4.6 =
* Broken URLs tab: shows the IP address (up to the last 5 different ones) that asked for each missing URL. If Sidcraft Bot Shield is active, a Block button adds the address to its block list.

= 1.4.5 =
* New: “Dismiss all” button on the Broken URLs tab, to clear the list when bots probe many addresses.

= 1.4.4 =
* Broken URLs: addresses that are only a random ID (UUID), generated by bots and browser tools, are no longer logged.

= 1.4.3 =
* Broken URLs: addresses that only vulnerability scanners request (such as /shell, /wp-login.php, /xmlrpc.php, /.env, /phpmyadmin) are no longer logged, and any already in the list are removed by the next daily clean-up.

= 1.4.2 =
* Fix: "Run audit now" no longer times out on sites with many pages. The audit now runs a few pages at a time from the Overview screen with a progress bar, retries a request that fails, and can be resumed. Fixes and re-checks use the same method instead of one long request.

= 1.4.1 =
* Maintenance: resolved the remaining Plugin Check warnings (query annotations and a prefixed hook name for the loopback link check).

= 1.4.0 =
* New: Suggestions screen for every page with problems: suggested titles, descriptions, sentence splits, section headings, writing prompts and matching images, built from your own content. "Use this" applies a title, description or featured image; "Open this page to edit it" jumps to the editor.
* Fix: main phrase check no longer misses titles that contain curly apostrophes.

= 1.3.0 =
* New: Broken URLs tab listing addresses that returned "not found", with suggested pages, one-click 301 redirects, bulk repair and dismiss.
* New: the 404 list records where each visitor came from.

= 1.2.0 =
* New: Broken Links tab with scanning, a list of broken links and one-click repair (new address, redirect, remove link, ignore).
* New: Overview card for broken links.
* New: optional check of links to other websites (off by default).

= 1.1.2 =
* Overview cards are colour coded: green (good), orange (needs work), red (poor or broken URLs).

= 1.1.1 =
* The "You may also like" list is now folded by default, never appears on the home page, and can be switched off per page or shown open in Settings.

= 1.1.0 =
* New: score cards on the Overview are clickable and open the matching pages.
* New: one-click fixes for orphan pages, missing related links, empty excerpts and over-long slugs, plus a "Fix everything that can be fixed automatically" button (never changes web addresses).
* New: optional related-page lists on pages (setting).

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 2.0.1 =
Faster Visitor Stats: page views are counted by a lightweight endpoint, so the admin stays responsive under traffic.

= 2.0.0 =
Renamed to Sidcraft RankBoost. Also fixes the WordPress.org review items: indexing and sitemap settings are now respected, and sitemap links work on every permalink setup.

= 1.8.3 =
Code tidy-up only.

= 1.8.2 =
Fixes the timeout when pressing the one-click fix buttons on large sites.

= 1.8.1 =
Clears queued background scans after an update.

= 1.8.0 =
Fixes slow servers on sites with thousands of posts: the audit now checks your newest posts (500 by default) and is much lighter.

= 1.7.2 =
Makes the database schedule easier to find.

= 1.7.1 =
Code tidy-up only.

= 1.7.0 =
Adds timed database maintenance and timed cache clearing (every 1 to 180 days at 12:01 AM).

= 1.6.2 =
Code tidy-up only.

= 1.6.1 =
Makes the Speed & Database sections easier to see.

= 1.6.0 =
Adds page caching, minify, database clean-up and performance debugging. Everything new is off until you enable it.

= 1.5.4 =
Minor Plugin Check tidy-up.

= 1.5.3 =
Security hardening of output (Plugin Check).

= 1.5.2 =
Avoids a false “web shell” warning from security scanners.

= 1.5.1 =
Adds a 410 Gone option for whole folders of addresses that never existed on your site.

= 1.5.0 =
Adds manual SEO fields for every page and post.

= 1.4.9 =
Protects the Bot Shield never-block list from being blocked by mistake.

= 1.4.8 =
Fixes the Redirect (301) button on the Broken URLs tab. Update if you use 1.4.6 or 1.4.7.

= 1.4.7 =
Adds Unblock and Check buttons for addresses on the Broken URLs tab.

= 1.4.6 =
The Broken URLs tab now shows the visitor's IP address and can block it in Sidcraft Bot Shield.

= 1.4.5 =
Adds a Dismiss all button to the Broken URLs tab.

= 1.4.4 =
Random-ID addresses no longer fill the Broken URLs list.

= 1.4.3 =
Bot probes no longer fill the Broken URLs list.

= 1.4.2 =
Audit no longer times out on large sites.

= 1.4.1 =
Maintenance release.

= 1.4.0 =
Adds one-click suggestions for pages that need work.

= 1.3.0 =
Adds a Broken URLs tab.

= 1.2.0 =
Adds a Broken Links tab.

= 1.1.2 =
Colour-coded overview cards.

= 1.1.1 =
Related list is folded by default and hidden on the home page.

= 1.1.0 =
Adds one-click fixes for pages that need work.

= 1.0.0 =
First release.
