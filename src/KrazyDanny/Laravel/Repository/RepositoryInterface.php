<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;


interface RepositoryInterface {


	// remembers model or query in cache for a certain period of time, must be called before during(). 
	// If model is passed remembers an specific model instance, otherwise the during() method must be followed by find(), skip(), take(), first() or count(). 
	// Returns $this
	public function remember ( 
		Model $model = null
	); // return $this;


	// sets the amount of seconds of a model or query to be remembered.
	// If a model instance has been passed to ->remember() then stores model in cache and returns true, otherwise returns $this and must be followed by one of the following methods get(), find(), create(), count(), first(), delete(), skip(), take() or save() 
	public function during ( int $seconds );


	// remembers object or queries in cache forever. 
	// If model is passed returns boolean, otherwise returns $this
	// Write-Aside Strategy
	public function rememberForever ( 
		Model $model = null
	);


	// retrieves object or queries directly and only from cache.
	// Force Read-Aside Strategy
	public function fromCache ( ); // return $this;


	// retrieves a specific object.
	// Read-Through Strategy
	public function get ( $id ) : ?Model;

	// inserts a specific object.
	// Write-Through Strategy
	public function create ( array $attributes ) : ?Model;

	// inserts or updates a specific object.
	// Write-Through Strategy
	public function save ( Model $model ) : bool;
	
	// deletes a specific object.
	// Write-Through Strategy
	public function delete ( Model $model ) : bool;	

	// returns first matched object.
	// Read-Through Strategy
	public function first ( Builder $queryBuilder ) : ?Model;

	// returns all matched objects.
	// Read-Through Strategy
	public function find ( Builder $queryBuilder ) : Collection;

	// returns the number of matched object.
	// Read-Through Strategy
	public function count ( Builder $queryBuilder ) : int;


	// sets the amount of entities to be retrieved using find() method
    public function take ( int $entities ); // return $this;
 	

 	// sets the amount of entities to be skiped from the begining of the result
    public function skip ( int $entities ); // return $this;


    // removes a specific object from cache only
	public function forget ( 
		$id,
		array &$bulk = null
	);


	// saves complete model's data in a buffer in order to be processed by persist later
	public function buffer ( Model $model );


	// saves model's id in an index to be processed by persist later. NOTE: during persist model data will be retrieved from the model's specific cache key
	public function index ( Model $model );	


    // runs a callback for all models stored in cache using ->rememberForever( $model ) method allowing to persist them in database or any other processing (Write-Back Strategy)
    // options:
    /*
    	[
    		'written_since' => 0,
    		'written_until' => \time(),
    		'object_limit'	=> 1000,
    		'clean_cache'	=> true,
    		'source'		=> 'buffer' | 'index',
    	]
    */
	public function persist ( 
        \Closure $callback,
        array $options = []
    ) : bool;


    public function handleDatabaseExceptions ( 
        \Closure $callback
    );


	public function handleCacheStoreExceptions ( 
        \Closure $callback
    );


    public function observe ( string $class );


    public function silently ( );

}