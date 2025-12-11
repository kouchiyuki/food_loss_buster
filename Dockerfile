# Dockerfile

# ベースイメージとして、元の環境で使用していたPHP 8.3 + Apacheを使用
FROM php:8.3-apache

# 必要なPHP拡張機能（PDO_MySQL）をインストール
# PDO_MySQLはDB接続に必須です
RUN apt-get update && \
    apt-get install -y libzip-dev libonig-dev && \
    docker-php-ext-install pdo_mysql mbstring zip