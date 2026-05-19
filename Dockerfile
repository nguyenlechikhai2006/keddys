FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    && docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /var/www
COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/var/www"]