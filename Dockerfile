FROM php:8.1-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get install -y unzip git libzip-dev libpng-dev libonig-dev libxml2-dev libcurl4-openssl-dev pkg-config libssl-dev zip curl
RUN docker-php-ext-install zip
COPY . /var/www/html/
WORKDIR /var/www/html
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install
