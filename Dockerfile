FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev \
    default-mysql-client \
    zip unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install \
    pdo pdo_mysql mysqli mbstring \
    exif bcmath gd zip opcache

# CRITICAL FIX: Remove ALL MPM modules then enable only prefork
# This handles the case where php:apache image loads MPM in apache2.conf directly
RUN find /etc/apache2/mods-enabled -name "mpm_*" -delete \
    && find /etc/apache2/mods-available -name "mpm_event*" -exec sed -i 's/^LoadModule/#LoadModule/g' {} \; \
    && find /etc/apache2/mods-available -name "mpm_worker*" -exec sed -i 's/^LoadModule/#LoadModule/g' {} \; \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate

# PHP configuration
RUN echo "upload_max_filesize = 6M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 8M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 30" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "default_charset = UTF-8" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "expose_php = Off" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "allow_url_include = Off" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.cookie_samesite = Lax" >> /usr/local/etc/php/conf.d/custom.ini

# Apache config
COPY apache-railway.conf /etc/apache2/sites-available/000-default.conf

# Copy app files
WORKDIR /var/www/html
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod +x /var/www/html/startup.sh

# Verify MPM at build time
RUN echo "===MPM CHECK===" && ls -la /etc/apache2/mods-enabled/ | grep mpm && echo "===END==="

EXPOSE 80
CMD ["/var/www/html/startup.sh"]
