##################
### Main image ###
##################

# Build argument: Image tag
ARG IMAGE_TAG=8.1.34

# Build argument: The `COMPOSER_AUTH` value for building
ARG COMPOSER_AUTH

# Build argument: Composer version
ARG COMPOSER_VERSION=2.9.3

# Build argument: XDebug version
ARG XDEBUG_VERSION=3.5.0


###############################
### Stage: Composer version ###
###############################

# Base image
FROM composer:$COMPOSER_VERSION AS versioned-composer


#############################
### Stage: Base PHP image ###
#############################

# Base image
FROM php:$IMAGE_TAG AS php-base

# Installs libraries
ENV DEBIAN_FRONTEND=noninteractive
RUN apt update && apt install -y \
    git \
    libicu-dev \
    libpng-dev \
    libzip-dev \
    openssh-client \
    unzip \
    zlib1g-dev \
    --no-install-recommends && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Installs PHP extensions
RUN docker-php-ext-install \
    bcmath \
    intl \
    opcache \
    pcntl \
    zip

# Sets paths
ENV PATH=/app:/app/vendor/bin:$PATH

# Sets common environment variables
ENV PHP_USER_ID=33 \
    TERM=linux

# Prepares for loading private Composer dependencies
RUN mkdir -p ~/.ssh \
    && ssh-keyscan -H github.com >> ~/.ssh/known_hosts

# Sets the working directory
WORKDIR /app

# Copies base scripts and configs
COPY image-files/ /

# Fixes git dubious ownership
RUN git config --global --add safe.directory /app

# Sets up XDebug
ENV XDEBUG_MODE=off
ARG XDEBUG_VERSION
RUN pecl install xdebug-$XDEBUG_VERSION

# Installs Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=versioned-composer /usr/bin/composer /usr/bin/composer

# Starts the app
CMD ["sleep", "infinity"]
