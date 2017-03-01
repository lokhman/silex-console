<?php
/**
 * Tools for Silex 2+ framework.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
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

namespace Lokhman\Silex\Command\Cache;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cache clear console command.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 * @link https://github.com/lokhman/silex-tools
 */
class ClearCommand extends Command {

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        foreach ((new \ReflectionClass($this))->getMethods() as $method) {
            if ($method->class == self::class || is_subclass_of($method->class, self::class)) {
                if (strpos($method->name, '_') === 0) {
                    $targets[] = substr($method->name, 1);
                }
            }
        }
        sort($targets);

        $this
            ->setName('cache:clear')
            ->setDescription('Clears cache')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Target cache to clear', $targets);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        foreach ($input->getOption('target') as $target) {
            if (method_exists($this, '_' . $target)) {
                call_user_func([$this, '_' . $target], $input, $output);
            } else {
                $output->writeln(sprintf('<error>Unknown target "%s"</error>', $target));
                return;
            }
        }

        $output->writeln('<info>Cache cleared<info>');
    }

    /**
     * @see \Symfony\Component\Filesystem\Filesystem
     */
    private function fsEmpty($path) {
        $iterator = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST) as $fileInfo) {
            $file = $fileInfo->getPathname();
            if (is_link($file)) {
                if (!@(unlink($file) || '\\' !== DIRECTORY_SEPARATOR || rmdir($file)) && file_exists($file)) {
                    $error = error_get_last();
                    throw new \RuntimeException(sprintf('Failed to remove symlink "%s": %s.', $file, $error['message']));
                }
            } elseif (is_dir($file)) {
                if (!@rmdir($file) && file_exists($file)) {
                    $error = error_get_last();
                    throw new \RuntimeException(sprintf('Failed to remove directory "%s": %s.', $file, $error['message']));
                }
            } elseif (!@unlink($file) && file_exists($file)) {
                $error = error_get_last();
                throw new \RuntimeException(sprintf('Failed to remove file "%s": %s.', $file, $error['message']));
            }
        }
    }

    private function _apc(InputInterface $input, OutputInterface $output) {
        if (!function_exists('apc_clear_cache')) {
            return;
        }

        apc_clear_cache();
        $output->writeln('<comment>APC cache cleared</comment>');
    }

    private function _apcu(InputInterface $input, OutputInterface $output) {
        if (!function_exists('apcu_clear_cache')) {
            return;
        }

        apcu_clear_cache();
        $output->writeln('<comment>APCu cache cleared</comment>');
    }

    private function _twig(InputInterface $input, OutputInterface $output) {
        $app = $this->getApplication()->getContainer();
        if (!isset($app['twig.options']['cache'])) {
            return;
        }

        $this->fsEmpty($app['twig.options']['cache']);
        $output->writeln('<comment>Twig cache cleared</comment>');
    }

}
