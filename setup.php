#!/usr/bin/env php
<?php

/**
 * Laravel All-In-One Setup Script
 * 
 * This script automates the setup process for the Laravel project.
 * It works on both Windows and Mac/Linux systems.
 * 
 * Usage:
 *   php setup.php                     Interactive: choose .env copy + which Docker services
 *   php setup.php -y                  Non-interactive: all compose services + full setup
 *   php setup.php -y --no-docker      No containers; still runs composer, key, optimize, migrate
 *   php setup.php --help
 *
 * Composer install, key:generate, optimize, migrate and connections:check always run (no prompts).
 */

/**
 * Parse CLI arguments
 *
 * @return array{help: bool, non_interactive: bool, no_docker: bool}
 */
function parseSetupArgv(array $argv): array {
    $help = in_array('--help', $argv, true) || in_array('-h', $argv, true);
    $nonInteractive = in_array('-y', $argv, true) || in_array('--yes', $argv, true);
    $noDocker = in_array('--no-docker', $argv, true);

    return [
        'help' => $help,
        'non_interactive' => $nonInteractive,
        'no_docker' => $noDocker,
    ];
}

/**
 * Prompt y/n; empty line uses default
 */
function promptYesNo(string $question, bool $default): bool {
    $suffix = $default ? '[Y/n]' : '[y/N]';
    echo "{$question} {$suffix}: ";
    $line = fgets(STDIN);
    if ($line === false) {
        return $default;
    }
    $line = strtolower(trim($line));
    if ($line === '') {
        return $default;
    }

    return str_starts_with($line, 'y');
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     Laravel All-In-One - Automated Setup Script          ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

$cli = parseSetupArgv($argv);
if ($cli['help']) {
    echo "Options:\n";
    echo "  (none)        Ask: copy .env (if missing)? Which Docker services to start?\n";
    echo "                postgres, redis, rabbitmq, elasticsearch, kibana (each opt-in).\n";
    echo "                Always runs: composer install, key:generate (if needed), optimize, migrate.\n";
    echo "  -y, --yes     No prompts: copy .env if missing, start all Docker services (unless --no-docker).\n";
    echo "  --no-docker   Do not start any Docker services.\n";
    echo "  -h, --help    Show this help.\n";
    echo "\n";
    exit(0);
}

$steps = [];
$errors = [];

/**
 * Execute a command and return the result
 */
function executeCommand($command, $description, $required = true) {
    global $steps, $errors;
    
    $isWindows = (PHP_OS_FAMILY === 'Windows' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    
    echo "🔄 {$description}...\n";
    $startTime = microtime(true);
    
    // Execute command
    $output = [];
    $returnCode = 0;
    
    // PHP exec() works the same on both platforms for most commands
    // Docker commands might need special handling on Windows
    exec($command . ' 2>&1', $output, $returnCode);
    
    $duration = round(microtime(true) - $startTime, 2);
    
    if ($returnCode === 0) {
        echo "✅ {$description} completed ({$duration}s)\n\n";
        $steps[] = ['status' => 'success', 'description' => $description, 'duration' => $duration];
        return true;
    } else {
        $errorMsg = implode("\n", $output);
        echo "❌ {$description} failed\n";
        if (!empty($errorMsg)) {
            // Truncate long error messages
            $displayError = strlen($errorMsg) > 200 ? substr($errorMsg, 0, 200) . '...' : $errorMsg;
            echo "   Error: " . $displayError . "\n";
        }
        echo "\n";
        
        $steps[] = ['status' => 'error', 'description' => $description, 'error' => $errorMsg];
        
        if ($required) {
            $errors[] = $description;
        }
        
        return false;
    }
}

/**
 * Check if a file exists
 */
function fileExists($file) {
    return file_exists($file);
}

/**
 * Check if a command is available
 */
function commandExists($command) {
    $isWindows = (PHP_OS_FAMILY === 'Windows' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $whereIsCommand = $isWindows ? 'where' : 'which';
    
    // Escape command for Windows
    $escapedCommand = $isWindows ? escapeshellarg($command) : $command;
    
    $process = proc_open(
        "$whereIsCommand $escapedCommand",
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    
    if ($process !== false) {
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        return $returnCode === 0;
    }
    
    return false;
}

/**
 * Get Docker Compose command (handles both v1 and v2)
 */
function getDockerComposeCommand() {
    // Try docker compose (v2) first
    if (commandExists('docker')) {
        $output = [];
        $returnCode = 0;
        exec('docker compose version 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            return 'docker compose';
        }
    }
    
    // Fallback to docker-compose (v1)
    if (commandExists('docker-compose')) {
        return 'docker-compose';
    }
    
    return null;
}

/**
 * Which compose services the user wants (service name => bool)
 *
 * @return array<string, bool>
 */
function defaultAllDockerServices(): array {
    return [
        'postgres' => true,
        'redis' => true,
        'rabbitmq' => true,
        'elasticsearch' => true,
        'kibana' => true,
    ];
}

/**
 * @param array<string, bool> $picked
 * @return list<string>
 */
function resolveDockerServiceList(array $picked): array {
    $list = [];
    foreach (['postgres', 'redis', 'rabbitmq', 'elasticsearch', 'kibana'] as $name) {
        if (!empty($picked[$name])) {
            $list[] = $name;
        }
    }
    if (in_array('kibana', $list, true) && !in_array('elasticsearch', $list, true)) {
        $list[] = 'elasticsearch';
    }

    return array_values(array_unique($list));
}

/**
 * Human-readable endpoints for services that were started (compose port mapping).
 *
 * @param list<string> $started Docker Compose service names
 * @return list<string>
 */
function serviceEndpointLines(array $started): array {
    $lines = [];
    $set = array_flip($started);

    if (isset($set['postgres'])) {
        $lines[] = 'PostgreSQL: 127.0.0.1:15432';
    }
    if (isset($set['redis'])) {
        $lines[] = 'Redis: 127.0.0.1:16379';
    }
    if (isset($set['rabbitmq'])) {
        $lines[] = 'RabbitMQ management UI: http://127.0.0.1:15672';
    }
    if (isset($set['elasticsearch'])) {
        $lines[] = 'Elasticsearch: http://127.0.0.1:9200';
    }
    if (isset($set['kibana'])) {
        $lines[] = 'Kibana: http://127.0.0.1:5601';
    }

    return $lines;
}

// --- User choices: only .env copy + Docker services (everything else runs automatically) ---
$servicePick = defaultAllDockerServices();
$runEnvCopy = !fileExists('.env') && fileExists('.env.example');

if ($cli['non_interactive']) {
    if ($cli['no_docker']) {
        $servicePick = array_map(static fn () => false, defaultAllDockerServices());
    }
} else {
    echo "📌 Setup options (composer install, key, optimize, and migrate run automatically).\n\n";

    if (!fileExists('.env') && fileExists('.env.example')) {
        $runEnvCopy = promptYesNo('Copy .env from .env.example?', true);
    } else {
        $runEnvCopy = false;
    }

    if (!$cli['no_docker'] && fileExists('docker-compose.yml')) {
        echo "\nWhich Docker services should be started?\n";
        $servicePick['postgres'] = promptYesNo('  Start PostgreSQL?', false);
        $servicePick['redis'] = promptYesNo('  Start Redis?', false);
        $servicePick['rabbitmq'] = promptYesNo('  Start RabbitMQ?', false);
        $servicePick['elasticsearch'] = promptYesNo('  Start Elasticsearch?', false);
        $servicePick['kibana'] = promptYesNo('  Start Kibana?', false);
        echo "\n";
    } else {
        $servicePick = array_map(static fn () => false, defaultAllDockerServices());
    }
}

if ($cli['no_docker']) {
    $servicePick = array_map(static fn () => false, defaultAllDockerServices());
}

$dockerServiceList = resolveDockerServiceList($servicePick);
$runDocker = $dockerServiceList !== [] && fileExists('docker-compose.yml');

// Check prerequisites (Docker only if we will run it)
echo "📋 Checking prerequisites...\n\n";

$basePrerequisites = ['php' => 'PHP', 'composer' => 'Composer'];
$missingPrerequisites = [];

foreach ($basePrerequisites as $command => $name) {
    if (commandExists($command)) {
        echo "✅ {$name} is installed\n";
    } else {
        echo "❌ {$name} is NOT installed\n";
        $missingPrerequisites[] = $name;
    }
}

if ($runDocker) {
    if (commandExists('docker')) {
        echo "✅ Docker is installed\n";
    } else {
        echo "❌ Docker is NOT installed\n";
        $missingPrerequisites[] = 'Docker';
    }
}

if (!empty($missingPrerequisites)) {
    echo "\n";
    echo "⚠️  Missing prerequisites: " . implode(', ', $missingPrerequisites) . "\n";
    echo "   Please install the missing prerequisites before running this script.\n";
    echo "\n";
    exit(1);
}

echo "\n";

// Step 1: Composer dependencies (always)
if (!fileExists('vendor/autoload.php')) {
    echo "🔄 Installing Composer dependencies...\n";
    echo "⏰ ⚠️  This process may take 1-2 minutes. Please wait...\n\n";
} else {
    echo "🔄 Syncing Composer dependencies (composer install)...\n\n";
}
if (!executeCommand('composer install --no-interaction', 'Installing Composer dependencies', true)) {
    echo "❌ Setup failed at: Installing Composer dependencies\n";
    exit(1);
}

// Step 2: Create .env file if it doesn't exist
if ($runEnvCopy) {
    if (!fileExists('.env')) {
        if (fileExists('.env.example')) {
            echo "🔄 Creating .env file from .env.example...\n";
            if (copy('.env.example', '.env')) {
                echo "✅ .env file created\n\n";
                $steps[] = ['status' => 'success', 'description' => 'Creating .env file'];
            } else {
                echo "❌ Failed to create .env file\n\n";
                $errors[] = 'Creating .env file';
            }
        } else {
            echo "⚠️  .env.example file not found. Please create .env manually.\n\n";
        }
    } else {
        echo "ℹ️  .env file already exists, skipping...\n\n";
        $steps[] = ['status' => 'skipped', 'description' => 'Creating .env file'];
    }
} else {
    if (!fileExists('.env')) {
        echo "ℹ️  .env creation skipped by choice.\n\n";
        $steps[] = ['status' => 'skipped', 'description' => 'Creating .env file (declined)'];
    } else {
        echo "ℹ️  .env file already exists.\n\n";
        $steps[] = ['status' => 'skipped', 'description' => 'Creating .env file'];
    }
}

// Step 3: Application key (always when .env exists and placeholder empty)
if (!fileExists('.env')) {
    echo "ℹ️  Application key skipped: .env is missing.\n\n";
    $steps[] = ['status' => 'skipped', 'description' => 'Generating application key (no .env)'];
} else {
    $envContent = file_get_contents('.env');
    $needsKey = strpos($envContent, 'APP_KEY=') === false || strpos($envContent, 'APP_KEY=base64:') === false;
    if ($needsKey) {
        if (!executeCommand('php artisan key:generate --ansi', 'Generating application key', true)) {
            echo "❌ Setup failed at: Generating application key\n";
            exit(1);
        }
    } else {
        echo "ℹ️  Application key already present, skipping key:generate...\n\n";
        $steps[] = ['status' => 'skipped', 'description' => 'Generating application key'];
    }
}

// Step 4: Docker — only selected services
if ($runDocker) {
    $implicitEs = in_array('elasticsearch', $dockerServiceList, true)
        && empty($servicePick['elasticsearch'])
        && !empty($servicePick['kibana']);
    if ($implicitEs) {
        echo "ℹ️  Elasticsearch was added automatically (required by Kibana).\n\n";
    }
    echo "🔄 Starting Docker services: " . implode(', ', $dockerServiceList) . "\n";
    echo "⏰ ⚠️  First-time image download may take 30+ seconds.\n\n";
    $dockerComposeCmd = getDockerComposeCommand();
    if ($dockerComposeCmd === null) {
        echo "❌ Docker Compose not found. Please install Docker Compose.\n";
        $errors[] = 'Starting Docker containers';
    } else {
        $servicesArg = implode(' ', array_map('escapeshellarg', $dockerServiceList));
        if (!executeCommand("$dockerComposeCmd up -d $servicesArg", 'Starting Docker containers', false)) {
            echo "⚠️  Docker containers failed to start. Continuing anyway...\n";
            echo "   Manually: $dockerComposeCmd up -d $servicesArg\n\n";
        }
    }
    echo "⏳ Waiting for containers to become ready (10 seconds)...\n";
    sleep(10);
    echo "\n";
} else {
    echo "ℹ️  Docker: no services selected or docker-compose.yml missing; no containers started.\n\n";
    $steps[] = ['status' => 'skipped', 'description' => 'Starting Docker containers'];
}

// Step 5: Optimize Laravel (always)
if (!executeCommand('php artisan optimize', 'Optimizing Laravel application', false)) {
    echo "⚠️  Optimization failed, but continuing...\n\n";
}

// Step 6: Migrations (always)
if (!executeCommand('php artisan migrate --force', 'Running database migrations', true)) {
    echo "❌ Setup failed at: Running database migrations\n";
    exit(1);
}

// Step 7: Connections check (always, best-effort)
echo "🔄 Checking service connections...\n";
$connectionCheck = executeCommand('php artisan connections:check', 'Checking service connections', false);

// Summary
echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                    SETUP SUMMARY                         ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

foreach ($steps as $step) {
    $status = $step['status'];
    $description = $step['description'];
    
    if ($status === 'success') {
        $duration = isset($step['duration']) ? " ({$step['duration']}s)" : '';
        echo "✅ {$description}{$duration}\n";
    } elseif ($status === 'error') {
        echo "❌ {$description}\n";
    } elseif ($status === 'skipped') {
        echo "ℹ️  {$description} (skipped)\n";
    }
}

echo "\n";

if (!empty($errors)) {
    echo "⚠️  Some steps failed:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n";
    echo "⚠️  Setup completed with errors. Please review the errors above.\n";
    exit(1);
} else {
    echo "✅ Setup completed successfully!\n";
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              🚀 START YOUR APPLICATION                    ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    $isWindows = (PHP_OS_FAMILY === 'Windows' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $greenColor = $isWindows ? '' : "\033[1;32m";
    $resetColor = $isWindows ? '' : "\033[0m";
    
    echo "   Or use the dev script (includes queue worker, logs, and Vite):\n";
    echo "   {$greenColor}composer run dev{$resetColor}\n";
    echo "\n";
    echo "📝 Additional commands:\n";
    if ($dockerServiceList !== []) {
        echo "   - Check Docker containers: docker compose ps\n";
    }
    echo "   - Check service connections: php artisan connections:check\n";
    echo "\n";
    echo "🔗 Quick reference:\n";
    echo "   - Laravel (after php artisan serve): http://127.0.0.1:8000\n";
    foreach (serviceEndpointLines($dockerServiceList) as $line) {
        echo "   - {$line}\n";
    }
    echo "\n";
    echo "📌 To start the Laravel development server, run:\n";
    echo "   {$greenColor}php artisan serve{$resetColor}\n";
    echo "\n";
    exit(0);
}

