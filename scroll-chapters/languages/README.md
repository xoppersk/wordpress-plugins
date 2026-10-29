# Scroll Chapters — translations

Text domain: `scroll-chapters`. This folder holds the plugin's
translation files.

A `scroll-chapters.pot` catalogue belongs here. It has not been
generated yet for v1.0.0 — the plugin ships with English source strings
only. To generate the catalogue from the source once WP-CLI is
available on the build machine:

```sh
wp i18n make-pot wp-content/plugins/scroll-chapters \
  wp-content/plugins/scroll-chapters/languages/scroll-chapters.pot \
  --domain=scroll-chapters
```

Place per-locale files beside it as `scroll-chapters-{locale}.po` /
`.mo` (or JSON via `wp i18n make-json`); WordPress loads them through
the plugin's Domain Path (`/languages`).
