FROM php:8.2-apache

# Enable Apache rewrite
RUN a2enmod rewrite

# Install PDO + MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copy project
COPY . /var/www/html/

# Set Apache DocumentRoot to /public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' \
    /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

WORKDIR /var/www/html/public
