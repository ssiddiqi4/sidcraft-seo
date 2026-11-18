=== Sidcraft AutoSEO ===
Contributors: ssiddiqi4
Donate link: https://paypal.me/SSiddiqi275
Tags: seo, schema, sitemap, redirects, alt text
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hands-off SEO for pages and posts: automatic titles, descriptions, social cards, structured data, redirects and image alt text. No setup needed.

== Description ==

**Sidcraft AutoSEO optimises every page and post on its own.** Install it, activate it, and it keeps working as you publish and edit. There is nothing to configure and no keywords to enter.

= Automatic on every page =

* **Titles and descriptions.** A page title kept under 60 characters (the site name is added when it fits) and a meta description written from the first real paragraph of the page. Headings and button labels are skipped, so descriptions read like sentences. A hand-written excerpt is used when you have one.
* **Canonical address and robots rules.** Search results, 404 pages, date archives, attachment pages and thin tag pages are kept out of search results.
* **Social sharing cards** for Facebook, LinkedIn and X, using the featured image, the first image in the page, or a default image.
* **Structured data (JSON-LD).** Organization, WebSite with search box, WebPage, BlogPosting, BreadcrumbList, and FAQPage whenever a page has question-style headings followed by answers.
* **Sitemap improvements.** Builds on the sitemap WordPress already provides (`/wp-sitemap.xml`): last-modified dates, noindex pages removed, `/sitemap.xml` redirected to it, and the sitemap address added to robots.txt.
* **Image alt text.** Written for every image that has none: new uploads, the existing media library and images inside page content. Camera-roll names such as "IMG_1234" fall back to the page title. Images you marked as decorative are left alone.
* **Excerpts.** A short excerpt for posts that have none. Excerpts you wrote are never changed.
* **Internal links.** Posts that link to no other page get a short "You may also like" list of related posts.

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

If Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework is active, Sidcraft AutoSEO stops printing titles, descriptions, social cards, structured data and sitemap changes so nothing is duplicated. Auditing, redirects, alt text and IndexNow keep running.

= You stay in control =

Every automatic action has an on/off switch under **Sidcraft AutoSEO > Settings**. On any single page, the **Sidcraft AutoSEO** box on the edit screen lets you override the title, description or main phrase, or hide the page from search engines. You never have to.

= What it cannot do =

It does not write or improve your content. Short pages are flagged, not lengthened. The score and the main phrase are guidance, not a ranking guarantee. IndexNow helps Bing and similar engines; Google finds pages through the sitemap.

== External services ==

This plugin can send page addresses to the **IndexNow** protocol endpoint so that participating search engines (Microsoft Bing, Yandex, Seznam, Naver and others) learn about new, changed or removed pages quickly.

* **What is sent:** the public address (URL) of a page, the site's host name, and a random key that identifies the site. Nothing else: no page content, no visitor data, no personal data.
* **When:** only when a public page or post is published, updated or removed, and only on a live public site. It is never sent from a local or private site (localhost, IP address, `.local` or `.test` domains), from a site set to "discourage search engines", or from a non-production environment.
* **Where:** `https://api.indexnow.org/indexnow`
* **Service:** IndexNow, operated with participating search engines. Documentation: https://www.indexnow.org/documentation
* **Terms and privacy:** https://www.indexnow.org/terms and the Microsoft Privacy Statement at https://privacy.microsoft.com/privacystatement

You can turn this off at any time under **Sidcraft AutoSEO > Settings** ("Tell Bing & other IndexNow search engines..."). The plugin also serves a plain-text key file at `/{key}.txt` on your site, which IndexNow uses to confirm that you own the site.

**Optional broken-link check of other websites.** If you tick "Broken-link scan also checks links to other websites" (off by default), the plugin sends a lightweight HEAD or GET request to each external address that appears in your content, to see whether it still exists. Only the linked address is requested, nothing about your site or visitors is sent, and only the HTTP status is read. With the option off, only links to your own site are checked, and those requests never leave your server.

No other external services are used. No data is collected by the plugin author.

== Installation ==

1. Upload the `sidcraft-autoseo` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate **Sidcraft AutoSEO**.
3. That is all. Open **Sidcraft AutoSEO** in the admin menu to see how your pages score, and press **Run audit now** to check everything straight away.

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

Sidcraft AutoSEO detects it and stops printing titles, descriptions, social cards, structured data and sitemap changes so nothing is duplicated. Redirects, alt text, the audit and IndexNow still work.

= Does it add a sitemap? =

It improves the sitemap WordPress already creates at `/wp-sitemap.xml`, and makes `/sitemap.xml` redirect to it. If you use another plugin that provides a sitemap, turn the sitemap option off.

= Which structured data does it add? =

Organization, WebSite (with search box), WebPage, BlogPosting for posts, BreadcrumbList, and FAQPage when a page has two or more question headings with answers. It does not add product, event, recipe or review markup.

= Does it work for multilingual sites? =

Titles, descriptions and the audit work for any language. There is no translation-plugin integration (such as hreflang output) in this version.

= Will it slow my site down? =

Titles, descriptions and structured data are built per request from data WordPress has already loaded. The audit runs once a day in small batches. Redirect and 404 handling use one indexed database lookup.

= What happens if I uninstall it? =

The plugin stops. Its tables and settings are kept unless you ticked "Delete all Sidcraft AutoSEO data when the plugin is deleted" in Settings before deleting it.

= Is anything sent to the author? =

No. See "External services" for the one optional request the plugin makes (IndexNow).

== Screenshots ==

1. The Overview screen: average score, how many pages are good, redirects created automatically, and the issues most worth looking at.
2. Pages & Posts, with the lowest scores first and plain-language reasons.
3. Redirects & 404s: automatic and manual redirects, and broken addresses that visitors reached.
4. Settings: every automatic feature has an on/off switch.

== Changelog ==

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
