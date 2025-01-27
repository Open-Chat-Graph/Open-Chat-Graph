FROM php:8.3-apache

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
        libicu-dev \
        libssl-dev \
        git \
        zip \
        unzip \
        vim \
        wget \
        msmtp \
        msmtp-mta \
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

RUN pecl install xdebug-3.3.2 && \
    docker-php-ext-enable xdebug

RUN mv /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled
RUN /bin/sh -c a2enmod rewrite

RUN ln -sf  /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

CMD ["apache2-foreground"]
