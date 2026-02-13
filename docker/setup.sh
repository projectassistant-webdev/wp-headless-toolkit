#!/bin/bash
set -euo pipefail

# WP Headless Toolkit - Docker Entrypoint Setup Script
# Waits for MySQL, installs WordPress + WPGraphQL, activates plugin, runs Composer

PLUGIN_DIR="/var/www/html/wp-content/plugins/wp-headless-toolkit"
WPGRAPHQL_VERSION="1.27.0"

# ---------------------------------------------------------------------------
# Helper: Wait for MySQL to be ready
# ---------------------------------------------------------------------------
wait_for_mysql() {
    local max_attempts=30
    local attempt=1

    echo "[setup] Waiting for MySQL at ${WORDPRESS_DB_HOST:-mysql}..."

    while [ "$attempt" -le "$max_attempts" ]; do
        if mysqladmin ping -h "${WORDPRESS_DB_HOST:-mysql}" \
            -u "${WORDPRESS_DB_USER:-root}" \
            -p"${WORDPRESS_DB_PASSWORD:-root}" --silent 2>/dev/null; then
            echo "[setup] MySQL is ready (attempt $attempt/$max_attempts)"
            return 0
        fi
        echo "[setup] MySQL not ready yet (attempt $attempt/$max_attempts)..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "[setup] ERROR: MySQL did not become ready after $max_attempts attempts"
    exit 1
}

# ---------------------------------------------------------------------------
# Helper: Run the official WordPress entrypoint to set up wp-config.php
# ---------------------------------------------------------------------------
setup_wp_config() {
    # Copy WordPress files and generate wp-config.php.
    # The official entrypoint only runs setup when $1 starts with "apache2"
    # or equals "php-fpm". We call it with "apache2-foreground" as $1 so
    # it copies files and creates wp-config.php, then we replace the exec
    # at the end (which would start Apache) by sending it to the background
    # briefly and killing it.
    if [ ! -f /var/www/html/wp-config.php ]; then
        echo "[setup] Running official WordPress entrypoint to generate wp-config.php..."

        # Run the official entrypoint which copies WP files + generates wp-config.php
        # then execs apache2-foreground. We background it and wait for wp-config.php.
        /usr/local/bin/docker-entrypoint.sh apache2-foreground &
        local wp_pid=$!

        # Wait for wp-config.php to appear (the entrypoint creates it before exec)
        local wait_count=0
        while [ ! -f /var/www/html/wp-config.php ] && [ "$wait_count" -lt 60 ]; do
            sleep 1
            wait_count=$((wait_count + 1))
        done

        # Give it a moment to finish writing
        sleep 2

        # Kill the Apache process that was exec'd
        kill "$wp_pid" 2>/dev/null || true
        wait "$wp_pid" 2>/dev/null || true

        if [ ! -f /var/www/html/wp-config.php ]; then
            echo "[setup] ERROR: wp-config.php was not created after ${wait_count}s"
            echo "[setup] Checking if WordPress files exist..."
            ls -la /var/www/html/wp-config* 2>/dev/null || echo "[setup] No wp-config files found"
            ls -la /var/www/html/index.php 2>/dev/null || echo "[setup] No index.php found"
            exit 1
        fi
        echo "[setup] wp-config.php created successfully"
    else
        echo "[setup] wp-config.php already exists"
    fi
}

# ---------------------------------------------------------------------------
# Install WordPress via WP-CLI (if not already installed)
# ---------------------------------------------------------------------------
install_wordpress() {
    if wp core is-installed 2>/dev/null; then
        echo "[setup] WordPress is already installed"
        return 0
    fi

    echo "[setup] Installing WordPress..."

    # Create the database if it doesn't exist
    wp db create 2>/dev/null || true

    wp core install \
        --url="http://localhost" \
        --title="WP Headless Toolkit Test" \
        --admin_user="${WORDPRESS_ADMIN_USER:-admin}" \
        --admin_password="${WORDPRESS_ADMIN_PASSWORD:-admin}" \
        --admin_email="${WORDPRESS_ADMIN_EMAIL:-admin@example.com}" \
        --skip-email

    echo "[setup] WordPress installed successfully"
}

# ---------------------------------------------------------------------------
# Install and activate WPGraphQL plugin
# ---------------------------------------------------------------------------
install_wpgraphql() {
    if wp plugin is-installed wp-graphql 2>/dev/null; then
        echo "[setup] WPGraphQL is already installed"
        wp plugin activate wp-graphql 2>/dev/null || true
        return 0
    fi

    echo "[setup] Installing WPGraphQL v${WPGRAPHQL_VERSION}..."

    # Download from WordPress.org plugin repository
    wp plugin install wp-graphql --version="${WPGRAPHQL_VERSION}" --activate

    echo "[setup] WPGraphQL installed and activated"
}

# ---------------------------------------------------------------------------
# Activate the WP Headless Toolkit plugin
# ---------------------------------------------------------------------------
activate_plugin() {
    if [ ! -d "$PLUGIN_DIR" ]; then
        echo "[setup] WARNING: Plugin directory not found at $PLUGIN_DIR"
        echo "[setup] Make sure the plugin source is mounted via docker-compose volume"
        return 1
    fi

    echo "[setup] Running composer install in plugin directory..."
    cd "$PLUGIN_DIR"

    # Install Composer dependencies (including dev dependencies for testing)
    composer install --no-interaction --prefer-dist 2>&1 | tail -5

    cd /var/www/html

    # Activate the plugin
    echo "[setup] Activating WP Headless Toolkit..."
    wp plugin activate wp-headless-toolkit

    echo "[setup] WP Headless Toolkit activated"
}

# ---------------------------------------------------------------------------
# Download WordPress test library (for WPUnit tests)
# ---------------------------------------------------------------------------
setup_test_library() {
    local tests_dir="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"

    if [ -d "$tests_dir" ]; then
        echo "[setup] WordPress test library already exists at $tests_dir"
        return 0
    fi

    echo "[setup] Downloading WordPress test library..."

    # Get WordPress version
    local wp_version
    wp_version=$(wp core version)

    mkdir -p "$tests_dir"

    # Download test library via SVN
    svn co --quiet \
        "https://develop.svn.wordpress.org/tags/${wp_version}/tests/phpunit/includes/" \
        "${tests_dir}/includes"

    svn co --quiet \
        "https://develop.svn.wordpress.org/tags/${wp_version}/tests/phpunit/data/" \
        "${tests_dir}/data"

    # Create wp-tests-config.php
    cat > "${tests_dir}/wp-tests-config.php" <<WPCONFIG
<?php
define( 'ABSPATH', '/var/www/html/' );
define( 'DB_NAME', '${WP_TESTS_DB_NAME:-wordpress_test}' );
define( 'DB_USER', '${WORDPRESS_DB_USER:-root}' );
define( 'DB_PASSWORD', '${WORDPRESS_DB_PASSWORD:-root}' );
define( 'DB_HOST', '${WORDPRESS_DB_HOST:-mysql}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', '${WORDPRESS_ADMIN_EMAIL:-admin@example.com}' );
define( 'WP_TESTS_TITLE', 'WP Headless Toolkit Tests' );
define( 'WP_PHP_BINARY', 'php' );
\$table_prefix = 'wptests_';
WPCONFIG

    echo "[setup] WordPress test library installed at $tests_dir"
}

# ---------------------------------------------------------------------------
# Main execution
# ---------------------------------------------------------------------------
main() {
    echo "============================================="
    echo " WP Headless Toolkit - Docker Setup"
    echo "============================================="

    wait_for_mysql
    setup_wp_config
    install_wordpress
    install_wpgraphql
    activate_plugin
    setup_test_library

    echo "============================================="
    echo " Setup complete! Starting Apache..."
    echo "============================================="

    # Hand off to the default CMD (apache2-foreground)
    exec "$@"
}

main "$@"
