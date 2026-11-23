FROM php:8.3-cli-alpine

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

COPY . /var/www

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "backend/public", "backend/public/index.php"]
