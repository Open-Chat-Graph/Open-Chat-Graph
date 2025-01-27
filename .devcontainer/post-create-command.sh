sed -i 's/host\.docker\.internal/172.17.0.1/g' /var/www/html/docker/app/php.ini
cd /var/www/html
composer install
