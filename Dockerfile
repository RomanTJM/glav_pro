FROM php:8.2-fpm

# Устанавливаем расширения PDO для MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Unzip нужен для Composer
RUN apt-get update && apt-get install -y unzip \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Сначала копируем только composer.json для кеширования слоя зависимостей
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist 2>/dev/null || true

# Разрешаем PHP-FPM передавать переменные окружения
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Копируем весь проект
COPY . .
RUN composer install --no-interaction --prefer-dist
