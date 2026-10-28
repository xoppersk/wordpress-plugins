# WordPress Plugins — Sheku Koroma / Sevyn Labs

Custom WordPress plugins built for production sites on the Sevyn Labs
estate. Every plugin here runs live on a real site; nothing here is a
demo or a tutorial snippet.

## Plugins

| Plugin | What it does |
| --- | --- |
| [global-styles-inline](global-styles-inline/) | Prints a global stylesheet inline at `wp_head` priority 1000 — the fix for builders whose custom CSS is never emitted and hosts that refuse direct `.css` reads from custom uploads subfolders. Drop it in `mu-plugins` or activate it as a regular plugin. |
| [liberty-core](liberty-core/) | Newsroom features for Liberty Online TV: live streaming from YouTube and Facebook, team and broadcast content types, breaking news, the daily briefing, video feeds, ads, forms with spam protection, the data saver and the app API. |
| [poda-core](poda-core/) | Content model, enquiry handling and one-click setup for the Studio Poda theme: projects, crew, services, enquiries and studio settings. |
| [studio-poda-widgets](studio-poda-widgets/) | Bespoke Elementor widgets, content types and shortcodes for Studio Poda — the route-board Ticker Band, the featured-work Cover Rail, the filterable Work Grid, and the `poda_project` / `poda_story` content types. |
| [scroll-chapters](scroll-chapters/) | Elementor addon: Chapter Rail, Typewriter, Marquee Band and Layered Image Stack widgets, plus seven small site helpers (filter motion, form error summary, grouped search, bend order, header hide, menu motion, service tag boxes). |
| [sk-portfolio](sk-portfolio/) | Styles and interactions for the Sheku Koroma portfolio: scrolling name marquee, spinning globe, magnetic buttons, cursor-follow project previews, page-transition curtain and live local time. |
| [mu-plugins/kowloon-light-office](mu-plugins/kowloon-light-office/) | Must-use plugins for Kowloon Light Office: content model (CPTs, ACF fields), render shortcodes, self-hosted fonts with Google Fonts stripped, Open Graph tags, page-level loop ordering, and archive query fixes. |
| [mu-plugins/studio-poda](mu-plugins/studio-poda/) | Must-use plugins for Studio Poda: the site foundation (typefaces + the global stylesheet printed inline at `wp_head` priority 1000) and the project-single template pieces. |

## Notes

- `liberty-core`, `poda-core` and `studio-poda-widgets` are GPL-2.0-or-later.
- The mu-plugin folders are production code for their respective sites; they
  assume Elementor / ACF where noted in their headers.
- No credentials, keys or client data live in this repository. SMTP and
  other secrets are read from `wp-config.php` constants or saved settings
  at runtime, never committed.

## Author

Sheku Koroma — [shekukoroma.com](https://shekukoroma.com) ·
Sevyn Labs — [sevynlabs.io](https://sevynlabs.io)
