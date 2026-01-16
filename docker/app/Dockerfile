FROM php:8.3-apache

# ビルド引数
ARG INSTALL_XDEBUG=true
ARG ENABLE_SSL=true

# apt-getのキャッシュマウントを使用して高速化（変更頻度低）
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends \
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
        faketime \
        libfaketime

# PHP拡張の設定とインストール（変更頻度低）
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

RUN docker-php-ext-install -j$(nproc) \
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
        gettext

RUN docker-php-ext-enable opcache

# Composerインストール（変更頻度低）
RUN cd /usr/bin && curl -s http://getcomposer.org/installer | php && ln -s /usr/bin/composer.phar /usr/bin/composer

# Xdebugインストール（CI環境ではスキップ）
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
        pecl install xdebug-3.3.2; \
    fi
# Xdebugはデフォルトで無効（環境変数ENABLE_XDEBUG=1で有効化）

# Apache設定（変更頻度低）
RUN a2enmod rewrite
RUN if [ "$ENABLE_SSL" = "true" ]; then a2enmod ssl; fi

# タイムゾーン設定（変更頻度低）
RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

# ユーザーID 1000でvscodeユーザーを作成（WSL互換性のため）（変更頻度低）
RUN useradd -m -u 1000 -G www-data -s /bin/bash vscode && \
    mkdir -p /home/vscode/.vscode-server && \
    chown -R vscode:vscode /home/vscode

# sudoers追加（vscodeとwww-data）（変更頻度低）
RUN echo "vscode ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers && \
    echo "www-data ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# openssl.iniファイルを作成して書き込み可能にする（変更頻度低）
RUN touch /usr/local/etc/php/conf.d/openssl.ini && \
    chmod 666 /usr/local/etc/php/conf.d/openssl.ini

# 設定ファイルのコピー（変更頻度高、最後に配置）
COPY php.ini /usr/local/etc/php/php.ini
RUN chmod 644 /usr/local/etc/php/php.ini

COPY setup-cron.sh /usr/local/bin/setup-cron.sh
RUN chmod +x /usr/local/bin/setup-cron.sh

CMD ["apache2-foreground"]
