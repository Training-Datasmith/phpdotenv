FROM php:7.2.34-cli@sha256:5249d94b4b00db63a0ed53e6528114926110ab5dcb0d0f07cc30d1ea7dbe747e

ENV DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
    printf 'deb https://archive.debian.org/debian buster main\n' > /etc/apt/sources.list; \
    printf 'deb https://archive.debian.org/debian-security buster/updates main\n' >> /etc/apt/sources.list; \
    apt-get -o Acquire::https::archive.debian.org::Check-Valid-Until=false update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libzip-dev \
        libonig-dev \
    ; \
    docker-php-ext-install -j"$(nproc)" mbstring; \
    echo "error_reporting=32767" > /usr/local/etc/php/conf.d/error-reporting.ini; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
