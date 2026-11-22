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

# Node is needed to build the frontend. The Wayfinder Vite plugin also shells
# out to `php artisan`, so PHP and Node must live in the SAME stage.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /app

# Self-contained image: app code + production dependencies + built frontend.
COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

RUN npm ci

# These are baked into the JS bundle at build time (Vite reads them from env).
# Supply them as --build-arg in the cloud so Reverb points at the real host.
ARG VITE_APP_NAME="Job Application"
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=127.0.0.1
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

EXPOSE 8000 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
