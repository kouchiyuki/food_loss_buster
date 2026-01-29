# Dockerfile
FROM php:8.3-apache

# 必要なPHP拡張機能
RUN apt-get update && \
    apt-get install -y libzip-dev libonig-dev curl unzip && \
    docker-php-ext-install pdo_mysql mbstring zip

# Composer（必要なら）
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 環境変数をPHPで読み込めるように
# （docker-compose.yml の env_file と組み合わせる）
ENV TZ=Asia/Tokyo

# ドキュメントルート
WORKDIR /var/www/html

# 権限
RUN chown -R www-data:www-data /var/www/html
