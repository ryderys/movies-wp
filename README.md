# Movies WordPress Site

A WordPress website for managing and displaying movies content.

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB)
- Composer (optional, for dependency management)
- Node.js & npm (optional, for frontend tooling)

## Installation & Setup

### 1. Clone the Repository

```bash
git clone <repository-url>
cd movies
```

### 2. Configure WordPress

1. Copy the WordPress configuration template and update it:
   ```bash
   cp wp-config-sample.php wp-config.php
   ```

2. Edit `wp-config.php` and update the following:
   - `DB_NAME` - Your database name
   - `DB_USER` - Your database username
   - `DB_PASSWORD` - Your database password
   - `DB_HOST` - Your database host (usually `localhost`)
   - Secret keys (generate new ones from https://api.wordpress.org/secret-key/1.1/salt/)

### 3. Set Up Database

1. Create a new database in MySQL/MariaDB:
   ```sql
   CREATE DATABASE movies_db;
   ```

2. Run WordPress installation by visiting:
   ```
   http://localhost/movies/wp-admin/install.php
   ```

### 4. Install Dependencies (if applicable)

```bash
# For Composer dependencies
composer install

# For npm dependencies
npm install
```

### 5. Activate Custom Plugins & Themes

1. Log in to WordPress admin dashboard
2. Navigate to Plugins and activate custom plugins
3. Navigate to Appearance > Themes and activate the custom theme

## Project Structure

```
movies/
├── wp-content/
│   ├── themes/          # Custom and third-party themes
│   ├── plugins/         # Custom and third-party plugins
│   └── uploads/         # User-uploaded media (not in version control)
├── wp-config.php        # WordPress configuration (not in version control)
├── wp-config-sample.php # Configuration template
├── .gitignore          # Git ignore rules
├── README.md           # This file
├── composer.json       # PHP dependencies (if used)
└── package.json        # JavaScript dependencies (if used)
```

## Development

### Creating Custom Themes

Custom themes should be created in `wp-content/themes/your-theme-name/` and are version controlled.

### Creating Custom Plugins

Custom plugins should be created in `wp-content/plugins/custom-plugins/` and are version controlled. Third-party plugins are not tracked in Git.

### Environment Variables

If using a `.env` file for local development, make sure to:
1. Create a `.env` file (not tracked in Git)
2. Add it to `.gitignore`
3. Use a package like `vlucas/phpdotenv` to load environment variables

Example `.env`:
```
DB_NAME=movies_db
DB_USER=root
DB_PASSWORD=password
DB_HOST=localhost
WP_ENV=development
```

## Deployment

### Before Deploying

1. Ensure `wp-config.php` is not committed to version control
2. Update database credentials for the production environment
3. Set `WP_DEBUG` to `false` in production
4. Set proper file permissions:
   ```bash
   chmod 755 wp-content/themes/
   chmod 755 wp-content/plugins/
   chmod 644 wp-*.php
   ```

### Production Checklist

- [ ] Update `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` settings
- [ ] Set secure database credentials
- [ ] Enable HTTPS
- [ ] Configure backups
- [ ] Set up security headers
- [ ] Run security plugins (e.g., Wordfence)

## Maintenance

### Regular Backups

Create regular backups of:
- The database
- `wp-content/uploads/` directory
- Custom plugins and themes

### WordPress Updates

Keep WordPress core, plugins, and themes updated through the admin dashboard or via command line:

```bash
wp core update
wp plugin update --all
wp theme update --all
```

## Troubleshooting

### Database Connection Issues

- Verify MySQL/MariaDB is running
- Check database credentials in `wp-config.php`
- Ensure database user has proper permissions

### Permission Issues

- Check file and directory permissions in `wp-content/`
- Ensure web server has write access to uploads directory

### Plugins Not Loading

- Check PHP error logs
- Verify plugin files are in correct directory
- Check for PHP version compatibility

## Contributing

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Make your changes
3. Commit: `git commit -m "Add your feature"`
4. Push: `git push origin feature/your-feature`
5. Create a Pull Request

## License

See `license.txt` for licensing information.

## Support

For WordPress documentation, visit: https://wordpress.org/support/
