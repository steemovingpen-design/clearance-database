# Use official PHP with Apache
FROM php:8.2-apache

# Fix: Disable conflicting MPM modules, enable only mpm_prefork (required for PHP mod)
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . /var/www/html/

# Set permissions for uploads directory
RUN mkdir -p /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

# Apache config to allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/clearance.conf \
    && a2enconf clearance

EXPOSE 80
