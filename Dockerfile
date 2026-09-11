FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Install Apache + PHP + MySQL extension (Ubuntu auto-enables mpm_prefork with mod_php - no conflicts)
RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-mysql \
    php8.1-gd \
    php8.1-mbstring \
    php8.1-curl \
    php8.1-zip \
    libapache2-mod-php8.1 \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN mkdir -p /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && rm -f /var/www/html/Dockerfile

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
