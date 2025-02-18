#!/bin/sh

php bin/console doctrine:migrations:migrate

# Fixer les permissions pour le dossier d'uploads
chmod -R 775 /var/www/website/public/uploads
chmod -R 777 /var/www/website/var

php-fpm