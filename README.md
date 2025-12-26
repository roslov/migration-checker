Database Migration Checker
==========================

This package validates database migrations. It checks whether all up and down migrations run without errors.

It also validates that down migrations revert all changes, so there is no diff in the database after running them.


## Requirements

- PHP 8.1 or higher.


## Installation

The package could be installed with composer:

```shell
composer require roslov/migration-checker --dev
```


## General usage

TBD


## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Code style analysis

The code style is analyzed with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and
[PSR-12 Ext coding standard](https://github.com/roslov/psr12ext). To run code style analysis:

```shell
./vendor/bin/phpcs --extensions=php --colors --standard=PSR12Ext --ignore=vendor/* -p -s .
```

