<?php

namespace KrazyDanny\Laravel\Repository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*

----------------------
READ STRATEGIES 
----------------------

No-Cache:

$repository->get( $id );
$repository->find( $queryBuilder );
$repository->count( $queryBuilder );
$repository->first( $queryBuilder );

----------------------

Cache-Only:

$repository->fromCache()->get( $id );
$repository->fromCache()->find( $queryBuilder );
$repository->fromCache()->count( $queryBuilder );
$repository->fromCache()->first( $queryBuilder );

----------------------

Cache-Aside:
( get from db if not in cache )

$repository->remember()->during( $ttl )->get( $id );
$repository->remember()->during( $ttl )->find( $queryBuilder );
$repository->remember()->during( $ttl )->count( $queryBuilder );
$repository->remember()->during( $ttl )->first( $queryBuilder );

----------------------

Read-Through:
( get from db only the first time )

$repository->rememberForever()->get( $id );
$repository->rememberForever()->find( $queryBuilder );
$repository->rememberForever()->count( $queryBuilder );
$repository->rememberForever()->first( $queryBuilder );


----------------------
WRITE STRATEGIES 
----------------------

Write-Around:
( avoids caching when writing )

$repository->save( $model );

----------------------

Write-Back / Write Behind Caching:
( saves only in cache when writing, then sync with db on schedule )

$repository->rememberForever( $model );
$repository->sync( 
    function( $collection ) {
        
        foreach ( $collection as $model ) {
            // do database engine specific logic here
        }        

        if ( $ok )
            return true; // if true remove model ids from sync queue
        
        return false; // if false keeps model ids in sync queue and tries again next time sync method is called
    },
    [
    ] 
);

----------------------

Write-Through:
( saves in db and in cache )

$repository->rememberForever()->save( $model );

----------------------

Write-Through + ttl / Eviction Policy:
( saves in db and in cache with expiration ):

$repository->remember()->during( $ttl )->save( $model );

*/

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


	// logs model's current state in order to be processed by sync()
	public function log ( Model $model );


    // runs a callback for all models stored in cache using ->rememberForever( $model ) method (Write-Aside Strategy) allowing to persist them in database or any other processing
    // options:
    /*
    	[
    		'written_since' => 0,
    		'written_until' => \time(),
    		'object_limit'	=> 1000,
    		'clean_cache'	=> true,
    		'source'		=> 'log' | 'index',
    	]
    */
	public function sync ( 
        \Closure $callback,
        array $options = []
    ) : bool;


    public function handleDatabaseException ( 
        \Closure $callback
    );


	public function handleCacheStoreException ( 
        \Closure $callback
    );

}