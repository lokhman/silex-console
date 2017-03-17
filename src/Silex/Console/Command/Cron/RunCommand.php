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
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\Process;

/**
 * Cron run console command.
 *
 * @author Alexander Lokhman <alex.lokhman@gmail.com>
 *
 * @link https://github.com/lokhman/silex-tools
 */
class RunCommand extends Command
{
    use LockableTrait;

    const TIMEOUT = 500000;  // 500 msec

    const STATE_STOPPED = 0;
    const STATE_RUNNING = 1;

    protected $output;
    protected $tasks = [];
    protected $state = self::STATE_RUNNING;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cron:run')
            ->setDescription('Starts cron scheduler');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('<error>The command is already running in another process.</error>');

            return 0;
        }

        $this->init($output);
        $this->loop();
    }

    protected function init(OutputInterface $output)
    {
        declare(ticks=1);

        $signal = function () use ($output) {
            $output->writeln('<comment>Cron is shutting down...</comment>');
            $this->state = RunCommand::STATE_STOPPED;
        };

        pcntl_signal(SIGINT, $signal);
        pcntl_signal(SIGTERM, $signal);

        $this->output = $output;

        $console = $this->getApplication();
        $app = $console->getContainer();

        if (isset($app['cron'])) {
            $this->setTasks($app['cron']);
        }
    }

    protected function setTasks(array $tasks)
    {
        foreach ($tasks as $name => $options) {
            if (!isset($options['at']) || !is_string($options['at'])) {
                throw new \RuntimeException(sprintf('Task "%s" must have valid "at" parameter defined.', $name));
            }

            $task = new Task($options['at'], $name);

            if (isset($options['output'])) {
                if ('&' == $output = $options['output']) {
                    $output = $this->output;
                } else {
                    $output = new StreamOutput(@fopen($output, 'a'), OutputInterface::VERBOSITY_NORMAL);
                }
                $task->setOutput($output);
            }

            if (isset($options['command']) && is_string($options['command'])) {
                $task->setCommand($options['command'], isset($options['arguments']) ? $options['arguments'] : []);
            } elseif (isset($options['raw']) && is_string($options['raw'])) {
                $task->setRaw($options['raw'], !isset($options['async']) || $options['async']);
            } else {
                throw new \RuntimeException(sprintf('Task "%s" must have valid "command" or "raw" parameter defined.', $name));
            }

            $this->output->writeln([
                sprintf('<fg=cyan>Registered new task "%s":', $name),
                sprintf(' next run at %s</>', $task->getNextDate()->format('r')),
            ]);

            $this->tasks[] = $task;
        }
    }

    protected function loop()
    {
        $execute = function (Task $task) {
            $output = $task->getOutput();
            $output->writeln(sprintf('<fg=black;bg=white>%s [%s]</>', date('Y-m-d H:i:s'), $task->getName()));

            switch ($task->getType()) {
                case Task::TYPE_COMMAND:
                    $input = $task->getCommand();
                    $console = $this->getApplication();
                    $command = $console->find($input->getParameterOption('command'));
                    $command->run($input, $output);
                    break;

                case Task::TYPE_RAW:
                    $process = $task->getRaw();
                    if ($task->isAsync()) {
                        $task->attachAsync($process);
                        $process->start();
                    } else {
                        $process->run(function ($type, $buffer) use ($output) {
                            if ($type === Process::ERR) {
                                $buffer = sprintf('<error>%s</error>', $buffer);
                            }
                            $output->write($buffer);
                        });
                    }
            }
        };

        $this->output->writeln('<info>Starting cron...</info>');

        while ($this->state != self::STATE_STOPPED) {
            foreach ($this->tasks as $task) {
                $task->queue($execute);
            }
            usleep(self::TIMEOUT);
        }

        foreach ($this->tasks as $task) {
            $task->detachAsync();
        }
    }
}

class Task
{
    const TYPE_RAW = 0;
    const TYPE_COMMAND = 1;

    protected $name;
    protected $type;

    protected $output;
    protected $definition;
    protected $cronExpression;
    protected $nextTimestamp;

    protected $asyncPool = [];
    protected $async;

    public function __construct($at, $name = 'UNKNOWN')
    {
        $this->cronExpression = CronExpression::factory($at);
        $this->nextTimestamp = $this->getNextTimestamp();
        $this->output = new NullOutput();
        $this->name = $name;
    }

    public function getNextDate()
    {
        return $this->cronExpression->getNextRunDate();
    }

    protected function getNextTimestamp()
    {
        return $this->getNextDate()->getTimestamp();
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function getRaw()
    {
        if ($this->type !== self::TYPE_RAW) {
            return;
        }

        $process = new Process($this->definition);
        $process->setPty(true);

        return $process;
    }

    public function setRaw($raw, $async = true)
    {
        $this->type = self::TYPE_RAW;
        $this->definition = $raw;
        $this->async = $async;
    }

    public function getCommand()
    {
        if ($this->type !== self::TYPE_COMMAND) {
            return;
        }

        return $this->definition;
    }

    public function setCommand($command, array $arguments = [])
    {
        $this->type = self::TYPE_COMMAND;
        $arguments['command'] = $command;
        $this->definition = new ArrayInput($arguments);
    }

    public function isAsync()
    {
        return (bool) $this->async;
    }

    public function attachAsync(Process $process)
    {
        $this->asyncPool[] = $process;
    }

    public function detachAsync()
    {
        foreach ($this->asyncPool as $process) {
            $process->wait();
        }
    }

    public function queue(callable $callback)
    {
        // flush output from async processes
        foreach ($this->asyncPool as $i => $process) {
            if ($process->isStarted()) {
                if ($buffer = $process->getIncrementalOutput()) {
                    $this->output->write($buffer);
                }
                if ($buffer = $process->getIncrementalErrorOutput()) {
                    $this->output->write(sprintf('<error>%s</error>', $buffer));
                }
            } elseif ($process->isTerminated()) {
                unset($this->asyncPool[$i]);
            }
        }

        if ($this->nextTimestamp == $next = $this->getNextTimestamp()) {
            return /* task is still in the queue */;
        }

        $this->nextTimestamp = $next;
        $callback($this);
    }
}
