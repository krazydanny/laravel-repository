Laravel Model Repository
========================

[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://paypal.me/danielspadafora)

This package provides an abstraction layer for easily implementing industry-standard caching strategies with Eloquent models.


- [Laravel Model Repository](#laravel-model-repository)
	- [Main Features](#main-features)
		- [Saves cache storage and money](#saves-cache-and-money)
		- [Simplifies caching strategies](#simplifies-caching-strategies)
		- [Optimized for Redis](#optimized-for-redis)
	- [Installation](#installation)
		- [Laravel version Compatibility](#laravel-version-compatibility)
		- [Lumen version Compatibility](#lumen-version-compatibility)
		- [Install the package via Composer](#install-the-package-via-composer)
	- [Creating a Repository for a Model](#creating-a-repository-for-a-model)
	- [Use with Singleton Pattern](#use-with-singleton-pattern)
	- [Calling built-in Eloquent methods](#calling-built-in-eloquent-methods)
	- [Making Eloquent Queries](#making-eloquent-queries)
	- [Implementing Caching Strategies](#caching-strategies)
		- [Read Strategies](#read-strategies)
			- [Read Aside](#read-aside)
			- [Read Through](#read-through)
		- [Write Strategies](#write-strategies)
			- [Write Back](#write-back)
			- [Write Through](#write-through)

<br><br>

Main Features
-------------


### Simplifies caching strategies

Implementing high availability and concurrency caching strategies could be a complex and time consuming task without the appropiate abstraction layer. 

Laravel Model Repository simplifies caching strategies using human-readable chained methods for your existing Eloquent models :)


### Saves cache storage and money

Current available methods for caching Laravel models store the entire PHP object in cache. That consumes a lot of extra storage and results in slower response times, therefore having a more expensive infrastructure.

Laravel Model Repository stores only the business specific data of your model necesary to recreate exactly the same instance later, after being loaded by PHP. Saving more than 50% of cache storage and significantly reducing response times from the cache server.

<br><br>

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

<br><br>

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

<br><br>


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

<br><br>

Calling built-in Eloquent methods
---------------------------------

Calling the native Eloquent Model methods from our repository gives us the advantage of combining them with caching strategies. First, let's see how we call them. It's pretty straightforward :)



Create a new model
```php

$user = $userRepository->create([
	'firstname' => 'Krazy',
	'lastname'  => 'Danny',
	'email'	    => 'somecrazy@email.com',
	'active'    => true,
]);

$user_id = $user->getKey();

```


Get a specific model by ID
```php

$user = $userRepository->get( $user_id );

```


Update a specific model
```php

$user->active = false;

$userRepository->save( $user );

```


Delete a specific model
```php


$userRepository->delete( $user );

```

<br><br>


Making Eloquent Queries
-----------------------

Unlike get() or save(), query methods work a little different. They receive as parameter the desired query builder instance (Illuminate\Database\Eloquent\Builder) in order to execute the query. For example:

To find all models under a certain criteria
```php

$q = User::where( 'active', true );

$userCollection = $userRepository->find( $q );

```

To get the first model instance under a certain criteria
```php

$q = User::where( 'active', true );

$user = $userRepository->first( $q );

```

To count all model instances under a certain criteria
```php

$q = User::where( 'active', true );

$userCount = $userRepository->count( $q );

```

<br><br>

Implementing Caching Strategies
-------------------------------
