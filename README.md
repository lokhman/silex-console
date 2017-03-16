# silex-console

[![StyleCI](https://styleci.io/repos/79904121/shield?branch=master)](https://styleci.io/repos/79904121)

Console application for [**Silex 2.0+**](http://silex.sensiolabs.org/) micro-framework.

> This project is a part of [`silex-tools`](https://github.com/lokhman/silex-tools) library.

## <a name="installation"></a>Installation
You can install `silex-console` with [Composer](http://getcomposer.org):

    composer require lokhman/silex-console

## <a name="documentation"></a>Documentation
A wrapper class for [Symfony Console](https://github.com/symfony/console) application that registers console commands
and service providers.

    #!/usr/bin/env php
    require __DIR__ . '/../vendor/autoload.php';

    use Silex\Application;
    use Silex\Provider as Providers;
    use Lokhman\Silex\Console\Console;
    use Lokhman\Silex\Console\Command as Commands;
    use Lokhman\Silex\Console\Provider as Providers;

    $app = new Application();
    $app->register(new Providers\DoctrineServiceProvider());

    $console = new Console($app);

    // add console command
    $console->add(new Commands\Session\SchemaCreateCommand());

    // register console service providers
    $console->registerServiceProvider(new Providers\DoctrineServiceProvider());
    $console->registerServiceProvider(new Providers\DoctrineMigrationsServiceProvider(), [
        'migrations.directory' => __DIR__ . '/../app/migrations',
        'migrations.namespace' => 'Project\Migrations',
    ]);

    $console->run();

Console supports [`ConfigServiceProvider`](https://github.com/lokhman/silex-config) and adds `--env` (`-e` in short)
option to all registered commands.

### <a name="cron-command"></a>Cron Commands
The library provides commands for running background tasks as cron. Configuration supports cron schedule expressions.

    $app = new Application([
        'cron' => [
            'task1' => [
                'command' => 'app:some:command',        // internal commands (always sync!)
                'arguments' => ['--env' => $env],       // arguments as array
                'at' => '0 0 * * *',                    // cron expression
                'output' => '&',                        // "&" is to redirect output to cron stdout
            ],
            'task2' => [
                'raw' => '/path/to/task2',              // raw command line
                'at' => '@daily',                       // cron expression
                'output' => '/path/to/task2.log',       // output to file
            ],
            'task3' => [
                'raw' => '/path/to/task3',              // raw command line
                'at' => '@annually',                    // cron expression
                'output' => null,                       // disable output (default)
            ],
        ],
    ]);

    $console = new Console($app);
    $console->add(new Commands\Cron\RunCommand());
    $console->run();

## <a name="license"></a>License
Library is available under the MIT license. The included LICENSE file describes this in detail.
