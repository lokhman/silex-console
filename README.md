# silex-console

[![StyleCI](https://styleci.io/repos/79904121/shield?branch=master)](https://styleci.io/repos/79904121)

Console application for [**Silex 2.0+**](http://silex.sensiolabs.org/) micro-framework.

> This project is a part of [`silex-tools`](https://github.com/lokhman/silex-tools) library.

## <a name="installation"></a>Installation
You can install `silex-console` with [Composer](http://getcomposer.org):

    composer require lokhman/silex-console

## <a name="documentation"></a>Documentation
A wrapper class for [Symfony Console](https://github.com/symfony/console) application that registers console commands
and service providers. Also supplies `DoctrineServiceProvider` and `DoctrineMigrationsServiceProvider` classes to
register commands for [Doctrine DBAL](https://github.com/doctrine/dbal),
[Doctrine ORM](https://github.com/doctrine/doctrine2) and [Doctrine Migrations](https://github.com/doctrine/migrations).

    #!/usr/bin/env php
    require __DIR__ . '/../vendor/autoload.php';

    use Silex\Application;
    use Silex\Provider as Providers;
    use Lokhman\Silex\Console\Console;
    use Lokhman\Silex\Console\Provider as ConsoleProviders;

    $app = new Application();
    $app->register(new Providers\DoctrineServiceProvider());

    $console = new Console($app);
    $console->registerServiceProvider(new ConsoleProviders\DoctrineServiceProvider());
    $console->registerServiceProvider(new ConsoleProviders\DoctrineMigrationsServiceProvider(), [
        'migrations.directory' => __DIR__ . '/../app/migrations',
        'migrations.namespace' => 'Project\Migrations',
    ]);
    $console->run();

Console supports [`ConfigServiceProvider`](https://github.com/lokhman/silex-config) and adds `--env` (`-e` in short)
option to all registered commands.

## <a name="license"></a>License
Library is available under the MIT license. The included LICENSE file describes this in detail.
