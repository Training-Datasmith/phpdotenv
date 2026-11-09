FROM php:8.5-cli@sha256:535bbd40e9b483eb4edaf78b7cfb43cc1682b6f3953350ff4a86416c159176f7

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" zip \
    && echo "error_reporting=32767" > /usr/local/etc/php/conf.d/error-reporting.ini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
