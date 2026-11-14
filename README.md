# Don't Touch My Hair

## Booking system requirements

The shared appointment calendar, admin approval, email notifications, and private feedback forms require PHP hosting and a database. GitHub Pages only serves static files and cannot run the PHP endpoints in this project. Do not publish this version to GitHub Pages as a replacement for the existing static site; the booking form will not work there.

The project supports SQLite for local development and MySQL for production. It uses PHP 8.1 or newer with PDO, PDO_SQLITE for local development, and PDO_MYSQL when using MySQL.

## Free hosting to get started

[x10Hosting Basic](https://x10hosting.com/) is a reasonable free trial home for this PHP/MySQL site: its current free-plan page lists PHP, two MySQL databases, three email addresses, and up to three domains on one hosting service, with a free subdomain option. It is community-supported, and its terms warn that inactive or empty accounts can be suspended. Keep a separate backup of your site and database. Check that the account offers PHP 8.1 or newer and test PHP email delivery before relying on booking notifications.

The site can also run on another PHP host that provides MySQL, HTTPS, writable uploads, and outgoing email. The free plan is for getting started; it is not a substitute for reliable backups and supported hosting for business-critical bookings.

## Set up the free host

1. Create an account through [x10Hosting](https://x10hosting.com/signup), choose the free Basic plan, and select one of its free subdomains. The host currently limits a free account to one hosting service; confirm its current limits during signup.
2. In the hosting panel, select PHP 8.1 or newer. Create a MySQL database and user, and note the exact database host, database name, username, and password shown by the panel.
3. Use File Manager or FTP to upload the website files into the account's website root. Include hidden `.htaccess` files and the `data/` and `uploads/` folders. Do not upload `.git`, local database files, or credentials.
4. Copy `private_config.example.php` to `private_config.php` in the website root. Edit the copy with the database details, the hosted site's HTTPS URL, a sender email address supplied by the host, and your admin password hash. `private_config.php` is ignored by Git and blocked from direct web access; never commit or share it.
5. Generate the admin password hash on a trusted computer with PHP:
```sh
php -r "echo password_hash('replace-with-a-long-unique-password', PASSWORD_DEFAULT), PHP_EOL;"
```

   Paste the resulting hash into `ADMIN_PASSWORD_HASH` in `private_config.php`; do not use the example password. In `DB_DSN`, replace the example database host and name with the exact values from the hosting panel. Also set `DB_USER`, `DB_PASSWORD`, `BOOKING_FROM_EMAIL`, and `PUBLIC_SITE_URL` there. The PHP app creates its tables on first use; you do not need to import `Database.sql`.
6. Open the hosted site's `/track.html`, sign in, and add appointment date/time ranges. Each range represents one booking. Test the customer booking page with your own email, then approve and decline test requests and check that emails arrive. Mark a test appointment complete to test its private feedback link.
7. If the host reports that email could not be sent, do not assume customers received anything. The admin page will show that warning; check the host's mail settings or contact its community support before advertising online bookings.

This will use the host's URL, not the existing GitHub Pages URL. GitHub Pages cannot run the PHP booking system, so do not publish this PHP version there.

For a later paid upgrade in South Africa, [xneelo Web Hosting](https://xneelo.co.za/web-hosting/) lists multi-domain plans with PHP and MySQL. Check its current packages and prices when you are ready.

## Local development

With PHP and PDO_SQLITE installed, run the site from this directory:

```sh
php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000/`. The owner dashboard is at `/track.html`. Configure an `ADMIN_PASSWORD_HASH` before signing in. Local PHP may not be able to send email; the admin and customer pages report delivery failures rather than pretending an email was sent.
