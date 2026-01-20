FROM php:8.3-cli

# System deps for Composer installs
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
     git \
     unzip \
     libzip-dev \
  && docker-php-ext-install pdo_mysql zip \
  && rm -rf /var/lib/apt/lists/*

# Install Composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
