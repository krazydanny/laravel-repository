<?php

namespace KrazyDanny\Laravel\Repository;

use Illuminate\Support\Str;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class BaseRepository implements RepositoryInterface {

    const OBJECT_LIMIT      = 300;

    protected $class;
    protected $cachePrefix;
    protected $model;

    protected $ttl          = 0;
    protected $fromCache    = false;
    protected $skip         = false;
    protected $take         = false;
    protected $observer     = null;

    protected static $observerClass  = null;


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


    static public function observe ( string $class ) {

        self::$observerClass = $class;
    }


    protected function fireObserverEvent (
        string $method,
        $result
    ) : bool
    {

        if ( self::$observerClass ) {

            if ( !$this->observer ) {

                $class = self::$observerClass;
                $this->observer = new $class();
            }

            $this->observer->$method( 
                $result 
            );

            return true;
        }

        return false;
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

            $data  = Cache::get(     
                $this->cachePrefix.':'.$id 
            );

            if ( $data ) {

                $class  = $this->class;
                $models = $class::hydrate([$data]);

                $this->clearSettings();

                $model = $models[0] ?? null;

                if ( $model ) {

                    $this->fireObserverEvent(
                        'retrievedFromCache',
                        $model
                    );
                }

                return $model;      
            }
            else if ( $this->fromCache ) {

                $this->clearSettings();

                return null;
            }
            else {

                $model = $this->class::find( $id );

                if ( $model ) {

                    $this->storeModelInCache( $model );

                    $this->fireObserverEvent(
                        'retrievedFromDatabase',
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

        $ttl = $this->ttl;

        $data = $this->query(
            $queryBuilder,
            function () use ( $queryBuilder, $ttl ) {

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

        if ( is_array($data) ) {

            $class = $this->class;

            $class::unguard();

            $models = $class::hydrate([$data]);

            $class::reguard();

            return $models[0] ?? null;
        }

        return $data;
    }


    protected function index ( Model $model ) {

        if ( Cache::getStore() instanceof RedisStore ) {

            Cache::getStore()->connection()->zadd(
                $this->cachePrefix.':index',
                \time(),
                $model->getKey()
            );
        }
    }


    public function log ( Model $model ) {

        if ( Cache::getStore() instanceof RedisStore ) {

            Cache::getStore()->connection()->zadd(
                $this->cachePrefix.':log',
                \time(),
                \serialize(
                    $model->toArray()
                )
            );
        }

        $this->clearSettings();        
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
        if ( !Cache::getStore() instanceof RedisStore ) {

            throw new \Exception(
                'Sync is only available for the following cache stores: Illuminate\Cache\RedisStore'
            );
        }

        $ids = Cache::getStore()->connection()->zrangebyscore(
            $this->cachePrefix.':index',
            $written_since,
            $written_until,
            [
                'LIMIT' => [ $skip, $take ],
            ]
        );

        if ( empty( $ids ) )
            return new Collection;

        $keys = [];

        foreach ( $ids as $id ) {

            $keys[] = $this->cachePrefix.':'.$id;
        }

        unset( $ids );

        $data =  \call_user_func_array( 
            [ $redis, "mget" ], 
            $keys
        );

        unset( $keys );

        return $this->unserializeMulti( $data );
    }


    protected function getModelsFromLog ( 
        int $written_since,
        int $written_until,
        int $skip,
        int $take
    ) : Collection
    {
        if ( !Cache::getStore() instanceof RedisStore ) {

            throw new \Exception(
                'Sync is only available for the following cache stores: Illuminate\Cache\RedisStore'
            );
        }

        return $this->unserializeMulti(
            Cache::getStore()->connection()->zrangebyscore(
                $this->cachePrefix.':log',
                $written_since,
                $written_until,
                [
                    'LIMIT' => [ $skip, $take ],
                ]
            )
        );
    }    


    protected function cleanIndex ( 
        int $written_since,
        int $written_until,
        bool $forget = true
    )
    {
        $redis = Cache::getStore()->connection();

        if ( $forget ) {

            \call_user_func_array( 
                [ $redis, "unlink" ], 
                $keys
            );
        }

        return $redis->zremrangebyscore(
            $this->cachePrefix.':index',
            $written_since,
            $written_until
        );
    }


    protected function cleanLog ( 
        int $written_since,
        int $written_until
    )
    {

        return Cache::getStore()->connection()->zremrangebyscore(
            $this->cachePrefix.':index',
            $written_since,
            $written_until
        );
    }    


    public function find ( 
        Builder $queryBuilder 
    ) : Collection 
    {
        $ttl  = $this->ttl;

        $data = $this->query(
            $queryBuilder,
            function () use ( $queryBuilder, $ttl ) {

                if ( $ttl == 0 ) {
                    return $queryBuilder->get();
                }

                return $queryBuilder->get()->toArray();
            },
            $this->generateQueryCacheKey(
                $queryBuilder
            )            
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

        return $this->query(
            $queryBuilder,
            function () use ( $queryBuilder ) {

                return $queryBuilder->get()->count();
            },
            $this->generateQueryCacheKey(
                $queryBuilder
            ).':count'
        );
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

        $store = Cache::getStore();

        if ( $store instanceof RedisStore ) {

            return \call_user_func_array( 
                [ $store->connection(), "unlink" ], 
                $bulk
            );
        }
        else {

            foreach ( $bulk as $key ) {

                Cache::forget( $key );
            }

            return true;
        }

        return false;
    }


    protected function getModelsForSync ( 
        string $source,
        int $written_since,
        int $written_until,
        int $skip,
        int $take        
    ) {

        switch ( $source ) {

            case 'log':
                return $this->getModelsFromLog(
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
                    'Unsupported option, value must be log or index'
                );
        }
    }    


    public function sync ( 
        \Closure $callback,
        array $options = []
    ) : bool 
    {
        $written_since = $options['written_since'] ?? 0;
        $written_until = $options['written_until'] ?? \time();
        $object_limit  = $options['object_limit'] ?? self::OBJECT_LIMIT;

        $source = $options['source'] ?? 'log';

        $skip = 0;
        $take = $object_limit;

        $models = $this->getModelsForSync(
            $source,
            $written_since,
            $written_until,
            $skip,
            $take
        );

        $r = true;

        while ( $models->isEmpty() ) {

            if ( !$callback( $models ) ) {

                $r = false;
                break;
            }

            $skip += $object_limit;
            $take += $object_limit;

            $models = $this->getModelsForSync(
                $source,
                $written_since,
                $written_until,
                $skip,
                $take
            );
        }

        if ( 
            $r
            && $options['clean'] ?? false 
        ) {

            $this->cleanAfterSync(
                $using,
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

            return Cache::get( $key );            
        }
        else if ( $this->ttl < 0 ) {

            $this->clearSettings();

            return Cache::rememberForever( 
                $key, 
                $callback
            );
        }
        else if ( $this->ttl > 0 ) {

            $r = Cache::remember(                 
                $key,
                $this->ttl,
                $callback
            );

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

            return Cache::forever(     
                $key, 
                $value
            );
        }
        else if ( $this->ttl > 0 ) {

            return Cache::put(     
                $key, 
                $value, 
                $this->ttl
            );
        }

        return false;
    }


    protected function cleanAfterSync ( 
        string $source,
        int $written_since,
        int $written_until
    ) {

        switch ( $source ) {

            case 'log':
                return $this->cleanLog(
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
                    'Unsupported option, value must be log or index'
                );
        }
    }


    public function handleDatabaseException ( 
        \Closure $callback
    ){
        $this->dbHandler = $callback;
    }


    public function handleCacheStoreException ( 
        \Closure $callback
    ){
        $this->cacheHandler = $callback;
    }

}