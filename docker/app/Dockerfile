FROM php:8.3-apache

# ユーザーID 1000でvscodeユーザーを作成（WSL互換性のため）
RUN useradd -m -u 1000 -G www-data -s /bin/bash vscode && \
    mkdir -p /home/vscode/.vscode-server && \
    chown -R vscode:vscode /home/vscode

COPY php.ini /usr/local/etc/php/php.ini
RUN chmod 644 /usr/local/etc/php/php.ini

# openssl.iniファイルを作成して書き込み可能にする
RUN touch /usr/local/etc/php/conf.d/openssl.ini && \
    chmod 666 /usr/local/etc/php/conf.d/openssl.ini

RUN cd /usr/bin && curl -s http://getcomposer.org/installer | php && ln -s /usr/bin/composer.phar /usr/bin/composer

RUN apt-get update && apt-get install -y \
        default-mysql-client \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libxslt-dev \
        zlib1g-dev \
        libcurl4-openssl-dev \
        libsqlite3-dev \
        sqlite3 \
        libicu-dev \
        libssl-dev \
        git \
        zip \
        unzip \
        vim \
        wget \
        msmtp \
        msmtp-mta \
        sudo \
        cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        mysqli \
        zip \
        mbstring \
        intl \
        xml \
        soap \
        xsl \
        curl \
        opcache \
        bcmath \
        exif \
        pcntl \
        sockets \
        gettext \
    && docker-php-ext-enable \
        opcache

# sudoers追加（vscodeとwww-data）
RUN echo "vscode ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers && \
    echo "www-data ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

RUN pecl install xdebug-3.3.2
# Xdebugはデフォルトで無効（環境変数ENABLE_XDEBUG=1で有効化）

RUN a2enmod rewrite && a2enmod ssl

RUN ln -sf  /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

# Cron設定スクリプトをコピー
COPY setup-cron.sh /usr/local/bin/setup-cron.sh
RUN chmod +x /usr/local/bin/setup-cron.sh

CMD ["apache2-foreground"]
