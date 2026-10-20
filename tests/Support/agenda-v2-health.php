<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only.');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
echo "Verified: isolated local MariaDB, testing, no .env, fake external services.\n";
