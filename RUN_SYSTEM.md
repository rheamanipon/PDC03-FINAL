# PDC Concert Ticketing - Run Guide

This guide explains how to run the system on Windows using XAMPP.

## 1) Requirements

- XAMPP (Apache + MySQL)
- PHP (version required by this Laravel project)
- Composer
- Node.js + npm

## 2) Project Location

Make sure the project is inside:

`C:\xampp\htdocs\PDC-3-FINALPROJ-VER3`

## 3) First-Time Setup

Open terminal in the project folder, then run:

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

## 4) Database Setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Create a database in phpMyAdmin.
3. Update DB settings in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=concert_ticket_reservation_system
DB_USERNAME=root
DB_PASSWORD=
```

4. Run migrations (and seed if needed):

```bash
php artisan migrate
php artisan db:seed
```

If you already have SQL dump, import it in phpMyAdmin instead of seeding.

## 5) Storage Link (for uploaded images)

Run this once so concert images display correctly:

```bash
php artisan storage:link
```

## 6) Run the App

Use two terminals:

Terminal 1 (Laravel server):

```bash
php artisan serve
```

Terminal 2 (Vite assets):

```bash
npm run dev
```

Open:

- App: [http://127.0.0.1:8000](http://127.0.0.1:8000)

## 7) Quick Daily Run

Every time you work:

1. Start Apache + MySQL in XAMPP
2. In project folder:
   - `php artisan serve`
   - `npm run dev`

## 8) Common Fixes

- **Images not showing**
  - Run: `php artisan storage:link`

- **CSS/JS not loading**
  - Make sure `npm run dev` is running
  - Try `npm install` again if needed

- **Database connection error**
  - Recheck `.env` DB values
  - Confirm MySQL is running in XAMPP

- **App key error**
  - Run: `php artisan key:generate`

