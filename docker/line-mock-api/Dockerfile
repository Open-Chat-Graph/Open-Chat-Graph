FROM php:8.3-apache

# PHP設定ファイルをコピー
COPY php.ini /usr/local/etc/php/php.ini

# 必要なPHP拡張をインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apacheのmod_rewriteを有効化
RUN a2enmod rewrite

# 作業ディレクトリを設定
WORKDIR /app

# Apache設定ファイルをコピー
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# ポート80を公開
EXPOSE 80

# Entrypointスクリプトをコピー
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Apacheを起動（entrypointスクリプト経由）
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
