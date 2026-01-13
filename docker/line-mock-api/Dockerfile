FROM php:8.3-apache

# 必要なPHP拡張をインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apacheのmod_rewriteとmod_sslを有効化
RUN a2enmod rewrite ssl

# 作業ディレクトリを設定
WORKDIR /app

# Apache設定ファイルをコピー
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY apache-ssl.conf /etc/apache2/sites-available/default-ssl.conf

# SSL設定を有効化
RUN a2ensite default-ssl

# ポート80と443を公開
EXPOSE 80 443

# Apacheを起動
CMD ["apache2-foreground"]
