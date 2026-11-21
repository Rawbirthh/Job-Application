FROM php:8.4-cli-bookworm

# System dependencies for Composer, extensions and git-over-https installs
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    default-libmysqlclient-dev \
    libsqlite3-dev \
    libonig-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions this project uses (mirrors the author's local PHP setup)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        bcmath \
        intl \
        zip \
        curl \
        gd \
        pcntl \
        posix

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
