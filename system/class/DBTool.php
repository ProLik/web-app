<?php
/**
 * Created by PhpStorm.
 * User: LIKANG
 * Date: 2017/12/8
 * Time: 16:00
 */

class DBTool
{
    public $pdo_list;
    private $memcache = null;
    private $redis = null;
    private $redis_cluster = null;
    private $mongo_client_list = array();
    public $cache;

    const CACHE_TYPE_MEMCACHE = 1;
    const CACHE_TYPE_REDIS = 2;
    const CACHE_TYPE_REDIS_CLUSTER = 3;//redis 集群

    private static $instance;

    public function __construct()
    {
        //设置用户自定义的错误处理函数
        set_error_handler("cf_error_handler");
        cf_require_class("ConfigTool");
        cf_require_class("DebugTool");
        DebugTool::get_instance()->debug("DbTool loaded");
    }

    public static function get_instance(){
        if(!self::$instance){
            self::$instance = new DBTool();
        }
        return self::$instance;
    }

    public function get_pdo($db_config)
    {
        $key = md5($db_config["db"] . $db_config['host'] . $db_config['port'] . $db_config['user']);

        if($this->pdo_list[$key]){
            return $this->pdo_list[$key];
        }

        $db_string = 'mysql:host=' . $db_config['host'] . ';port=' . $db_config['port'] . ';dbname=' . $db_config['db'];
        DebugTool::get_instance()->debug($db_string);
        try {
            $pdo  =  new PDO($db_string, $db_config['user'], $db_config['pass']);
        } catch  (PDOException $e) {
            print "Error!: " . $e->getMessage() . "<br/>";
            die();
        }
        $pdo->exec("SET CHARACTER SET utf8");
        $this->pdo_list[$key] = $pdo;
        return $pdo;
    }


    public function get_memcache()
    {
        cf_require_class("MyMemcache");
        if($this->memcache){
            return $this->memcache;
        }

        $this->memcache = new MyMemcache();
        $domain = ConfigTool::get_instance()->get_config("memcache", "database");

        $domains = explode(":", $domain);
        $this->memcache->addServer($domains[0], intval($domain[1]));
        return $this->memcache;
    }


    public function get_redis()
    {
        cf_require_class("MyRedis");
        return $this->redis ? $this->redis : new MyRedis();
    }

    public function get_redis_cluster() {
        cf_require_class("RedisClusterCache");
        return $this->redis_cluster ? $this->redis_cluster : new RedisClusterCache();
    }

    public function get_cache()
    {
        $cache_type = ConfigTool::get_instance()->get_config("cache_type");
        switch ($cache_type){
            case self::CACHE_TYPE_MEMCACHE:
                $this->cache = $this->get_memcache();
                break;
            case self::CACHE_TYPE_REDIS:
                $this->cache = $this->get_redis();
                break;
            case self::CACHE_TYPE_REDIS_CLUSTER:
                $this->cache = $this->get_redis_cluster();
                break;
        }

    }

    public function get_solr()
    {
        $solr_config = ConfigTool::get_instance()->get_config('solr', "database");
        $solr_client = new SolrClient(array(
            'hostname'=>$solr_config['hostname'],
            'port'=>$solr_config['port']
        ));
        $solr_client->setServlet(SolrClient::SEARCH_SERVLET_TYPE,$solr_config['db'].'/select');
        return $solr_client;
    }

    public function get_mongo($db) {
        $mongodb_config_list = ConfigTool::get_instance()->get_config("mongodb", "database");
        $uri = $mongodb_config_list[$db]['uri'];
        if(!$this->mongo_client_list[$uri]) {
            $this->mongo_client_list[$uri]  = new MongoDB\Driver\Manager($uri,array(
                'readPreference'=>'secondary'
            ));
        }
        return $this->mongo_client_list[$uri];
    }

}