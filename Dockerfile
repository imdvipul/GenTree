FROM php:8.2-apache

# Enable Apache rewrite
RUN a2enmod rewrite

# Install PDO + MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copy project
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Apache config
WORKDIR /var/www/html
