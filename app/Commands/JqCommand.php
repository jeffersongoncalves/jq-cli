<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class JqCommand extends Command
{
    protected $signature = 'jq';

    protected $description = 'Command-line JSON processor (jq clone). Run: jq [OPTIONS] FILTER [FILES...]';

    public function handle(): int
    {
        // jq invocations are intercepted in the entry script and handled by
        // App\Jq\Cli\Runner. Reaching here means no filter was provided.
        $this->line('jq - commandline JSON processor');
        $this->line('Usage: jq [OPTIONS] FILTER [FILES...]');
        $this->line('Example: echo \'{"a":1}\' | jq ".a"');

        return self::SUCCESS;
    }
}
