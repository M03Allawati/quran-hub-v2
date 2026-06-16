FROM debian:bookworm-slim

# Install Apache + PHP + extensions in one go
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-php \
    php php-mysql php-mbstring php-gd php-zip php-curl php-xml php-bcmath php-opcache \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate php

# PHP configuration
RUN echo "upload_max_filesize = 6M\n\
post_max_size = 8M\n\
max_execution_time = 30\n\
memory_limit = 256M\n\
default_charset = UTF-8\n\
expose_php = Off\n\
display_errors = Off\n\
log_errors = On\n\
allow_url_include = Off\n\
session.cookie_httponly = 1\n\
session.cookie_samesite = Lax" > /etc/php/8.2/apache2/conf.d/99-custom.ini

# Apache config
COPY apache-railway.conf /etc/apache2/sites-available/000-default.conf

# Copy app files
WORKDIR /var/www/html
RUN rm -rf /var/www/html/*
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod +x /var/www/html/startup.sh

# Apache needs these to run in foreground
ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2 \
    APACHE_PID_FILE=/var/run/apache2.pid \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_SERVER_NAME=localhost

RUN mkdir -p $APACHE_RUN_DIR $APACHE_LOCK_DIR $APACHE_LOG_DIR

EXPOSE 80
CMD ["/var/www/html/startup.sh"]
