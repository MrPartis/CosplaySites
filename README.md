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

- XAMPP
- Command-line access (Windows `cmd.exe` is supported — examples below use `cmd` syntax)
- Optional: Composer (if you choose to enable optional PHPMailer dependency)

---

## How to run

- Copy the whole repository (only contents inside the repository) into /xampp/htdocs directory
- Run XAMPP, enable Apache and MySQL
- Create database 'cosplay_sites' from /phpmyadmin after enabling MySQL
- Start routing from /home
