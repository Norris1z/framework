<?php

namespace Illuminate\Console\Exceptions;

use Symfony\Component\Process\Exception\LogicException;

class ProcessNotStartedException extends LogicException
{
    /**
     * Creates a new Process Exception instance.
     *
     * @param  \Illuminate\Console\Process|null  $process
     * @return void
     */
    public function __construct($process = null)
    {
        parent::__construct($process
            ? sprintf('The process "%s" failed to start.', $process->getCommandLine())
            : 'The process failed to start.',
        );
    }
}
