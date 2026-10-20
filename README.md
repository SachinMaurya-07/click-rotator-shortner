# Click Rotation URL Shortener

A lightweight PHP URL shortener that rotates a short link across multiple destination URLs after a configurable number of clicks per destination.

## Features

- Create named short links (slugs)
- Configure multiple destination URLs for each short link
- Set a click limit per destination
- Rotate to the next destination after the configured limit
- Enable or disable individual links
- File-based storage with file locking
- Simple admin dashboard

## Requirements

- PHP 8.0+ (uses `str_starts_with`)
- Apache with `mod_rewrite` and `.htaccess` enabled, or equivalent rewrite rules
- Write permission for the `data/` directory

## Setup

1. Upload or clone the project into your PHP web root.
2. Copy `config.example.php` to `config.php`.
3. Open `config.php` and replace `REPLACE_WITH_A_LONG_RANDOM_PASSWORD` with a unique, strong admin password.
4. Ensure the PHP/web server can write to the `data/` directory.
5. Open `admin.html` in your browser and sign in with the password configured in `config.php`.
6. Create a short link, add destination URLs (one per line), set a click limit, and save.
7. Test a short link such as `https://your-domain.example/your-slug`.

### Local setup

With PHP installed, from the project directory run:

```bash
cp config.example.php config.php
php -S 127.0.0.1:8000
```

Then open `http://127.0.0.1:8000/admin.html`.

Note: PHP's built-in server is for local development only. Pretty short URLs may require Apache rewrite support; test the redirect behavior on your intended server.

## Data and security

- `config.php` and `data/links.json` are intentionally excluded from Git.
- `data/links.json` is generated automatically and can contain destination URLs and click statistics. Do not commit real production data.
- Change the example admin password before deployment.
- Use HTTPS in production and restrict access to the admin dashboard where possible.
- File-based storage is suitable for small deployments. High traffic or multi-server deployments should use a database such as MySQL with transactional updates.

## Deployment

For Hostinger or another PHP host, upload the project files, create `config.php` from `config.example.php`, set a strong password, and verify that `.htaccess` rules are supported by the hosting configuration.

## License

Add a license file before describing this project as open source. Without a license, normal copyright rules apply.
