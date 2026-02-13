# WP Headless Toolkit - WordPress Test Environment
# Based on official WordPress image with PHP 8.1+
# Adds Composer, WP-CLI, and Codeception dependencies

ARG PHP_VERSION=8.1
FROM wordpress:6.7-php${PHP_VERSION}

# Install system dependencies needed for Composer, WP-CLI, and testing
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    less \
    mariadb-client \
    subversion \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for Codeception
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install WP-CLI
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# Allow WP-CLI to run as root (required in Docker)
ENV WP_CLI_ALLOW_ROOT=1

# Copy the setup script
COPY docker/setup.sh /usr/local/bin/setup.sh
RUN chmod +x /usr/local/bin/setup.sh

# Set working directory to WordPress root
WORKDIR /var/www/html

# Use custom entrypoint that runs setup then falls through to Apache
ENTRYPOINT ["setup.sh"]
CMD ["apache2-foreground"]
