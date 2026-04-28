FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libwebp-dev && \
    docker-php-ext-configure gd --with-jpeg --with-webp && \
    docker-php-ext-install gd
RUN a2enmod rewrite
