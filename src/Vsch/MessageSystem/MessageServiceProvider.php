<?php namespace Vsch\MessageSystem;

use Illuminate\Support\ServiceProvider;

class MessageServiceProvider extends ServiceProvider
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
    public
    function boot()
    {
        $this->package('vsch/message-system');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public
    function register()
    {
        $this->app->bind('Vsch\MessageSystem\MessageRepository',
            function ($app)
            {
                $config = $app['config']['message-system::config'];
                $db = $app->make('Illuminate\Database\DatabaseManager');
                return new MessageRepository($config, $db);
            });

        $this->app['msg'] = $this->app->share(function ($app)
        {
            return new MessageSystem(
                $app['Vsch\MessageSystem\MessageRepository'],
                $app['events'] //Illuminate\Events\Dispatcher
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public
    function provides()
    {
        return array();
    }
}
