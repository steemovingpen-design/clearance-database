# Use official PHP with Apache
FROM php:8.2-apache

# Fix Apache MPM conflict: forcefully remove ALL mpm module symlinks, then enable only prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_prefork.conf \
          /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

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
