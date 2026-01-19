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

# Build argument: Docker version
ARG DOCKER_VERSION=29.1.4-cli

# Build argument: SQLite version
ARG SQLITE_VERSION=3.46.1-7

# Build argument: SQL Server PDO version
ARG SQL_SERVER_PDO_VERSION=5.12.0

# Build argument: php-extension-installer version
ARG PHP_EXTENSION_INSTALLER_VERSION=2.9.27


###############################
### Stage: Composer version ###
###############################

# Base image
FROM composer:$COMPOSER_VERSION AS versioned-composer


#############################
### Stage: Docker version ###
#############################

# Base image
FROM docker:$DOCKER_VERSION AS versioned-docker


#############################
### Stage: Docker version ###
#############################

# Base image
FROM mlocati/php-extension-installer:$PHP_EXTENSION_INSTALLER_VERSION AS versioned-php-extension-installer


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
    libpq-dev \
    libsqlite3-dev \
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
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip

# Installs SQLite
ARG SQLITE_VERSION
RUN apt update && apt install -y \
    sqlite3=$SQLITE_VERSION \
    --no-install-recommends && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Installs SQL Server
ARG SQL_SERVER_PDO_VERSION
RUN apt update && \
    apt install -y --no-install-recommends \
        gnupg2 \
        unixodbc-dev \
        && \
    curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | \
        gpg --dearmor > /usr/share/keyrings/microsoft.gpg && \
    echo "deb [signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/11/prod bullseye main" \
        > /etc/apt/sources.list.d/mssql-release.list && \
    apt update && \
        ACCEPT_EULA=Y apt install -y --no-install-recommends \
        msodbcsql18 && \
    pecl install pdo_sqlsrv-$SQL_SERVER_PDO_VERSION && \
    docker-php-ext-enable pdo_sqlsrv && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Installs Oracle
COPY --from=versioned-php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_oci

# Sets paths
ENV PATH=/app:/app/vendor/bin:$PATH

# Sets common environment variables
ENV PHP_USER_ID=33 \
    TERM=linux

# Prepares for loading private Composer dependencies
RUN mkdir -p ~/.ssh \
    && ssh-keyscan -H github.com >> ~/.ssh/known_hosts

# Installs Docker
COPY --from=versioned-docker /usr/local/bin/docker /usr/local/bin/docker

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
