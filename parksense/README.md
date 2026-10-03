# ParkSense (login + parking monitor)

Requires PHP 8.1 or newer with the `pdo_pgsql` extension, Apache (XAMPP works) and a Supabase project (the database).

## Setup (XAMPP on Windows + Supabase)
1. Copy this folder to `C:\xampp\htdocs\parksense`.
2. In `C:\xampp\php\php.ini` remove the `;` in front of `extension=pdo_pgsql`, then restart Apache.
3. In Supabase open **SQL Editor -> New query**, paste `database/schema.sql` and click **Run**. If you already set up the login system, run the updated schema again to create the parking slot and occupancy-history tables.
4. In Supabase click **Connect** and open the **Session pooler** tab. Copy the host and user
   into `includes/config.php` (`DB_HOST`, `DB_USER`). Then save the database password with
   `php database/set_db_password.php` (it tests the password and writes `includes/config.local.php`,
   which git ignores). The API keys (anon / publishable / service_role) are not used by this site.
5. Create the first accounts from a terminal in the project folder (there are no default passwords):
   `php database/create_user.php --username=admin1 --role=admin --name="Your Name"`
   `php database/create_user.php --username=guard1 --role=guard --name="Guard Name"`
   (On XAMPP the PHP program is `C:\xampp\php\php.exe`.)
6. Start Apache, open http://localhost/parksense/ and sign in.

## Folder map
- `login.php`, `logout.php`, `dashboard.php`, `index.php`: the pages.
- `includes/`: config, database, sessions, login logic, CSRF, top bar. Blocked from browsers.
- `database/`: `schema.sql` (run in Supabase) and the account-creation tool. Blocked from browsers.
- `assets/css/`: styles.

## Parking monitor (manual demo mode)
- An administrator can add slots, assign zones, and update their status from the dashboard. Guards can view the board and recent status history.
- Manual status changes are saved to `occupancy_logs`. Removed slots keep their history.
- The dashboard clearly marks manual demo mode; it does not claim to receive live sensor data. ESP32 reporting, device health, alerts, and automatic refresh still need to be connected when the hardware is available.

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
| Disabled staff still signed in | `is_active = false` takes effect on their next click |
| Clickjacking, sniffing | X-Frame-Options, frame-ancestors, nosniff, no-referrer, no-store caching |
| Direct file access | `.htaccess` blocks `includes/`, `database/`, and .sql/.md/.log files |
| Error leaks | `display_errors` off; errors go to the PHP log |
| Open redirects | Redirects go to fixed pages only |

## Before real deployment
- Serve over HTTPS (the HSTS header and Secure cookie switch on automatically).
- If possible, move `includes/` above the web root and change the `require` paths.
- On Nginx, `.htaccess` is ignored: add equivalent `deny all` rules for those folders.
- Keep `APP_DEBUG` false, keep PHP updated; turn on Supabase backups.

## Known limits (next steps)
- The username lockout also lets someone deliberately lock a real account for 15 minutes.
- No password reset, two-factor login, or user-management screen yet. Use `create_user.php`.
- Add `login_attempts` cleanup (delete rows older than 30 days) when you build reports.
