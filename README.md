<p align="center">
  <img src=".github/cover.jpg" alt="JUTAR BAZAR BD online shoe shop on desktop and phone" width="100%">
</p>

<h1 align="center">জুতার বাজার বিডি · JUTAR BAZAR BD</h1>

<p align="center">
  <b>Bangla-first online shoe shop for Bangladesh, with a Bangla admin panel and AI auto captions.</b><br>
  PHP 8 + SQLite: upload, extract, done. No database setup.
</p>

<p align="center">
  <a href="https://abid2929.github.io/JUTAR-BAZAR/"><b>👀 Live demo</b></a> &nbsp;·&nbsp;
  <a href="https://abid2929.github.io/JUTAR-BAZAR/admin/"><b>🔐 Admin demo</b></a>
</p>

<p align="center">
  <img alt="version" src="https://img.shields.io/badge/version-2.6.2-e5131d?style=flat-square"> <img alt="php" src="https://img.shields.io/badge/PHP-8%2B-777bb4?style=flat-square"> <img alt="db" src="https://img.shields.io/badge/SQLite-no%20setup-0f80cc?style=flat-square"> <img alt="lang" src="https://img.shields.io/badge/Bangla%20%2B%20English-✓-1f8a4c?style=flat-square">
</p>

<p align="center">
  <kbd>PHP 8</kbd> <kbd>SQLite</kbd> <kbd>JavaScript</kbd> <kbd>CSS3</kbd> <kbd>Google Gemini</kbd> <kbd>Facebook Pixel</kbd> <kbd>GA4</kbd> <kbd>Bangla + English</kbd>
</p>

> The demo is a static preview, so nothing is saved. The admin demo opens **without a password** and shows **sample data only**.

---


## 📸 Screenshots

<table>
  <tr>
    <td width="68%"><img src=".github/shots/home-pc2.jpg" alt="Best sellers on the home page"><br><sub>Product cards: price, discount, colours and stock at a glance</sub></td>
    <td width="32%" rowspan="2"><img src=".github/shots/home-ph.jpg" alt="Home page on a phone"><br><sub>Phone: offer slider, colourful category tiles, app-style tab bar</sub></td>
  </tr>
  <tr>
    <td><img src=".github/shots/prod-pc.jpg" alt="Product page"><br><sub>Product page: pick a size, buy now, add to cart or order on WhatsApp</sub></td>
  </tr>
  <tr>
    <td><img src=".github/shots/admin-pc.jpg" alt="Admin dashboard"><br><sub>Admin dashboard (Bangla): today's orders, sales, visitors, low stock</sub></td>
    <td rowspan="2"><img src=".github/shots/prod-ph.jpg" alt="Product page on a phone"><br><sub>Phone product page</sub></td>
  </tr>
  <tr>
    <td><img src=".github/shots/admin-prod.jpg" alt="Admin product list"><br><sub>Products: photos, prices, sizes, colours and stock in one place</sub></td>
  </tr>
</table>

---

## ✨ What's inside

**Storefront (opens in বাংলা, one tap to English)**
- Real shop info: JUTAR BAZAR BD, call 01624-244920, WhatsApp 01608-565035, Facebook page, Dhaka address (opposite Jamuna Future Park). All editable in Settings.
- Offer banner slider at the top. Every collection page opens on its own banner.
- **Men / Women / Kids** sections, plus 3 categories: Running, Sneakers, Fashion.
- Product pages with a size guide, an "only X left" badge, related products and recently viewed.
- Cart, checkout with cash on delivery (plus bKash / Nagad with TrxID when turned on), order tracking, WhatsApp ordering and a WhatsApp popup.
- A calm, premium first screen: soft entrance animation, no top bar, the WhatsApp popup waits until the visitor scrolls.
- Phone layout that feels like an app: one-line header and a bottom tab bar (Home, Shop, Cart, WhatsApp).
- Fast: phone-sized photo copies, lazy loading and clean HTML. SEO-ready: meta tags, canonical, JSON-LD and sitemap.xml.

**Admin panel (the same admin as the owner's other shops, Bangla with an English button)**
- Dashboard: today's numbers, quick buttons, visitors today and browsing now, 7-day sales chart, order status donut. App-style bottom bar on phone.
- Products with sizes (tap 36-46 or kids sizes), colours, a photo gallery with a colour tag per photo, stock, flash sale / new / best seller badges.
- **AI copywriter:** English + Bangla description, Facebook post and tags in 5 tones. Free with a Google Gemini key, or built-in templates.
- Categories, catalog collections (own row on the homepage), inventory, customer reviews, hero banner slides, homepage CMS.
- Orders with status history, invoice print, manual orders, CSV export, Steadfast courier hub, customers, reports.
- Admins & roles (super admin, manager, staff), 8 settings tabs (store, delivery, payment, courier, SMS, Meta Pixel + Conversions API, Google SEO, password & backup).

## 🚀 Install on cPanel (about 5 minutes)

1. Download this repo as a ZIP (**Code → Download ZIP**).
2. Upload everything **except the `docs/` folder** to `public_html`, then extract it.
3. Open `yourdomain.com/admin` and set your admin password on the first visit. After that you sign in with **just the password**. Staff tap "Staff? Sign in with username".
   Forgot it? Create an empty file `reset-admin.txt` in `public_html`, open `/admin` and set a new one. Your products and orders stay.
4. Change the shop name, phone, WhatsApp number and delivery charges in **Settings**.

Needs PHP 8.0 or newer with SQLite (standard on cPanel hosting).

## 📁 Folder map

| Folder | What it is |
|---|---|
| `index.php`, `inc/` | Storefront and core code |
| `admin/` | Admin panel |
| `assets/` | CSS, JS, images (`demo/*/s/` = phone-size copies) |
| `data/`, `uploads/` | Created on the server: database and uploaded photos (protected, not in this repo) |
| `docs/` | Static demo for GitHub Pages only. Do not upload it to the server. |

## 🕒 Latest updates

| Version | What changed |
|---|---|
| 2.6.2 | Top bar removed, smooth first-screen entrance, WhatsApp popup waits for scroll |
| 2.6.1 | Admin sign-in with the password only, eye button on every password field |
| 2.6.0 | Premium store look: studio photo tiles, rounded cards, trust cards, payment chips |
| 2.5.0 | New shared admin panel, reviews, collections, bKash/Nagad with TrxID, visitor counter |

## 📝 Notes

- Demo product photos are free-licence photos (Unsplash) with brand logos removed. Replace them with your own shop photos from the admin panel.
- No passwords, API keys or customer data are stored in this repo.

---

<p align="center">
  Designed &amp; developed by <a href="https://github.com/abid2929"><b>Abid Islam</b></a> &amp; <a href="https://boostnixmedia.com/">Boostnix Media</a>
</p>
