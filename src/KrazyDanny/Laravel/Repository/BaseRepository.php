<?php

namespace KrazyDanny\Laravel\Repository;

use Illuminate\Support\Str;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class BaseRepository implements RepositoryInterface {

    const OBJECT_LIMIT       = 300;

    protected $class;
    protected $cachePrefix;
    protected $model;

    protected $ttl           = 0;
    protected $fromCache     = false;
    protected $skip          = false;
    protected $take          = false;
    protected $observer      = null;
    protected $observerClass = null;
    protected $dbHandler     = null;
    protected $cacheHandler  = null;
    protected $mute          = false;


    public function __construct ( 
        string $class, 
        string $cache_prefix = null
    ) 
    {
        $this->class = $class;

        if ( $cache_prefix ) {

            $this->cachePrefix = $cache_prefix;
        }
        else {

            $this->detectCachePrefix();
        }
    }


    protected function detectCachePrefix ( ) {

        $parts = \explode( '\\', $this->class );

        $this->cachePrefix = \end(
            $parts    
        );        
    }


    public function observe ( string $class ) {

        $this->observerClass = $class;
    }


    protected function fireObserverEvent (
        string $method,
        $result
    ) : bool
    {

        if ( $this->observerClass ) {

            if ( !$this->observer ) {

                $class = $this->observerClass;
                $this->observer = new $class();
            }

            $this->observer->$method( 
                $result 
            );

            return true;
        }

        return false;
    }


    public function silently ( ) {

        $this->mute = true;
    }


    public function remember ( Model $model = null ) {

        $this->model = $model;

        return $this;
    }


    public function during ( int $seconds ) {

        $this->ttl = $seconds;

        if ( $this->model ) {

            $r = $this->storeModelInCache(
                $this->model
            );

            $this->clearSettings();            

            return $r;
        }

        return $this;
    }    



    public function fromCache ( ) {

        $this->fromCache = true;
        return $this;
    }


    public function rememberForever ( 
        Model $model = null 
    ) 
    {

        $this->ttl = -1;

        if ( $model ) {

            $this->index( $model );

            $r = $this->storeModelInCache( $model );

            $this->clearSettings();            

            return $r;
        }

        return $this;
    }


    public function skip ( int $entities ) {

        $this->skip = $entities;
        return $this;
    }


    public function take ( int $entities ) {

        $this->take = $entities;
        return $this;
    }


    public function get ( $id ) : ?Model {

        if ( !$id ) 
            return null;

        if ( $this->ttl != 0 || $this->fromCache ) {

            try {

                $data  = Cache::get(     
                    $this->cachePrefix.':'.$id 
                );                
            }
            catch ( \Exception $e ) {

                $data  = null;
                $this->handleCacheException( $e );
            }

            if ( $data ) {

                $class  = $this->class;
                $models = $class::hydrate([$data]);

                $this->clearSettings();

                $model = $models[0] ?? null;

                if ( $model ) {

                    $this->detectObserverEvent( 
                        true, 
                        $model 
                    );
                }

                return $model;  
            }
            else if ( $this->fromCache ) {

                $this->clearSettings();

                $this->detectObserverEvent( 
                    false, 
                    null 
                );

                return null;
            }
            else {

                $model = $this->class::find( $id );

                if ( $model ) {

                    $this->storeModelInCache( $model );

                    $this->detectObserverEvent( 
                        false, 
                        $model 
                    );
                }

                $this->clearSettings();

                return $model;              
            }
        }

        $this->clearSettings();

        return $this->class::find( $id );
    }


    protected function clearSettings () {

        $this->ttl       = 0;
        $this->take      = false;
        $this->skip      = false;
        $this->fromCache = false;
        $this->mute      = false;
    }


    public function create ( array $attributes ) : ?Model {

        if ( empty( $attributes ) ) {

            $this->clearSettings();
            return null;
        }

        $model = $this->class::create( $attributes );

        if ( $model ) {

            if ( $this->ttl < 0 )
                $this->index( $model );

            $this->storeModelInCache( $model );    
        }

        $this->clearSettings();

        return $model;
    }


    public function save ( Model $model ) : bool {

        if ( $model->save() ) {

            if ( $this->ttl < 0 )
                $this->index( $model );

            $this->storeModelInCache( $model );
            $this->clearSettings();

            return true;
        }

        $this->clearSettings();

        return false;   
    }


    public function delete ( Model $model ) : bool {

        if ( $this->fromCache ) {

            $this->forget( $model );

            $this->clearSettings();

            return true;
        }
        else if ( $model->delete() ) {

            $this->forget( $model );

            $this->clearSettings();

            return true;
        }

        return false;
    }


    public function first ( 
        Builder $queryBuilder 
    ) : ?Model 
    {

        $ttl  = $this->ttl;
        $hit  = true;

        $data = $this->query(
            $queryBuilder,
            function () use ( $queryBuilder, $ttl, $hit ) {

                $hit   = false;
                $model = $queryBuilder->first();

                if ( $model ) {

                    if ( $ttl == 0 )
                        return $model;
                    
                    return $model->toArray();    
                }

                return null;
            },
            $this->generateQueryCacheKey(
                $queryBuilder
            ).':first'
        );

        $this->detectObserverEvent( 
            $hit, 
            $data
        );

        if ( is_array($data) ) {

            $class = $this->class;

            $class::unguard();

            $models = $class::hydrate([$data]);

            $class::reguard();

            return $models[0] ?? null;
        }

        return $data;
    }


    public function index ( Model $model ) {

        try {

            $store = Cache::getStore();
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }  

        if ( !$store instanceof RedisStore ) {

            throw new \Exception(
                'buffer() is only available for the following cache stores: Illuminate\Cache\RedisStore'
            );
        }

        try {

            $store->connection()->zadd(
                $this->cachePrefix.':index',
                \time(),
                $model->getKey()
            );
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }   

    }


    public function buffer ( Model $model ) {

        try {

            $store = Cache::getStore();
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }  

        if ( !$store instanceof RedisStore ) {

            throw new \Exception(
                'buffer() is only available for the following cache stores: Illuminate\Cache\RedisStore'
            );
        }

        try {

            $store->connection()->zadd(
                $this->cachePrefix.':buffer',
                \time(),
                \serialize(
                    $model->toArray()
                )
            );
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }
    }


    protected function unserializeMulti (
        array $data
    ) : Collection
    {
        if ( !$data ) 
            return new Collection;

        $attributes = [];

        foreach ( $data as $d ) {

            if ( $d ) {
                $attributes[] = \unserialize( $d );
            }
        }

        unset( $data );

        return $this->class::hydrate( $attributes );
    }


    protected function getIndexedModels ( 
        int $written_since,
        int $written_until,
        int $skip,
        int $take
    ) : Collection
    {
        try {

            $store = Cache::getStore();
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }  

        if ( !$store instanceof RedisStore ) {

            throw new \Exception(
                'persist() is only available for the following cache stores: Illuminate\Cache\RedisStore'
            );
        }

        try {

            $ids = $store->connection()->zrangebyscore(
                $this->cachePrefix.':index',
                $written_since,
                $written_until,
                [
                    'LIMIT' => [ $skip, $take ],
                ]
            );            
        }
        catch ( \Exception $e ) {

            $ids = [];

            $this->handleCacheException( $e );
        }  

        if ( empty( $ids ) )
            return new Collection;

        $keys = [];

        foreach ( $ids as $id ) {

            $keys[] = $this->cachePrefix.':'.$id;
        }

        unset( $ids );

        try {

            $data =  \call_user_func_array( 
                [ $store, "mget" ], 
                $keys
            );            
        }
        catch ( \Exception $e ) {

            $data = [];

            $this->handleCacheException( $e );
        }

        unset( $keys );

        return $this->unserializeMulti( $data );
    }


    protected function getModelsFromBuffer ( 
        int $written_since,
        int $written_until,
        int $skip,
        int $take
    ) : Collection
    {
        try {

            $store = Cache::getStore();
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }  

        if ( !$store instanceof RedisStore ) {

            throw new \Exception(
                'persist() is only available for the following cache stores: Illuminate\Cache\RedisStore'
            );
        }

        try {

            $result = $store->connection()->zrangebyscore(
                $this->cachePrefix.':buffer',
                $written_since,
                $written_until,
                [
                    'LIMIT' => [ $skip, $take ],
                ]
            );
        }
        catch ( \Exception $e ) {

            $result = [];

            $this->handleCacheException( $e );
        }  

        return $this->unserializeMulti( $result );
    }    


    protected function cleanIndex ( 
        int $written_since,
        int $written_until,
        bool $forget = true
    )
    {
        try {

            $store = Cache::getStore()->connection();

            if ( $forget ) {

                \call_user_func_array( 
                    [ $store, "unlink" ], 
                    $keys
                );
            }

            return $store->zremrangebyscore(
                $this->cachePrefix.':index',
                $written_since,
                $written_until
            );
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }

        return false;
    }


    protected function cleanBuffer ( 
        int $written_since,
        int $written_until
    )
    {
        try {

            return Cache::getStore()->connection()->zremrangebyscore(
                $this->cachePrefix.':index',
                $written_since,
                $written_until
            );
        }
        catch ( \Exception $e ) {

            $this->handleCacheException( $e );
        }

        return false;
    }    


    public function find ( 
        Builder $queryBuilder 
    ) : Collection 
    {
        $ttl  = $this->ttl;
        $hit  = true;

        $data = $this->query(
            $queryBuilder,
            function () use ( $queryBuilder, $ttl, $hit ) {

                $hit = false;

                if ( $ttl == 0 ) {
                    return $queryBuilder->get();
                }

                return $queryBuilder->get()->toArray();
            },
            $this->generateQueryCacheKey(
                $queryBuilder
            )            
        );

        $this->detectObserverEvent( 
            $hit, 
            $data 
        );

        if ( $data instanceof Collection )
            return $data;

        if ( !$data )
            $data = [];

        $class = $this->class;
        $class::unguard();

        $models = $class::hydrate($data);

        $class::reguard();

        return $models;
    }


    public function count ( 
        Builder $queryBuilder 
    ) : int 
    {
        $hit = true;

        $c   = $this->query(
            $queryBuilder,
            function () use ( $queryBuilder, $hit ) {

                return $queryBuilder->get()->count();
            },
            $this->generateQueryCacheKey(
                $queryBuilder
            ).':count'
        );

        $this->detectObserverEvent( 
            $hit, 
            $c
        );

        return $c;    
    }


    public function generateQueryCacheKey ( 
        Builder $queryBuilder 
    ) : string 
    {
        return $this->cachePrefix.':q:'.$queryBuilder->getQuery()->generateCacheKey();
    }   


    protected function storeModelInCache ( 
        Model $model
    ) {

        return $this->writeToCache(

            $this->generateUnitCacheKey( 
                $model 
            ),
            $model->toArray()           
        );
    }


    public function generateUnitCacheKey ( 
        Model $model 
    ) : string 
    {

        return $this->cachePrefix.':'.$model->getKey();
    }


    public function detectCacheKey ( $value ) {

        if ( $value instanceof Model ) {

            return $this->cachePrefix.':'.$value->getKey();
        }
        else if ( $value instanceof Builder ) {

            return $this->generateQueryCacheKey( $value );
        }
        else if ( 
            Str::startsWith(
                $value, 
                $this->cachePrefix.':' 
            )
        ) {
            return $value;
        }
        else {

            return $this->cachePrefix.':'.$value;
        }
    }


    public function forget ( 
        $id,
        array &$bulk = null
    ) 
    {

        $this->clearSettings();

        if ( isset($bulk) ) {

            $prevBulk = true;
        }
        else {

            $prevBulk = false;
            $bulk     = [];
        }

        if ( 
            \is_array( $id )
            || $id instanceof Collection
        ) {

            foreach ( $id as $i ) {

                $key = $this->detectCacheKey( $i );

                $bulk[] = $key;

                if ( $i instanceof Builder ) {

                    $bulk[] = $key.':first';
                    $bulk[] = $key.':count';
                }
            }
        }
        else {

            $key = $this->detectCacheKey( $id );

            $bulk[] = $key;

            if ( $id instanceof Builder ) {

                $bulk[] = $key.':first';
                $bulk[] = $key.':count';
            }
        }

        if ( $prevBulk )
            return $bulk;

        if ( empty($bulk) )
            return false;        

        try {

            $store = Cache::getStore();
        }
        catch ( \Exception $e ) {

            $store = false;

            $this->handleCacheException( $e );
        }        

        if ( $store instanceof RedisStore ) {

            try {

                return \call_user_func_array( 
                    [ $store->connection(), "unlink" ], 
                    $bulk
                );
            }
            catch ( \Exception $e ) {

                $this->handleCacheException( $e );
            }            
        }
        else {

            foreach ( $bulk as $key ) {

                try {

                    Cache::forget( $key );
                }
                catch ( \Exception $e ) {

                    $this->handleCacheException( $e );
                }
            }

            return true;
        }

        return false;
    }


    protected function getModelsToPersist ( 
        string $source,
        int $written_since,
        int $written_until,
        int $skip,
        int $take        
    ) {

        switch ( $source ) {

            case 'buffer':
                return $this->getModelsFromBuffer(
                    $written_since,
                    $written_until,
                    $skip,
                    $take                    
                );

            case 'index':
                return $this->getIndexedModels(
                    $written_since,
                    $written_until,
                    $skip,
                    $take                    
                );

            default:
                throw new \Exception(
                    'Unsupported option, value must be buffer or index'
                );
        }
    }    


    public function persist ( 
        \Closure $callback,
        array $options = []
    ) : bool 
    {
        $written_since = $options['written_since'] ?? 0;
        $written_until = $options['written_until'] ?? \time();
        $object_limit  = $options['object_limit'] ?? self::OBJECT_LIMIT;

        $source = $options['method'] ?? 'buffer';

        $skip = 0;
        $take = $object_limit;

        $models = $this->getModelsToPersist(
            $source,
            $written_since,
            $written_until,
            $skip,
            $take
        );

        $r = true;

        while ( !$models->isEmpty() ) {

            if ( !$callback( $models ) ) {

                $r = false;
                break;
            }

            $skip += $object_limit;
            $take += $object_limit;

            $models = $this->getModelsToPersist(
                $source,
                $written_since,
                $written_until,
                $skip,
                $take
            );
        }

        if ( 
            $r
            && $options['clean_cache'] ?? true 
        ) {

            $this->cleanAfterPersist(
                $source,
                $written_since,
                $written_until
            );
        }

        return $r;
    }


    protected function query (
        Builder $queryBuilder,
        \Closure $callback,
        string $key
    ) 
    {
        if ( $this->skip ) {

            $queryBuilder->skip( $this->skip );
        }

        if ( $this->take ) {

            $queryBuilder->take( $this->take );
        } 

        if ( $this->fromCache ) {

            $this->clearSettings();

            try {

                return Cache::get( $key );            
            }
            catch ( \Exception $e ) {

                return null;

                $this->handleCacheException( $e );
            }            
        }
        else if ( $this->ttl < 0 ) {

            $this->clearSettings();

            try {

                return Cache::rememberForever( 
                    $key, 
                    $callback
                );
            }
            catch ( \Exception $e ) {

                return null;

                $this->handleCacheException( $e );
            }            
        }
        else if ( $this->ttl > 0 ) {

            try {

                $r = Cache::remember(                 
                    $key,
                    $this->ttl,
                    $callback
                );
            }
            catch ( \Exception $e ) {

                $r = null;
                $this->handleCacheException( $e );
            }

            $this->clearSettings();

            return $r;
        }
        else {

            $this->clearSettings();

            return $callback();
        }
    }


    protected function writeToCache ( 
        string $key,
        $value
    )
    {
        if ( $this->ttl < 0 ) {

            try {

                return Cache::forever(     
                    $key, 
                    $value
                );
            }
            catch ( \Exception $e ) {

                $this->handleCacheException( $e );
            }
        }
        else if ( $this->ttl > 0 ) {

            try {

                return Cache::put(     
                    $key, 
                    $value, 
                    $this->ttl
                );
            }
            catch ( \Exception $e ) {

                $this->handleCacheException( $e );
            }
        }

        return false;
    }


    protected function cleanAfterPersist ( 
        string $source,
        int $written_since,
        int $written_until
    ) {

        switch ( $source ) {

            case 'buffer':
                return $this->cleanBuffer(
                    $written_since,
                    $written_until                  
                );

            case 'index':
                return $this->cleanIndex(
                    $written_since,
                    $written_until,                  
                );

            default:
                throw new \Exception(
                    'Unsupported option, value must be buffer or index'
                );
        }
    }


    protected function handleDbException ( \Exception $e ) {

        if ( $this->dbHandler ) {

            $callback = $this->dbHandler;

            return $callback( $e );
        }

        if ( !$this->mute )
            throw $e;
    }


    protected function handleCacheException ( \Exception $e ) {

        if ( $this->cacheHandler ) {

            $callback = $this->cacheHandler;

            return $callback( $e );
        }

        if ( !$this->mute )
            throw $e;
    }


    public function handleDatabaseExceptions ( 
        \Closure $callback      
    ){
        $this->dbHandler = $callback;
    }


    public function handleCacheStoreExceptions ( 
        \Closure $callback
    ){
        $this->cacheHandler = $callback;
    }


    protected function detectObserverEvent ( 
        bool $dbHit,
        $mixed
    ) {

        if ( $dbHit ) {

            $this->fireObserverEvent( 'cacheMiss', $mixed );
        }
        else if ( $mixed ) {

            $this->fireObserverEvent( 'cacheHit', $mixed );
        }
    }

}