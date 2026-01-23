#!/usr/bin/env php
<?php

/**
 * Laravel All-In-One Setup Script
 * 
 * This script automates the setup process for the Laravel project.
 * It works on both Windows and Mac/Linux systems.
 * 
 * Usage: php setup.php
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     Laravel All-In-One - Automated Setup Script          ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

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

// Check prerequisites
echo "📋 Checking prerequisites...\n\n";

$prerequisites = [
    'php' => 'PHP',
    'composer' => 'Composer',
    'docker' => 'Docker',
];

$missingPrerequisites = [];

foreach ($prerequisites as $command => $name) {
    if (commandExists($command)) {
        echo "✅ {$name} is installed\n";
    } else {
        echo "❌ {$name} is NOT installed\n";
        $missingPrerequisites[] = $name;
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

// Step 1: Install Composer dependencies
if (!fileExists('vendor/autoload.php')) {
    echo "🔄 Installing Composer dependencies...\n";
    echo "⏰ ⚠️  This process may take 1-2 minutes. Please wait...\n";
    echo "   (Downloading and installing PHP packages)\n\n";
    if (!executeCommand('composer install --no-interaction', 'Installing Composer dependencies', true)) {
        echo "❌ Setup failed at: Installing Composer dependencies\n";
        exit(1);
    }
} else {
    echo "ℹ️  Composer dependencies already installed, skipping...\n\n";
    $steps[] = ['status' => 'skipped', 'description' => 'Installing Composer dependencies'];
}

// Step 2: Create .env file if it doesn't exist
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

// Step 3: Generate application key if needed
if (fileExists('.env')) {
    $envContent = file_get_contents('.env');
    if (strpos($envContent, 'APP_KEY=') === false || strpos($envContent, 'APP_KEY=base64:') === false) {
        if (!executeCommand('php artisan key:generate --ansi', 'Generating application key', true)) {
            echo "❌ Setup failed at: Generating application key\n";
            exit(1);
        }
    } else {
        echo "ℹ️  Application key already exists, skipping...\n\n";
        $steps[] = ['status' => 'skipped', 'description' => 'Generating application key'];
    }
}

// Step 4: Start Docker containers
echo "🔄 Starting Docker containers...\n";
echo "⏰ ⚠️  This process may take 30-45 seconds. Please wait...\n";
echo "   (Downloading images and starting containers for the first time)\n\n";
$dockerComposeCmd = getDockerComposeCommand();
if ($dockerComposeCmd === null) {
    echo "❌ Docker Compose not found. Please install Docker Compose.\n";
    $errors[] = 'Starting Docker containers';
} else {
    if (!executeCommand("$dockerComposeCmd up -d", 'Starting Docker containers', true)) {
        echo "⚠️  Docker containers failed to start. Continuing anyway...\n";
        echo "   You may need to start them manually: $dockerComposeCmd up -d\n\n";
    }
}

// Wait a bit for Docker containers to be ready
echo "⏳ Waiting for Docker containers to be ready (10 seconds)...\n";
sleep(10);
echo "\n";

// Step 5: Optimize Laravel
if (!executeCommand('php artisan optimize', 'Optimizing Laravel application', false)) {
    echo "⚠️  Optimization failed, but continuing...\n\n";
}

// Step 6: Run migrations
if (!executeCommand('php artisan migrate --force', 'Running database migrations', true)) {
    echo "❌ Setup failed at: Running database migrations\n";
    exit(1);
}

// Step 7: Check connections
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
    echo "📝 Additional Commands:\n";
    echo "   - Check Docker containers: docker compose ps\n";
    echo "   - Check service connections: php artisan connections:check\n";
    echo "\n";
    echo "🔗 Service URLs (after starting the server):\n";
    echo "   - Laravel App: http://127.0.0.1:8000\n";
    echo "   - RabbitMQ UI: http://127.0.0.1:15672\n";
    echo "   - Elasticsearch: http://127.0.0.1:9200\n";
    echo "   - Kibana: http://127.0.0.1:5601\n";
    echo "\n";
    echo "📌 To start the Laravel development server, run:\n";
    echo "   {$greenColor}php artisan serve{$resetColor}\n";
    echo "\n";
    exit(0);
}

