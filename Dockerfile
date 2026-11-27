FROM php:8.3-cli-alpine

RUN apk add --no-cache tzdata \
    && docker-php-ext-install pdo pdo_mysql

ENV TZ=Asia/Ho_Chi_Minh

WORKDIR /var/www

COPY backend /var/www/backend
COPY frontend /var/www/frontend

RUN mkdir -p /var/www/backend/storage/uploads/student_cards /var/www/backend/storage/logs \
    && chmod -R 777 /var/www/backend/storage

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "backend/public", "backend/public/index.php"]
