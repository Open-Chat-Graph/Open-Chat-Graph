sudo sed -i 's/host\.docker\.internal/172.17.0.1/g' /usr/local/etc/php/php.ini
sudo service apache2 reload

cd /var/www/html
composer install

cat << 'EOF' > /var/www/html/shared/secrets.php
<?php

if (
    isset($_SERVER['HTTP_HOST'])
    && str_contains($_SERVER["HTTP_X_FORWARDED_HOST"], 'github.dev')
) {
    $_SERVER['HTTP_HOST'] = $_SERVER["HTTP_X_FORWARDED_HOST"];
    $_SERVER['HTTPS'] = 'on';
}
EOF
