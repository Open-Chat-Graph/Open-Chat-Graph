cat << 'EOF' > /var/www/html/.user.ini
xdebug.client_host=172.17.0.1
EOF

cd /var/www/html
composer install

service apache2 reload