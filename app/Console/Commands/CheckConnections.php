<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use Exception;

class CheckConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connections:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks Redis, PostgreSQL, RabbitMQ and Elasticsearch connections';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting connection checks...');
        $this->newLine();

        $results = [
            'redis' => $this->checkRedis(),
            'postgresql' => $this->checkPostgreSQL(),
            'rabbitmq' => $this->checkRabbitMQ(),
            'elasticsearch' => $this->checkElasticsearch(),
        ];

        $this->newLine();
        $this->displayResults($results);

        $allConnected = collect($results)->every(fn($result) => $result['status'] === 'success');

        return $allConnected ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): array
    {
        $this->info('🔍 Checking Redis connection...');

        $config = config('redis.default', []);
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $client = config('redis.client', 'phpredis');

        // First check Redis client configuration
        if ($client === 'phpredis' && !extension_loaded('redis')) {
            return [
                'status' => 'error',
                'message' => "Redis PHP extension (phpredis) is not installed. Please install the 'phpredis' extension or set REDIS_CLIENT=predis in your .env file.",
                'host' => $host,
                'port' => $port,
                'client' => $client,
            ];
        }

        try {
            $startTime = microtime(true);
            
            // Test connection with Redis facade
            $redis = Redis::connection();
            $redis->ping();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'success',
                'message' => "Redis connection successful",
                'host' => $host,
                'port' => $port,
                'client' => $client,
                'response_time' => $responseTime . ' ms',
            ];
        } catch (\Throwable $e) {
            // If Redis facade doesn't work, try socket connection
            try {
                $startTime = microtime(true);
                $socket = @fsockopen($host, $port, $errno, $errstr, 2);
                
                if ($socket) {
                    fclose($socket);
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    
                    return [
                        'status' => 'success',
                        'message' => "Connected to Redis server (socket test)",
                        'host' => $host,
                        'port' => $port,
                        'client' => $client,
                        'response_time' => $responseTime . ' ms',
                        'note' => 'Laravel Redis client configuration should be checked',
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'message' => "Could not connect to Redis server: {$errstr} (Error Code: {$errno})",
                        'host' => $host,
                        'port' => $port,
                        'client' => $client,
                    ];
                }
            } catch (\Throwable $socketError) {
                return [
                    'status' => 'error',
                    'message' => "Redis connection failed: " . $e->getMessage() . " | Socket test also failed: " . $socketError->getMessage(),
                    'host' => $host,
                    'port' => $port,
                    'client' => $client,
                ];
            }
        }
    }

    /**
     * Check PostgreSQL connection
     */
    private function checkPostgreSQL(): array
    {
        $this->info('🔍 Checking PostgreSQL connection...');

        try {
            $startTime = microtime(true);
            
            // Check PostgreSQL connection
            $connection = DB::connection('pgsql');
            $connection->getPdo();
            
            // Execute a simple query
            $version = $connection->selectOne('SELECT version() as version');
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $config = config('postgresql', []);
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 5432;
            $database = $config['database'] ?? 'laravel';

            return [
                'status' => 'success',
                'message' => "PostgreSQL connection successful",
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'version' => $version->version ?? 'Unknown',
                'response_time' => $responseTime . ' ms',
            ];
        } catch (Exception $e) {
            $config = config('postgresql', []);
            
            return [
                'status' => 'error',
                'message' => "PostgreSQL connection failed: " . $e->getMessage(),
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 5432,
                'database' => $config['database'] ?? 'laravel',
            ];
        }
    }

    /**
     * Check RabbitMQ connection
     */
    private function checkRabbitMQ(): array
    {
        $this->info('🔍 Checking RabbitMQ connection...');

        try {
            $startTime = microtime(true);

            // Get RabbitMQ configuration from config file
            $config = config('rabbitmq', []);
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 5672;
            $user = $config['user'] ?? 'guest';
            $password = $config['password'] ?? 'guest';
            $vhost = $config['vhost'] ?? '/';

            // Try connection
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost
            );

            // Test connection
            $channel = $connection->channel();
            $channel->close();
            $connection->close();

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'success',
                'message' => "RabbitMQ connection successful",
                'host' => $host,
                'port' => $port,
                'vhost' => $vhost,
                'user' => $user,
                'response_time' => $responseTime . ' ms',
            ];
        } catch (AMQPIOException $e) {
            $config = config('rabbitmq', []);
            
            return [
                'status' => 'error',
                'message' => "RabbitMQ connection failed: " . $e->getMessage(),
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 5672,
                'vhost' => $config['vhost'] ?? '/',
            ];
        } catch (Exception $e) {
            $config = config('rabbitmq', []);
            
            return [
                'status' => 'error',
                'message' => "RabbitMQ connection failed: " . $e->getMessage(),
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 5672,
                'vhost' => $config['vhost'] ?? '/',
            ];
        }
    }

    /**
     * Check Elasticsearch connection
     */
    private function checkElasticsearch(): array
    {
        $this->info('🔍 Checking Elasticsearch connection...');

        try {
            $startTime = microtime(true);

            // Get Elasticsearch configuration from config file
            $config = config('elasticsearch', []);
            $host = $config['connection']['host'] ?? $config['host'] ?? 'http://127.0.0.1:9200';
            $port = $config['connection']['port'] ?? 9200;
            $scheme = $config['connection']['scheme'] ?? 'http';

            // Parse host if it's a full URL
            if (strpos($host, '://') !== false) {
                $parsedUrl = parse_url($host);
                $host = $parsedUrl['host'] ?? '127.0.0.1';
                $port = $parsedUrl['port'] ?? 9200;
                $scheme = $parsedUrl['scheme'] ?? 'http';
            }

            // Try connection using HTTP request
            $url = "{$scheme}://{$host}:{$port}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($httpCode >= 200 && $httpCode < 400) {
                $data = json_decode($response, true);
                $version = $data['version']['number'] ?? 'Unknown';

                return [
                    'status' => 'success',
                    'message' => "Elasticsearch connection successful",
                    'host' => $host,
                    'port' => $port,
                    'version' => $version,
                    'response_time' => $responseTime . ' ms',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => "Elasticsearch connection failed: HTTP {$httpCode}" . ($curlError ? " - {$curlError}" : ''),
                    'host' => $host,
                    'port' => $port,
                ];
            }
        } catch (Exception $e) {
            $config = config('elasticsearch', []);
            $host = $config['connection']['host'] ?? $config['host'] ?? 'http://127.0.0.1:9200';
            
            // Parse host if it's a full URL
            if (strpos($host, '://') !== false) {
                $parsedUrl = parse_url($host);
                $host = $parsedUrl['host'] ?? '127.0.0.1';
                $port = $parsedUrl['port'] ?? 9200;
            } else {
                $port = $config['connection']['port'] ?? 9200;
            }
            
            return [
                'status' => 'error',
                'message' => "Elasticsearch connection failed: " . $e->getMessage(),
                'host' => $host,
                'port' => $port,
            ];
        }
    }

    /**
     * Display results
     */
    private function displayResults(array $results): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    CONNECTION REPORT');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        foreach ($results as $service => $result) {
            $serviceName = strtoupper($service);
            
            if ($result['status'] === 'success') {
                $this->line("✅ <fg=green>{$serviceName}</fg=green>");
                $this->line("   Status: <fg=green>Connected</fg=green>");
                $this->line("   Host: {$result['host']}");
                $this->line("   Port: {$result['port']}");
                
                if (isset($result['client'])) {
                    $this->line("   Client: {$result['client']}");
                }
                
                if (isset($result['database'])) {
                    $this->line("   Database: {$result['database']}");
                }
                
                if (isset($result['version'])) {
                    $this->line("   Version: " . substr($result['version'], 0, 50) . "...");
                }
                
                if (isset($result['vhost'])) {
                    $this->line("   VHost: {$result['vhost']}");
                }
                
                if (isset($result['user'])) {
                    $this->line("   User: {$result['user']}");
                }
                
                if (isset($result['response_time'])) {
                    $this->line("   Response Time: <fg=cyan>{$result['response_time']}</fg=cyan>");
                }
                
                if (isset($result['note'])) {
                    $this->line("   Note: <fg=yellow>{$result['note']}</fg=yellow>");
                }
            } else {
                $this->line("❌ <fg=red>{$serviceName}</fg=red>");
                $this->line("   Status: <fg=red>Connection Error</fg=red>");
                $this->line("   Host: {$result['host']}");
                $this->line("   Port: {$result['port']}");
                
                if (isset($result['client'])) {
                    $this->line("   Client: {$result['client']}");
                }
                
                if (isset($result['database'])) {
                    $this->line("   Database: {$result['database']}");
                }
                
                if (isset($result['vhost'])) {
                    $this->line("   VHost: {$result['vhost']}");
                }
                
                $this->line("   Error: <fg=red>{$result['message']}</fg=red>");
            }
            
            $this->newLine();
        }

        $this->info('═══════════════════════════════════════════════════════════');
    }
}

