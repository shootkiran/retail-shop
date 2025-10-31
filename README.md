# Simple Retail POS

A Laravel 12 + Filament v4 starter tailored for a retail point-of-sale (POS) workflow. It ships with resources for managing
customers, vendors, products, purchases, and sales, plus a dedicated POS dashboard page to orchestrate front-of-house tasks.
SQLite powers persistence out of the box so you can get running quickly without extra infrastructure.

## Features

- **Filament Admin Panel** pre-configured with navigation groups for Contacts, Catalog, Transactions, Finance, and POS.
- **Resources** for customers, vendors, product categories, product items, payment methods, sales, and purchases, each with
  tailored forms, tables, and automation for totals/stock metadata.
- **Interactive Sales & Purchase forms** that calculate line totals, discounts, and payment balances in real time.
- **Realtime POS workspace** built with Filament UI components that supports barcode scanning, dynamic carts, hold/resume
  orders, multi-payment handling, and one-click PDF invoicing.

## Getting Started

### Requirements

- PHP 8.2+
- Composer
- Node.js 18+

### Installation

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build # or `npm run dev` when developing assets
```

SQLite is configured by default. A blank database file is created automatically the first time you run migrations.

### Admin Access

A default Filament admin account is created during setup:

- **URL:** `http://localhost/admin`
- **Email:** `admin@example.com`
- **Password:** `password`

Update the credentials after your first login.

### Running the App

```bash
php artisan serve
```
Visit the Filament dashboard at `http://localhost/admin` and explore the POS page from the navigation sidebar.

## Testing

```bash
php artisan test
```

## Coding Standards

This project follows the defaults enforced by Laravel Pint. Run the formatter before opening a PR:

```bash
./vendor/bin/pint
```

---

Built with ❤️ using [Laravel](https://laravel.com) and [Filament](https://filamentphp.com).
