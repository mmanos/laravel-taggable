<?php namespace Mmanos\Taggable;

use Illuminate\Support\ServiceProvider;

class TaggableServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;
	
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('mmanos/laravel-taggable');
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('command.laravel-taggable.tags', function ($app) {
			return new TagsCommand;
		});
		$this->commands('command.laravel-taggable.tags');
		
		$this->app->bindShared('command.laravel-taggable.taggable', function ($app) {
			return new TaggableCommand;
		});
		$this->commands('command.laravel-taggable.taggable');
	}
	
	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}
}
