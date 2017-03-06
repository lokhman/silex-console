<?php
/**
 * Tools for Silex 2+ framework.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 *
 * @link https://github.com/lokhman/silex-tools
 *
 * Copyright (c) 2016 Alexander Lokhman <alex.lokhman@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Lokhman\Silex\Console\Provider;

use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Pimple\Container;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Silex service provider for Doctrine console commands.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 *
 * @link https://github.com/lokhman/silex-tools
 */
class DoctrineServiceProvider extends AbstractServiceProvider implements BootableProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        /* not implemented */
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
        $commands = [
            new \Doctrine\DBAL\Tools\Console\Command\ImportCommand(),
            new \Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand(),
            new \Doctrine\DBAL\Tools\Console\Command\RunSqlCommand(),
        ];

        $helperSet = new HelperSet([
            'db' => new ConnectionHelper($app['db']),
        ]);

        if (isset($app['orm.em'])) {  // Doctrine ORM commands and helpers
            $helperSet->set(new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($app['orm.em']), 'em');
            $commands = array_merge($commands, [
                new \Doctrine\ORM\Tools\Console\Command\ClearCache\CollectionRegionCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand(),

                new \Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand(),
                new \Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand(),
                new \Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand(),

                new \Doctrine\ORM\Tools\Console\Command\ConvertDoctrine1SchemaCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand(),
                new \Doctrine\ORM\Tools\Console\Command\EnsureProductionSettingsCommand(),
                new \Doctrine\ORM\Tools\Console\Command\GenerateEntitiesCommand(),
                new \Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand(),
                new \Doctrine\ORM\Tools\Console\Command\GenerateRepositoriesCommand(),
                new \Doctrine\ORM\Tools\Console\Command\InfoCommand(),
                new \Doctrine\ORM\Tools\Console\Command\MappingDescribeCommand(),
                new \Doctrine\ORM\Tools\Console\Command\RunDqlCommand(),
                new \Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand(),
            ]);
        }

        $this->getConsole()->setHelperSet($helperSet);
        $this->getConsole()->addCommands($commands);
    }
}
