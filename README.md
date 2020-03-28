Laravel Repository
===============

[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://paypal.me/danielspadafora)

This package provides an abstraction layer for easily implementing industry-standard caching strategies with Eloquent models.


- [Laravel Repository](#laravel-repository)
	- [Installation](#installation)
		- [Laravel version Compatibility](#laravel-version-compatibility)
		- [Lumen version Compatibility](#lumen-version-compatibility)
		- [Install the package via Composer](#install-the-package-via-composer)
	- [Create a Repository for a Model](#create-a-repository-for-a-model)
	- [Use with Singleton Pattern](#use-with-singleton-pattern)
	- [Calling Eloquent Model methods](#model-methods)
	- [Making Eloquent Queries](#eloquent-queries)
	- [Implementing Caching Strategies](#caching-strategies)
		- [Read Strategies](#read-strategies)
			- [Read Aside](#read-aside)
			- [Read Through](#read-through)
		- [Write Strategies](#write-strategies)
			- [Write Back](#write-back)
			- [Write Through](#write-through)


Installation
------------
Make sure you have configured a cache connection and driver in your Laravel project. You can find cache configuration instructions for Laravel at https://laravel.com/docs/6.x/cache and for Lumen at https://lumen.laravel.com/docs/6.x/cache


### Laravel version Compatibility

 Laravel  | Package
:---------|:----------
 5.6.x    | 0.9.x
 5.7.x    | 0.9.x
 5.8.x    | 0.9.x
 6.x      | 0.9.x


### Lumen version Compatibility

 Lumen    | Package
:---------|:----------
 5.6.x    | 0.9.x
 5.7.x    | 0.9.x
 5.8.x    | 0.9.x
 6.x      | 0.9.x



### Install the package via Composer

```bash
$ composer require krazydanny/laravel-repository
```


Create a Repository for a Model
-------------------------------

To parameters are required by the constructor. The first parameter is Model's full class name. The second one is the prefix to be used to store models in cache.


```php

namespace App\Repositories;

use App\User;
use KrazyDanny\Eloquent\Repository;

class UserRepository extends Repository {

	public function __construct ( ) {

		parent::__construct(
			User::class, // Model's class name
			'Users' // the name of the cache prefix
		);
	}
}

```


Use with Singleton Pattern
-------------------------------

First register the Singleton call in a Service Provider.

```php

namespace App\Providers;

use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

    public function register() {

        $this->app->singleton( 
           UserRepository::class, 
            function () {
                return (new UserRepository);
            }
        );

```

Then access the same Repository instance anywhere in your app :)

```php

app( App\UserRepository::class )

```


Calling Eloquent Model methods
-------------------------------

```php



```
