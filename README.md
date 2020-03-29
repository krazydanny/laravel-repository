Laravel Model Repository (COMING SOON!)
========================

[![Latest Stable Version](https://img.shields.io/github/v/release/krazydanny/laravel-repository)](https://packagist.org/packages/krazydanny/laravel-repository) [![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://paypal.me/danielspadafora)

This package provides an abstraction layer for easily implementing industry-standard caching strategies with Eloquent models.


- [Laravel Model Repository](#laravel-model-repository)
	- [Main Features](#main-features)
		- [Simplify caching strategies and buy time](#simplify-caching-strategies-and-buy-time)	
		- [Save cache storage and money](#save-cache-storage-and-money)
	- [Installation](#installation)
		- [Laravel version Compatibility](#laravel-version-compatibility)
		- [Lumen version Compatibility](#lumen-version-compatibility)
		- [Install the package via Composer](#install-the-package-via-composer)
	- [Creating a Repository for a Model](#creating-a-repository-for-a-model)
	- [Use with Singleton Pattern](#use-with-singleton-pattern)
	- [Calling built-in Eloquent methods](#calling-built-in-eloquent-methods)
	- [Making Eloquent Queries](#making-eloquent-queries)
	- [Caching methods overview](#caching-methods-overview)	
	- [Implementing Caching Strategies](#implementing-caching-strategies)
		- [Read Aside](#read-aside-cache)
		- [Read Through](#read-through-cache)
		- [Write Back](#write-back-cache)
		- [Write Through](#write-through-cache)
	- [Extending Laravel Model Repository](#extending-laravel-model-repository)
		- [Pretty Queries](#pretty-queries)	
	- [Some things I wish somebody told me before](#some-things-i-wish-somebody-told-me-before)
	- [Bibliography](#bibliography)


<br>

Main Features
-------------


### Simplify caching strategies and buy time

Implementing high availability and concurrency caching strategies could be a complex and time consuming task without the appropiate abstraction layer.

Laravel Model Repository simplifies caching strategies using human-readable chained methods for your existing Eloquent models :)


### Save cache storage and money

Current available methods for caching Laravel models store the entire PHP object in cache. That consumes a lot of extra storage and results in slower response times, therefore having a more expensive infrastructure.

Laravel Model Repository stores only the business specific data of your model in order to recreate exactly the same instance later (after data being loaded from cache). Saving more than 50% of cache storage and significantly reducing response times from the cache server.

<br>

Installation
------------
Make sure you have properly configured a cache connection and driver in your Laravel/Lumen project. You can find cache configuration instructions for Laravel at https://laravel.com/docs/6.x/cache and for Lumen at https://lumen.laravel.com/docs/6.x/cache


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

<br>

Creating a Repository for a Model
---------------------------------	

In order to simplify caching strategies we will encapsulate model access within a model repository.

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

<br>


Use with Singleton Pattern
--------------------------

As a good practice to improve performance and keep your project simple is strongly recommended to use repositories along with the singleton pattern, avoiding the need for creating separate instances for the same repository at different project levels.

First register the singleton call in a service provider:

```php
namespace App\Providers;

use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

    public function register ( ) {

        $this->app->singleton( 
           UserRepository::class, 
            function () {
                return (new UserRepository);
            }
        );
    }

    # other service provider methods here
}

```

Add a line like this on every file you call the repository in order to keep code clean and pretty ;)

```php
use App\Repositories\UserRepository;

```

Then access the same repository instance anywhere in your project :)

```php
$userRepository = app( UserRepository::class );

```

<br>

Calling built-in Eloquent methods
---------------------------------

Calling native Eloquent Model methods from our repository gives us the advantage of combining them with caching strategies. First, let's see how we call them. It's pretty straightforward :)


Create a new model:
```php
$user = app( UserRepository::class )->create([
	'firstname' => 'Krazy',
	'lastname'  => 'Danny',
	'email'	    => 'somecrazy@email.com',
	'active'    => true,
]);

$user_id = $user->getKey();

```


Get a specific model by ID:
```php
$user = app( UserRepository::class )->get( $user_id );

```


Update a specific model:
```php
$user->active = false;

app( UserRepository::class )->save( $user );

```


Delete a specific model:
```php
app( UserRepository::class )->delete( $user );

```

<br>


Making Eloquent Queries
-----------------------

Unlike get() or save(), query methods work a little different. They receive as parameter the desired query builder instance (Illuminate\Database\Eloquent\Builder) in order to execute the query.

This will allow us to combine queries with caching strategies, as we will cover forward on this document. For now let's focus on the query methods only. For example:

To find all models under a certain criteria:
```php
$q = User::where( 'active', true );

$userCollection = app( UserRepository::class )->find( $q );

```

To get the first model instance under a certain criteria:
```php
$q = User::where( 'active', true );

$user = app( UserRepository::class )->first( $q );

```

To count all model instances under a certain criteria:
```php
$q = User::where( 'active', true );

$userCount = app( UserRepository::class )->count( $q );

```

<br>


Caching methods overview
------------------------

### remember() & during()

Calling remember() before any query method like find(), first() or count() stores the query result in cache for a given time. Always followed by the during() method, which defines the duration of the results in cache (TTL/Time-To-Live in seconds)


```php
$q = User::where( 'active', true );

app( UserRepository::class )->remember()->during( 3600 )->find( $q );

```


Also a model instance could be passed as parameter in order to store that specific model in cache.


```php
app( UserRepository::class )->remember( $user )->during( 3600 );

```



### rememberForever()

Calling rememberForever() before any query method like find(), first() or count() stores the query result in cache without an expiration time.


```php
$q = User::where( 'active', true );

app( UserRepository::class )->rememberForever()->find( $q );

```


Also a model instance could be passed as parameter in order to store that specific model in cache without expiration.


```php
app( UserRepository::class )->rememberForever( $user );

```


### fromCache()

Calling fromCache() before any query method like find(), first() or count() will try to retrieve the results from cache ONLY.


```php
$q = User::where( 'active', true );

app( UserRepository::class )->fromCache()->find( $q );

```


Also a model instance could be passed as parameter in order to retrieve that specific model from cache ONLY.


```php
app( UserRepository::class )->fromCache( $user );

```


### forget()

This method removes one or many models (or queries) from cache. It's very useful when you update a model and need cached queries or dependencies to be refreshed in real time.

The first parameter must be an instance of the model, a specific model ID (primary key) or a query builder instance (Illuminate\Database\Eloquent\Builder).


Forget query results:
```php
$query = User::where( 'active', true );

app( UserRepository::class )->forget( $query );

```

Forget a specific model using the object:
```php
app( UserRepository::class )->forget( $userModelInstance );

```

Forget a specific model by id:
```php
app( UserRepository::class )->forget( $user_id );

```

The second parameter (optional) could be an array to queue forget() operations in order to be done in a single request to the cache server. 

When passed the forget() method appends to the array (by reference) the removal operations instead of sending them instantly to the cache server.

It's useful when you need to expire many cached queries or models of the same repository. You can do it in one request optimizing response times for your cache server, therefore your app :)

For example:
```php
$user->active = false;
$user->save();

$forgets = [];

#removes user model from cache
app( UserRepository::class )->forget( $user, $forgets );

#removes query that finds active users
$query = User::where( 'active', true );
app( UserRepository::class )->forget( $query, $forgets );

#requests all queued removals to the cache server
app( UserRepository::class )->forget( $forgets );

```

<br>


Implementing Caching Strategies
-------------------------------

### Read-Aside Cache

<p align="center">
  <img alt="Read Aside Caching" src="https://github.com/krazydanny/laravel-repository/blob/master/read-aside-cache.png">
</p>

**How it works?**

1. The app first looks the desired model or query in the cache. If the data is found in cache, we’ve cache hit. The model or query results are read and returned to the client without database workload at all.
2. If model or query results were not found in cache we have a cache miss, then data is retrieved from database.
3. Model or query results retrived from database are stored in cache in order to have a successful cache hit next time.

**Use cases**

Works best for heavy read workload scenarios and general purpose.


**Usage**


When detecting you want a model or query to be remembered in cache for a certain period of time, Laravel Model Repository will automatically first try to retrieve it from cache. Otherwise will automatically retrieve it from database and store it in cache for the next time :)


Read-Aside a specific model by ID:
```php
$user = app( UserRepository::class )->remember()->during( 3600 )->get( $user_id );

```

Read-Aside query results:
```php
$q = User::where( 'active', true );

$userCollection = app( UserRepository::class )->remember()->during( 3600 )->find( $q );

$userCount = app( UserRepository::class )->remember()->during( 3600 )->count( $q );

$firstUser = app( UserRepository::class )->remember()->during( 3600 )->first( $q );

```


### Read-Through Cache

<p align="center">
  <img alt="Read Through Caching" src="https://github.com/krazydanny/laravel-repository/blob/master/read-through-cache.png">
</p>

**How it works?**

1. The app first looks the desired model or query in the cache. If the data is found in cache, we’ve cache hit. The model or query results are read and returned to the client without database workload at all.
2. If model or query results were not found in cache we have a cache miss, then data is retrieved from database ONLY THIS TIME in order to be always available from cache.


**Use cases**

Works best for heavy read workload scenarios where the same model or query is requested constantly.


**Usage**


When detecting you want a model or query to be remembered in cache forever, Laravel Model Repository will automatically first try to retrieve it from cache. Otherwise will automatically retrieve it from database and store it without expiration, so it will be always available form cache :)


Read-Through a specific model by ID:
```php
$user = app( UserRepository::class )->rememberForever()->get( $user_id );

```

Read-Through query results:
```php
$q = User::where( 'active', true );

$userCollection = app( UserRepository::class )->rememberForever()->find( $q );

$userCount = app( UserRepository::class )->rememberForever()->count( $q );

$firstUser = app( UserRepository::class )->rememberForever()->first( $q );

```


### Write-Through Cache

<p align="center">
  <img alt="Write Through Caching" src="https://github.com/krazydanny/laravel-repository/blob/master/write-through-cache.png">
</p>

**How it works?**

Models are always stored in cache and database.


**Use cases**

Used in scenarios where consistency is a priority or needs to be granted.


**Pros**

No cache invalidation techniques required. No need for using forget() method.

**Cons**

Could introduce write latency in some scenarios because data is always written in cache and database.


**Usage**

When detecting you want a model to be remembered in cache, Laravel Model Repository will automatically store it in cache and database (inserting or updating depending on the case).


Write-Through without expiration time:
```php
# create a new user in cache and database
$user = app( UserRepository::class )->rememberForever()->create([
	'firstname' => 'Krazy',
	'lastname'  => 'Danny',
	'email'	    => 'somecrazy@email.com',
	'active'    => true,
]);

# update an existing user in cache and database
$user->active = false;

app( UserRepository::class )->rememberForever()->save( $user );

```

Write-Through with expiration time (TTL):
```php
# create a new user in cache and database
$user = app( UserRepository::class )->remember()->during( 3600 )->create([
	'firstname' => 'Krazy',
	'lastname'  => 'Danny',
	'email'	    => 'somecrazy@email.com',
	'active'    => true,
]);

# update an existing user in cache and database
$user->active = false;

app( UserRepository::class )->remember()->during( 3600 )->save( $user );

```

### Write-Back Cache

<p align="center">
  <img alt="Write Back Caching" src="https://github.com/krazydanny/laravel-repository/blob/master/write-back-cache.png">
</p>

**How it works?**

Models are stored only in cache until they are massively persisted in database.


**Use cases**

Used in heavy write load scenarios and database-cache consistency is not a priority.


**Pros**

Resilient to database failures and downtimes 

**Cons**

In some cache failure scenarios data may be permanently lost.


**Usage**

*IMPORTANT!! THIS STRATEGY IS AVAILABLE FOR REDIS CACHE STORES ONLY (at the moment)*

With the log() method Laravel Model Repository will store data in cache untill you call the sync() method which will iterate many (batch) of cached models at once, alowing us to persist them the way our project needs through a callback function.


First write models in cache (ONLY):
```php
$model = app( SomeModelRepository::class )->log( new SomeModel( $data ) );

```

Then massively persist them in database:
```php
app( SomeModelRepository::class )->sync( 

    // the first param is a callback which returns true if models were persisted successfully, false otherwise
    function( $collection ) {
        
        foreach ( $collection as $model ) {
            // do database library custom and optimized logic here
            // for example: you could use bulk inserts and transactions in order to improve both performance and consistency
        }        

        if ( $result )
            return true; // if true remove model ids from sync queue
        
        return false; // if false keeps model ids in sync queue and tries again next time sync method is called
    },
    // the second param (optional) is an array with one or many of the following available options
    [
        'written_since' => 0, // process only models written since ths specified timestamp in seconds
        'written_until' => \time(), // process only models written until the given timestamp in seconds
        'object_limit'  => 500, // the object limit to be processed at the same time (to prevent memory overflows)
        'clean_cache'   => true, // if callback returns true, marks models as persisted
    ] 
);

```

<br>

Extending Laravel Model Repository
----------------------------------

### Pretty Queries

You can create human readable queries that represent your business logic in an intuititve way and ensures query criteria consistency encapsulating it's code.

For example:

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

	public function findByState ( string $state ) {

		return $this->find(
			User::where([
				'state'      => $state,
				'deleted_at' => null,
			])
		);
	}

}

```

Then call a pretty query :)

```php
$activeUsers = app( UserRepository::class )->findByState( 'active' );

$activeUsers = app( UserRepository::class )->remember()->during( 3600 )->findByState( 'active' );

$activeUsers = app( UserRepository::class )->rememberForever()->findByState( 'active' );

```

<br>

Some things I wish somebody told me before
------------------------------------------

### "Be shapeless, like water my friend" (Bruce Lee) 

There's no unique, best or does-it-all-right caching technique.

Every caching strategy has it's own advantages and disadvantages. Is up to you making a good analysis of what you project needs and it's priorities.

Even in the same project you may use different caching strategies for different models. For example: Is not the same caching millons of transaction logs everyday than registering a few new users in your app.

Also this library is designed to be implemented on the go. This means you can progressively apply caching techniques.

Lets say we currently have the following line in many places of our proyect:

```php
$model = SomeModel::create( $data );

```

Now assume we want to implement write-back strategy for that model only in some critical places of our proyect and see how it goes. Then we should only replace those specifice calls with:

```php
$model = app( SomeModelRepository::class )->log( new SomeModel( $data ) );

```

And leave those calls we want out of the caching strategy alone, they are not affected at all :)


<br>

Bibliography
------------

Here are some articles which talk in depth about caching strategies:

- https://bluzelle.com/blog/things-you-should-know-about-database-caching
- https://zubialevich.blogspot.com/2018/08/caching-strategies.html
- https://codeahoy.com/2017/08/11/caching-strategies-and-how-to-choose-the-right-one/
- https://docs.aws.amazon.com/AmazonElastiCache/latest/mem-ug/BestPractices.html
