#!/bin/bash
composer install && \
composer dump-autoload  && \
export APP_INSTALL=0 && \
# php artisan migrate:fresh --seed && \
php artisan key:generate && \
chmod -R 775 storage bootstrap/cache && \
chmod -R 777 /var/www/html/storage && \
php -S 0.0.0.0:80