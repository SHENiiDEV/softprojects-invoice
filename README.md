# SoftProjects Invoice Helper

⚡ **Universal E-Commerce Catalog Matcher, Cart Knapsack Optimizer & A4 PDF Invoice Generator.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/TailwindCSS-v3-38bdf8.svg)](https://tailwindcss.com/)
[![Nginx](https://img.shields.io/badge/Nginx-Production%20Ready-green.svg)](https://nginx.org/)

---

## 🌟 Features

- **Universal Multi-Engine Web Scraping & REST API Discovery:**
  - Automatic probing & pagination of Headless / Next.js / React / Shopify / WooCommerce REST APIs.
  - Automatic multi-currency resolution (`EUR`, `GBP`, `USD`) with cookie & header negotiation.
  - Automatic normalization of URLs & homoglyph sanitization.
- **Dynamic Bounded Knapsack Matching:**
  - Matches exact transaction amount with realistic constraints (1–2 items per SKU, max 3).
  - Exact cent adjustment on real catalog items (no dummy goods).
  - Flexible shipping configuration: Auto rule ($\le 50 \to 11.69$, $> 50 \to 0.00$), Free shipping ($0.00$), or Manual Custom Amount & Method title.
- **Instant Multi-Format Outputs:**
  - **UTF-8 Plaintext Receipt** (with Card Pan, `#ORDER-ID`, Customer Address & Items breakdown) with 1-click copy.
  - **Interactive Data Table** with direct product store links.
  - **A4 PDF Invoice** generated on demand in store's branding.
- **Built-in Security & Access Control:**
  - Session-based authentication gate for `SoftProjects`.
- **Production VPS Deployment:**
  - 1-click installer for Ubuntu/Debian (`deploy/deploy-vps.sh`) with Nginx + PHP-FPM + Let's Encrypt SSL for `invoice.soft-projects.io`.
  - Docker & Docker Compose setup (`Dockerfile`, `docker-compose.yml`).

---

## 🚀 Quick Start (Local Development)

```bash
# Clone the repository
git clone https://github.com/SHENiiDEV/softprojects-invoice.git
cd softprojects-invoice

# Run local development server
php -S 0.0.0.0:8000 -t platform
```
Open **`http://localhost:8000`** in your browser.

---

## 🌐 Production VPS Deployment (No Docker, Nginx + PHP-FPM)

- **Domain:** `invoice.soft-projects.io`
- **Target directory:** `/var/www/softprojects/invoice-helper`

```bash
# On your VPS:
sudo bash deploy/deploy-vps.sh
```

See [`VPS_DEPLOY_GUIDE.md`](VPS_DEPLOY_GUIDE.md) for full setup instructions and Nginx configuration.

---

## 🔐 Credentials
- **Username:** `SoftProjects`
- **Password:** `7Gq`W~<Bd82A`

---

## 📄 License
MIT License. Developed for SoftProjects.
