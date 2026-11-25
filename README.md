# Hesk

A modern black-and-white UI revamp of [HESK](https://www.hesk.com) help desk software, based on **HESK 3.8.0**.
Only the theme and templates are changed; HESK's logic is untouched.

## What changed
- New `black_white` theme for both the customer pages and the staff admin (`css/themes/`, `theme/hesk3/customer/css/themes/`)
- Redesigned customer pages: home, submit a ticket, view ticket
- Admin: split-screen login, page titles in the top bar, ticket overview panel, cleaner tables, cards and forms
- Most styling lives in `css/core_overrides.css` (admin) and `theme/hesk3/customer/css/core_overrides.css` (customer)

## Setup
1. Install HESK 3.8.0 as usual (database + `hesk_settings.inc.php`), or copy `hesk_settings.sample.inc.php` to `hesk_settings.inc.php` and fill in your database details and a random `url_key`.
2. In Admin → Settings → Look & Feel, select the **black_white** theme (or set `customer_theme` / `admin_theme` to `black_white` in the settings file).

## Licence
HESK is third-party software with its own licence (EULA): https://www.hesk.com/eula.php
The "Powered by" credit and licence check are left intact. Buy a HESK licence from hesk.com to remove them.
