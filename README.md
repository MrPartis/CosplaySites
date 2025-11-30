 # CosplaySites

CosplaySites is a compact PHP + vanilla JavaScript marketplace prototype intended for coursework and demonstration. It provides simple shop and item management, an image uploader with drag-and-drop reordering, a product detail viewer with a thumbnail strip, and lightweight JSON endpoints for AJAX interactions.

This README explains how to run the project locally, the database options, where the important client-side pieces live, and quick debugging tips (useful when developing the thumbnail/uploader UX).

---

## Quick Project Summary

- Purpose: coursework/demo marketplace web app
- Stack: PHP (PDO), vanilla JavaScript, minimal CSS
- Database: SQLite by default; supports MySQL when configured
- Dev server: PHP built-in server is sufficient for local testing

---

## Prerequisites

- PHP 7.4+ (with PDO and the desired driver: `pdo_sqlite` or `pdo_mysql`)
- Command-line access (Windows `cmd.exe` is supported — examples below use `cmd` syntax)
- Optional: Composer (if you choose to enable optional PHPMailer dependency)

---

## Quick start (development)

1. Open a Windows `cmd.exe` prompt in the repository root (where this README is).

2. (Optional) Set environment variables if you want to use a MySQL backend. The project detects and uses MySQL if configured — otherwise it falls back to an on-disk SQLite file. Check `src/db.php` for the exact environment variable names your copy expects; a common example is shown here:

   ```cmd
   :: Example MySQL environment variables (your project may read different names - confirm in src/db.php)
   set MYSQL_DSN=mysql:host=127.0.0.1;dbname=cosplaysites;charset=utf8mb4
   set MYSQL_USER=root
   set MYSQL_PASS=changeme
   ```

3. Start the built-in PHP server from the repository root:

   ```cmd
   php -S localhost:8000 -t .
   ```

4. Open your browser at `http://localhost:8000/home` (or `http://localhost:8000/`) and explore the app.

Notes:
- On first run the app's `src/db.php` performs conservative checks and may create any missing tables using `CREATE TABLE IF NOT EXISTS` statements. If you'd rather load schema/seed data manually see the Database section below.

---

## Database and seeds

- The repository includes `sql/create_schema.sql` and `sql/seeds.sql`.

- Using SQLite (default):

  - If the application isn't configured for MySQL, it will use an on-disk SQLite database file. To apply the SQL manually you can use the `sqlite3` CLI:

    ```cmd
    mkdir data
    sqlite3 data/db.sqlite < sql/create_schema.sql
    sqlite3 data/db.sqlite < sql/seeds.sql
    ```

- Using MySQL (example):

  ```cmd
  mysql -u root -p cosplaysites < sql/create_schema.sql
  mysql -u root -p cosplaysites < sql/seeds.sql
  ```

- If you prefer the app to create tables at runtime, start the dev server and visit the app; `src/db.php` will run safe `CREATE TABLE IF NOT EXISTS` and minor `ALTER TABLE` adjustments.

---

## File layout (high level)

- `src/` — PHP route handlers, templates, and server logic (authentication, owner pages, DB helpers)
- `assets/css/` — CSS styles; key gallery styles live in `assets/css/style.css`
- `assets/js/` — client scripts:
  - `main.js` — site main script: gallery/thumbnail logic, search suggestions, UI glue
  - `item-images.js` — uploader and drag/reorder helper exposing `initImageReorder(previewEl, imagesInputEl)`
- `sql/` — `create_schema.sql`, `seeds.sql`
- `data/` — runtime uploads and SQLite DB (created on first run)

---

## Image uploader, thumbnails and ordering (developer notes)

- The uploader supports multi-file selection, preview thumbnails, drag-and-drop reordering, and a server-friendly combined-order token scheme.

- Client tokens used when submitting the order:
  - `combinedOrder[]` values are tokens of the form `e{id}` for existing images (already in DB) and `n{index}` for newly added uploads. The server maps `n{index}` to the newly inserted image IDs after upload.

- Server side (example): `src/shop_add_item.php` reads `combinedOrder[]` and converts tokens into the final `displayOrder` for the `item_images` table.

- Thumbnail sizing and how to change it:
  - The thumbnail size used by uploader/editor is controlled by a CSS variable `--thumb-size` (default 80px). Change it at the top of `assets/css/style.css` or at runtime in DevTools:

    ```js
    // in browser console to test
    document.documentElement.style.setProperty('--thumb-size', '100px');
    ```

  - The viewer (product detail) also uses thumbnail sizing; the uploader previews were updated to use the same variable so sizes stay consistent.

- Key JS APIs and helpers:
  - `initImageReorder(previewSelector, fileInputSelector)` — sets up previews, drag/drop and syncs `combinedOrder[]` hidden fields.
  - Debug helpers (available in browser console):
    - `debugThumbStrip()` — logs bounding rects and draws temporary outlines around the main image, thumb-strip, thumbs container and nav buttons. Returns an object with `.clear()` to remove outlines.
    - `logThumbCenterCalc(index)` — logs the internal centering math for a thumbnail index.

---

## Development tips and debugging

- If the thumbnail strip appears misaligned, use the debug helper in the console:

  ```js
  const dbg = debugThumbStrip();
  console.log(dbg.info);
  dbg.clear(); // when done
  ```

- The thumbnail centering algorithm lives in `assets/js/main.js` (function `scrollActiveIntoView` and `syncThumbStripWidth`). The logic measures the main image bounding rect and adjusts the `.thumb-strip`/`.thumbs` widths to match visually.

- When changing gallery layout, test these interactions manually: resizing, changing the `.thumb-size`, adding/removing images in the editor, and using the arrow nav buttons.

---

## Common tasks

- Start dev server:
  ```cmd
  php -S localhost:8000 -t .
  ```

- Load seed data (sqlite example):
  ```cmd
  sqlite3 data/db.sqlite < sql/create_schema.sql
  sqlite3 data/db.sqlite < sql/seeds.sql
  ```

- Change thumbnail size globally (example):
  - Edit `assets/css/style.css` and add `:root { --thumb-size: 90px; }` or use the console snippet above to test.