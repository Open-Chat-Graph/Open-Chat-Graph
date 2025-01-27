sed -i 's/host\.docker\.internal/172.17.0.1/g' /usr/local/etc/php/php.ini
service apache2 reload

cd /var/www/html
composer install
