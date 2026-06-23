<?php

declare(strict_types=1);

namespace App\Jq\Cli;

use RuntimeException;

/** Invalid command-line usage. Maps to jq exit code 2. */
final class CliUsageException extends RuntimeException {}
