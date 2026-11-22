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
  <a href="https://abid2929.github.io/JUTAR-BAZAR/admin.html"><b>🔐 Admin demo</b></a>
</p>

<p align="center">
  <kbd>PHP 8</kbd> <kbd>SQLite</kbd> <kbd>JavaScript</kbd> <kbd>CSS3</kbd> <kbd>Google Gemini</kbd> <kbd>Facebook Pixel</kbd> <kbd>GA4</kbd> <kbd>Bangla + English</kbd>
</p>

> The demo is a static preview, so nothing is saved. The admin demo opens **without a password** and shows **sample data only**.

---

## ✨ What's inside

**Storefront (opens in বাংলা, one tap to English)**
- Real shop info: JUTAR BAZAR BD, call/WhatsApp 01624-244920, Facebook page, Dhaka address (opposite Jamuna Future Park). All editable in Settings.
- Offer banner slider at the top. Every collection page opens on its own banner.
- **Men / Women / Kids** sections, plus 5 collections: Running, Sneakers, Loafers, Formal, Sandals.
- Product pages with a size guide, an "only X left" badge, related products and recently viewed.
- Cart, cash-on-delivery checkout, order tracking, WhatsApp ordering and a WhatsApp popup.
- Phone layout that feels like an app: one-line header and a bottom tab bar (Home, Shop, Cart, WhatsApp).
- Fast: phone-sized photo copies, lazy loading and clean HTML. SEO-ready: meta tags, canonical, JSON-LD and sitemap.xml.

**Admin panel (Bangla, red theme)**
- Dashboard with a sales chart, orders, invoices and CSV export.
- Products with multi-photo upload. Each product has a "for whom" field (men, women, kids or both).
- Collections, 9 offer banners, contact and delivery charges, logo and colours, SEO and tracking (Facebook Pixel, GA4), password and backup.
- One-click **FB post copy** and **view on site** for every product; filters for men / women / kids / low stock.
- **Auto caption:** 5 tones (premium, offer, festive, friendly, simple village-friendly Bangla), knows kids and ladies shoes, English + Bangla product text, Facebook post, Instagram caption and Google title. Free with a Google Gemini key, or uses built-in templates.

## 🚀 Install on cPanel (about 5 minutes)

1. Download this repo as a ZIP (**Code → Download ZIP**).
2. Upload everything **except the `docs/` folder** to `public_html`, then extract it.
3. Open `yourdomain.com/admin` and set your admin password on the first visit.
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

## 📝 Notes

- Demo product photos are free-licence photos (Unsplash) with brand logos removed. Replace them with your own shop photos from the admin panel.
- No passwords, API keys or customer data are stored in this repo.

---

<p align="center">
  Designed &amp; developed by <a href="https://github.com/abid2929"><b>Abid Islam</b></a> &amp; <a href="https://boostnixmedia.com/">Boostnix Media</a>
</p>
