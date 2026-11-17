FROM php:8.4-apache

# Install system dependencies, PHP extensions, and Python ONNX Runtime dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    git \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libgl1 \
    libglib2.0-0 \
    python3 \
    python3-pip \
    python3-venv \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd mbstring exif pcntl bcmath zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Install lightweight Python ONNX dependencies for src/inference_cli.py
RUN python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/venv/bin/pip install --no-cache-dir \
        onnxruntime \
        opencv-python-headless \
        numpy \
        Pillow \
    && ln -sf /opt/venv/bin/python /usr/local/bin/python

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create non-root UID 1000 user (required by Hugging Face Spaces)
RUN useradd -m -u 1000 user

WORKDIR /var/www/html

# Install PHP dependencies first for better Docker layer caching
COPY --chown=user:user composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy application source
COPY --chown=user:user . .

# Re-run package discovery after full source copy
RUN php artisan package:discover --ansi || true

# Configure Apache DocumentRoot -> public/, port 7860, and writable dirs for UID 1000
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=7860
ENV HOME=/home/user

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "Listen 7860" > /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:[0-9]+>/<VirtualHost *:7860>/g' /etc/apache2/sites-available/000-default.conf \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public/leaves bootstrap/cache /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && chown -R user:user /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2 /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

COPY --chown=user:user docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

USER user

EXPOSE 7860

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
