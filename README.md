# Laravel Paysera
Package that helps to use Paysera API in laravel application.

A fork of adumskis/laravel-paysera

### Installation
First require package with composer:
```sh
$ composer require artme/laravel-paysera
```
Then add service provider to config/app.php:
```php
'providers' => [
    ...
    Artme\Paysera\PayseraServiceProvider::class,
],
```
Facede to aliases:
```php
'aliases' => [
    ...
    'Paysera'   => Artme\Paysera\PayseraFacade::class,
],
```
And last is to publish config, migrations and view:
```sh
$ php artisan vendor:publish --provider="Artme\Paysera\PayseraServiceProvider"
$ php artisan migrate
```
