# ParkSense (login + top-bar dashboard)

Requires PHP 8.1 or newer, MySQL/MariaDB and Apache (XAMPP works).

## Setup (XAMPP on Windows)
1. Copy this folder to `C:\xampp\htdocs\parksense`.
2. Start Apache and MySQL in the XAMPP control panel.
3. Open phpMyAdmin, choose Import, and import `database/schema.sql`.
4. In phpMyAdmin (SQL tab) create the website's database user. Use your own long password:
   `CREATE USER 'parksense_app'@'localhost' IDENTIFIED BY 'your-long-password';`
   `GRANT SELECT, INSERT, UPDATE, DELETE ON parksense.* TO 'parksense_app'@'localhost';`
5. Put the same password in `includes/config.php` (`DB_PASS`).
6. Create the first accounts from a terminal in the project folder (there are no default passwords):
   `php database/create_user.php --username=admin1 --role=admin --name="Your Name"`
   `php database/create_user.php --username=guard1 --role=guard --name="Guard Name"`
   (On XAMPP the PHP program is `C:\xampp\php\php.exe`.)
7. Open http://localhost/parksense/ and sign in.

## Folder map
- `login.php`, `logout.php`, `dashboard.php`, `index.php`: the pages.
- `includes/`: config, database, sessions, login logic, CSRF, top bar. Blocked from browsers.
- `database/`: `schema.sql` and the account-creation tool. Blocked from browsers.
- `assets/css/`: styles.

## Security measures in this version
| Risk | Protection |
|---|---|
| Stolen passwords in a database leak | Passwords stored only as bcrypt hashes (`password_hash`), upgraded automatically |
| SQL injection | PDO with real prepared statements everywhere |
| Cross-site scripting | Every printed value goes through `e()`; strict Content-Security-Policy |
| Cross-site request forgery | CSRF token on every form; sign-out is a POST |
| Password guessing | 5 failed tries per username or 20 per IP locks sign-in for 15 minutes |
| Finding valid usernames | One generic error message; equal timing for unknown users |
| Session hijacking / fixation | HttpOnly, SameSite=Strict, Secure (on HTTPS) cookies; new session ID at login; 15 min idle and 8 h absolute timeouts |
| Role tampering | Role is read from the database on every request; `require_role()` checks on the server |
| Disabled staff still signed in | `is_active = 0` takes effect on their next click |
| Clickjacking, sniffing | X-Frame-Options, frame-ancestors, nosniff, no-referrer, no-store caching |
| Direct file access | `.htaccess` blocks `includes/`, `database/`, and .sql/.md/.log files |
| Error leaks | `display_errors` off; errors go to the PHP log |
| Open redirects | Redirects go to fixed pages only |

## Before real deployment
- Serve over HTTPS (the HSTS header and Secure cookie switch on automatically).
- If possible, move `includes/` above the web root and change the `require` paths.
- On Nginx, `.htaccess` is ignored: add equivalent `deny all` rules for those folders.
- Keep `APP_DEBUG` false, keep PHP and MySQL updated, back up the database.

## Known limits (next steps)
- The username lockout also lets someone deliberately lock a real account for 15 minutes.
- No password reset, two-factor login, or user-management screen yet. Use `create_user.php`.
- Add `login_attempts` cleanup (delete rows older than 30 days) when you build reports.
