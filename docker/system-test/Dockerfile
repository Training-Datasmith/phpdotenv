FROM php:7.2.34-cli@sha256:5249d94b4b00db63a0ed53e6528114926110ab5dcb0d0f07cc30d1ea7dbe747e

ENV DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
    sed -i 's/deb.debian.org/archive.debian.org/g' /etc/apt/sources.list; \
    sed -i 's|security.debian.org/debian-security|archive.debian.org/debian-security|g' /etc/apt/sources.list; \
    sed -i '/buster-updates/d' /etc/apt/sources.list; \
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
