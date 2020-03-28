Laravel Repository
===============

[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://paypal.me/danielspadafora)

This package provides an abstraction layer for easily implementing industry-standard caching strategies with Eloquent models.


- [Laravel Repository](#laravel-repository)
	- [Installation](#installation)
		- [Laravel version Compatibility](#laravel-version-compatibility)
		- [Lumen version Compatibility](#lumen-version-compatibility)
		- [Install the package via Composer](#install-the-package-via-composer)
	- [Creating a Repository for a Model](#creating-a-repository-for-a-model)
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


Creating a Repository for a Model
---------------------------------	

Two parameters are required by the constructor. The first parameter is the model's full class name. The second parameter is the prefix to be used in cache to store model data.


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
--------------------------

As a good practice to improve performance and keep your project simple is strongly recommended to use repositories along with the singleton pattern, avoiding the need for creating separate instances for the same repository at different project levels.

First register the singleton call in a service provider.

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

Then access the same repository instance anywhere in your app :)

```php

app( App\UserRepository::class )

```


Calling Eloquent Model methods
------------------------------

Calling the native Eloquent Model methods from our repository gives us the advantage of combining them with caching strategies. First, let's see how we call them. It's pretty straightforward :)



Create a new model instance
```php

$user = $userRepository->create([
	'firstname' => 'Krazy',
	'lastname'  => 'Danny',
	'email'		=> 'somecrazy@email.com',
	'active'	=> true,
]);

$user_id = $user->getKey();

```


Get a specific model instance by ID
```php

$user = $userRepository->get( $user_id );

```


Update a specific model instance
```php

$user->active = false;

$userRepository->save( $user );

```
