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

namespace Lokhman\Silex\Console\Command\Cron;

use Cron\CronExpression;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cron tasks console command.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 *
 * @link https://github.com/lokhman/silex-tools
 */
class TasksCommand extends Command
{
    const VERBOSITY_RUN_HISTORY = 3;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cron:tasks')
            ->setDescription('Prints cron scheduler');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console = $this->getApplication();
        $app = $console->getContainer();

        if (!isset($app['cron'])) {
            $output->writeln('<comment>No cron tasks registered.</comment>');
            return;
        }

        foreach ($app['cron'] as $name => $options) {
            if (!isset($options['at']) || !is_string($options['at'])) {
                throw new \RuntimeException(sprintf('Task "%s" must have valid "at" parameter defined.', $name));
            }

            if (isset($options['command']) && is_string($options['command'])) {
                $arguments = isset($options['arguments']) ? $options['arguments'] : [];
                $output->writeln([
                    sprintf('<fg=cyan>%s [command]</>', $name),
                    sprintf(' <info>%s %s</info>', $options['command'], new CommandArguments($arguments)),
                ]);
            } elseif (isset($options['raw']) && is_string($options['raw'])) {
                $sync = isset($options['async']) && !$options['async'];
                $output->writeln([
                    sprintf('<fg=cyan>%s [raw%s]</>', $name, $sync ? ', sync' : ''),
                    sprintf(' <info>%s</info>', $options['raw']),
                ]);
            } else {
                throw new \RuntimeException(sprintf('Task "%s" must have valid "command" or "raw" parameter defined.', $name));
            }

            $cron = CronExpression::factory($options['at']);

            if ($output->isVerbose()) {
                $total = self::VERBOSITY_RUN_HISTORY;

                $output->writeln(sprintf(' cron expression: %s', $cron->getExpression()));

                $output->writeln(sprintf(' previous %s runs:', $total));
                foreach (array_reverse($cron->getMultipleRunDates($total, 'now', true)) as $runDate) {
                    $output->writeln('  - '.$runDate->format('r'));
                }

                $output->writeln(sprintf(' next %s runs:', $total));
                foreach ($cron->getMultipleRunDates($total) as $runDate) {
                    $output->writeln('  - '.$runDate->format('r'));
                }
            } else {
                $output->writeln(sprintf(' next run at %s', $cron->getNextRunDate()->format('r')));
            }
        }
    }
}

class CommandArguments extends ArrayInput
{
    public function __toString()
    {
        $reflection = new \ReflectionClass(parent::class);
        $private = $reflection->getProperty('parameters');
        $private->setAccessible(true);

        $parameters = [];
        foreach ($private->getValue($this) as $param => $values) {
            foreach ((array) $values as $value) {
                if ($param && '-' === $param[0]) {
                    $parameters[] = $param.('' != $value ? '='.$this->escapeToken($value) : '');
                } else {
                    $parameters[] = $this->escapeToken($value);
                }
            }
        }

        return implode(' ', $parameters);
    }
}
