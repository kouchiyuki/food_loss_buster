# Dockerfile

FROM php:8.3-apache

# 必要なPHP拡張機能
RUN apt-get update && \
    apt-get install -y libzip-dev libonig-dev && \
    docker-php-ext-install pdo_mysql mbstring zip