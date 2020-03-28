Laravel Repository
===============

[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://paypal.me/danielspadafora)

This package provides an abstraction layer for implementing industry-standard caching strategies for Eloquent models


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
