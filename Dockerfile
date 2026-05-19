FROM php:8.2-apache

# Cài extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy toàn bộ code vào apache
COPY . /var/www/html/

# Phân quyền
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80