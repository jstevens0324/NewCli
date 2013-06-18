<?php

namespace Application\Command;

use InvalidArgumentException,
    MssMessage\Model\Message,
    MssMessage\Model\MessageRecipientClient,
    Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractPoller extends Command
{
    protected $pidfile      = null;
    protected $interval     = 1;
    protected $lastPollTime = 0;
    protected $nextPollTime = 0;

    public function sigintShutdown($signal)
    {
        if ($signal === SIGINT || $signal === SIGTERM) {
            if (null !== $this->pidfile) {
                unlink('data/' . $this->pidfile);
            }
            exit();
        }
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        if (null !== $this->pidfile) {
            // Enable ticks so that CTRL+C can be caught
            declare(ticks = 1);

            // Attach functions to CTRL+C so that the PID file can be removed if force closed.
            // Doesn't work on the server, may need to enable pcntl_signal?
            //pcntl_signal(SIGTERM, array($this, 'sigintShutdown'));
            //pcntl_signal(SIGINT, array($this, 'sigintShutdown'));

            // Create PID file for CentOS service
            file_put_contents('data/' . $this->pidfile, getmypid());
        }

        parent::run($input, $output);
    }

    protected function waitInterval()
    {
        sleep($this->interval);
        /*
        if (!is_numeric($this->interval)) {
            throw new InvalidArgumentException('interval must be numeric');
        }

        // sleep for specified interval
        $this->lastPollTime = time();
        $this->nextPollTime = $this->lastPollTime + $this->interval;

        while($this->lastPollTime < $this->nextPollTime) {
            $this->lastPollTime = time();
        }

        $this->lastPollTime = time();
        */
    }
}
