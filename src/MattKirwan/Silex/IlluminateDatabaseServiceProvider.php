<?php

namespace MattKirwan\Silex;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Capsule\Manager as Capsule;

class IlluminateDatabaseServiceProvider implements ServiceProviderInterface
{
	/**
	 * Register the Illuminate Database service
	 *
	 * @param Silex\Application
	 */
	public function register(Application $app)
	{
		$app['db.connection_defaults'] = array(
			'driver' => 'mysql',
			'host' => 'localhost',
			'database' => 'test',
			'username' => 'root',
			'password' => 'root',
			'charset' => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix' => null,
		);

		$app['db.boot'] = true;
		$app['db.global'] = true;

		$app['db.container'] = $app->share(function() {
			return new Container;
		});

		$app['db.dispatcher'] = $app->share(function() use ($app) {
			return new Dispatcher($app['db.container']);
		});

		if(class_exists('\Illuminate\Cache\CacheManager'))
		{
			$app['db.cache_manager'] = $app->share(function() use ($app) {
				return new \Illuminate\Cache\CacheManager($app['db.container']);
			});
		}

		$app['db.cache'] = array(
			'driver' => 'apc',
			'prefix' => 'laravel',			
		);

		$app['db'] = $app->share(function() use ($app) {

			$db = new Capsule($app['db.container']);

			$db->setEventDispatcher($app['db.dispatcher']);

			if (isset($app['db.cache_manager']) && isset($app['db.cache']))
			{
				$db->setCacheManager($app['db.cache_manager']);
			 
				foreach ($app['db.cache'] as $key => $value)
				{
					$app['db.container']->offsetGet('config')->offsetSet('cache.' . $key, $value);
				}
			}

			if($app['db.global'])
			{
				$db->setAsGlobal();
			}

			if($app['db.boot'])
			{
				$db->bootEloquent();
			}					

			if(!isset($app['db.connections']))
			{
				$connection = array();

				if(isset($app['db.connection']))
				{
					$connection = $app['db.connection'];
				}

				$app['db.connections'] = array(
					'default' => $connection
				);
			}

			foreach($app['db.connections'] as $connection => $options)
			{
				$db->addConnection(array_replace($app['db.connection_defaults'], $options), $connection);
			}			

			if(!$app['debug'])
			{
				$db->connection()->disableQueryLog();
			}
 
			return $db;

		});
	}

	/**
	 * Register the Illuminate Database service
	 *
	 * @param Silex\Application
	 */
	public function boot(Application $app)
	{
		if($app['db.boot'])
		{
			$app->before(function() use($app) {
				$app['db'];
			}, Application::EARLY_EVENT);
		}
	}
}