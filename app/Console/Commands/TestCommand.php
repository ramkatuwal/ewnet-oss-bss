<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestCommand extends Command
{
    protected $signature = 'test {--filter= : Filter which tests to run}
                           {--stop-on-failure : Stop execution upon first failure}
                           {--group= : Run only tests from the specified group(s)}';

    protected $description = 'Run the application tests';

    public function handle()
    {
        $this->info('Running application tests...');
        $this->newLine();

        $command = [base_path('vendor/bin/phpunit')];

        if ($this->option('filter')) {
            $command[] = '--filter';
            $command[] = $this->option('filter');
        }

        if ($this->option('stop-on-failure')) {
            $command[] = '--stop-on-failure';
        }

        if ($this->option('group')) {
            $command[] = '--group';
            $command[] = $this->option('group');
        }

        // Disable TTY for Docker/CI compatibility
        $process = new Process($command);
        $process->setTty(false); 
        $process->setTimeout(null); // No timeout
        
        // Run and output directly
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo $buffer;
            } else {
                echo $buffer;
            }
        });

        return $process->isSuccessful() ? 0 : 1;
    }
}
