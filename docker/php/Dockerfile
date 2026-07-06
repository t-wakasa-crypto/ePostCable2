FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    autoconf \
    g++ \
    make \
    libpng-dev \
    libxml2-dev \
    libonig-dev \
    libzip-dev \
    fonts-ipaexfont \
    gnupg \
    && mkdir -p /usr/share/fonts/truetype/fonts-ipa \
    && ln -sf /usr/share/fonts/opentype/ipaexfont-gothic/ipaexg.ttf \
              /usr/share/fonts/truetype/fonts-ipa/ipaexg.ttf \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Redis拡張
RUN pecl install redis && docker-php-ext-enable redis

# Microsoft ODBC Driver 18 for SQL Server
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
       | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
       > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
       msodbcsql18 \
       unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# SQL Server PHP拡張 (sqlsrv / pdo_sqlsrv)
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# PHP-FPM ワーカーをホストの bind-mount オーナー (UID/GID 1000) と同じユーザーで動かす
# これにより storage/framework/ への書き込み権限問題を解消する
RUN groupadd -g 1000 laravel \
    && useradd -u 1000 -g laravel -s /bin/bash -m laravel \
    && sed -i 's/^user = www-data/user = laravel/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = www-data/group = laravel/' /usr/local/etc/php-fpm.d/www.conf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]