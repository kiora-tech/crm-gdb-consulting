#!/bin/sh

# Fixer les permissions pour le dossier d'uploads
chmod -R 775 /var/www/website/public/uploads
chmod -R 777 /var/www/website/public/templates
chmod -R 777 /var/www/website/var
chown -R www-data:www-data /var/www/website
php-fpm