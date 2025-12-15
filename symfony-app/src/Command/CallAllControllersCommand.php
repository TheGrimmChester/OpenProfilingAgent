<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Command to call all Symfony controllers to generate test data
 * 
 * This command uses the composite pattern to organize endpoint calls
 * into logical groups by controller.
 */
#[AsCommand(
    name: 'app:call-all-controllers',
    description: 'Call all Symfony controllers to generate test data for testing'
)]
class CallAllControllersCommand extends Command
{
    private string $baseUrl;
    private SymfonyStyle $io;
    private array $results = [];

    protected function configure(): void
    {
        $this
            ->addOption('base-url', 'u', InputOption::VALUE_OPTIONAL, 'Base URL for the Symfony app', 'http://localhost:8080')
            ->addOption('skip-curl', null, InputOption::VALUE_NONE, 'Skip endpoints that use cURL (may cause segfaults)')
            ->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'Number of iterations to repeat database operations', 10)
            ->addOption('data-size', 's', InputOption::VALUE_OPTIONAL, 'Size multiplier for data generation (1-100)', 1)
            ->setHelp('This command calls all controller endpoints to generate test data.');
    }

    private int $iterations;
    private int $dataSizeMultiplier;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->baseUrl = rtrim($input->getOption('base-url'), '/');
        $skipCurl = $input->getOption('skip-curl');
        $this->iterations = (int)($input->getOption('iterations') ?? 10);
        $this->dataSizeMultiplier = (int)($input->getOption('data-size') ?? 1);

        $this->io->title('Calling All Symfony Controllers for Test Data Generation');
        $this->io->writeln("  <info>Iterations:</info> {$this->iterations}");
        $this->io->writeln("  <info>Data Size Multiplier:</info> {$this->dataSizeMultiplier}x");
        $this->io->newLine();

        try {
            $client = HttpClient::create([
                'timeout' => 60,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            // Call all controller endpoints using composite pattern
            $this->callRedisController($client);
            $this->callHttpMethodsController($client);
            $this->callComprehensiveController($client, $skipCurl);
            $this->callMySQLiController($client);
            $this->callPDOController($client);
            $this->callRequestResponseController($client);
            $this->callServiceMapController($client, $skipCurl);
            $this->callDumpController($client, $skipCurl);

            // Display summary
            $this->displaySummary();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Error executing command: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Call all Redis controller endpoints with multiple iterations and randomness
     */
    private function callRedisController($client): void
    {
        $this->io->section('Redis Profiling Test Controller');

        $baseEndpoints = [
            ['GET', '/api/test/redis/simple'],
            ['GET', '/api/test/redis/set-get'],
            ['GET', '/api/test/redis/delete'],
            ['GET', '/api/test/redis/exists'],
            ['GET', '/api/test/redis/incr-decr'],
            ['GET', '/api/test/redis/hash'],
            ['GET', '/api/test/redis/list'],
            ['GET', '/api/test/redis/set'],
            ['GET', '/api/test/redis/expire'],
            ['GET', '/api/test/redis/multiple'],
            ['GET', '/api/test/redis/comprehensive'],
        ];

        $endpoints = $baseEndpoints;

        // Add multiple iterations with random selection
        $randomEndpoints = ['/api/test/redis/simple', '/api/test/redis/set-get', '/api/test/redis/multiple', 
                           '/api/test/redis/hash', '/api/test/redis/list', '/api/test/redis/set'];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            // Randomly select 1-3 endpoints per iteration
            $count = rand(1, 3);
            for ($j = 0; $j < $count; $j++) {
                $randomEndpoint = $randomEndpoints[array_rand($randomEndpoints)];
                $endpoints[] = ['GET', $randomEndpoint];
            }
        }

        // Shuffle endpoints for randomness
        shuffle($endpoints);

        $this->callEndpoints($client, $endpoints, 'Redis');
    }

    /**
     * Call all HTTP Methods controller endpoints with various sizes
     */
    private function callHttpMethodsController($client): void
    {
        $this->io->section('HTTP Methods Test Controller');

        $endpoints = [
            ['GET', '/api/test/http/get'],
            ['POST', '/api/test/http/post', ['body' => json_encode(['test' => 'data', 'value' => 123])]],
            ['PUT', '/api/test/http/put', ['body' => json_encode(['test' => 'data', 'value' => 456])]],
            ['PATCH', '/api/test/http/patch', ['body' => json_encode(['test' => 'data', 'value' => 789])]],
            ['DELETE', '/api/test/http/delete'],
            ['HEAD', '/api/test/http/head'],
            ['OPTIONS', '/api/test/http/options'],
            ['GET', '/api/test/http/comprehensive'],
            ['POST', '/api/test/http/comprehensive', ['body' => json_encode(['test' => 'comprehensive'])]],
        ];

        // Test different sizes with multiplier
        $baseSizes = [100, 1024, 10240, 102400, 524288]; // Up to 512KB
        $sizes = array_map(fn($size) => $size * $this->dataSizeMultiplier, $baseSizes);
        
        foreach ($sizes as $size) {
            $endpoints[] = ['GET', "/api/test/http/get?size={$size}"];
            $endpoints[] = ['POST', "/api/test/http/post?response_size={$size}", ['body' => json_encode(['data' => str_repeat('x', min($size, 10000))])]];
            $endpoints[] = ['PUT', "/api/test/http/put?response_size={$size}", ['body' => json_encode(['data' => str_repeat('y', min($size, 10000))])]];
            $endpoints[] = ['PATCH', "/api/test/http/patch?response_size={$size}", ['body' => json_encode(['data' => str_repeat('z', min($size, 10000))])]];
        }

        // Add multiple iterations with various payloads and randomness
        for ($i = 0; $i < $this->iterations; $i++) {
            // Random payload size
            $payloadSize = rand(50, 200) * $this->dataSizeMultiplier;
            $payload = [
                'iteration' => $i,
                'timestamp' => time(),
                'random_id' => rand(1000, 9999),
                'data' => str_repeat('test', $payloadSize),
                'values' => range(1, rand(10, 50) * $this->dataSizeMultiplier)
            ];
            
            // Randomly select HTTP methods
            $methods = ['POST', 'PUT', 'PATCH'];
            $selectedMethod = $methods[array_rand($methods)];
            $endpoints[] = [$selectedMethod, "/api/test/http/" . strtolower($selectedMethod), ['body' => json_encode($payload)]];
            
            // Random size for GET
            $randomSize = rand(100, 10240) * $this->dataSizeMultiplier;
            $endpoints[] = ['GET', "/api/test/http/get?size={$randomSize}"];
        }
        
        // Shuffle endpoints for randomness
        shuffle($endpoints);

        $this->callEndpoints($client, $endpoints, 'HTTP Methods');
    }

    /**
     * Call comprehensive profiling controller with multiple iterations
     */
    private function callComprehensiveController($client, bool $skipCurl): void
    {
        $this->io->section('Comprehensive Profiling Test Controller');

        $endpoints = [
            ['GET', '/api/test/comprehensive'],
            ['POST', '/api/test/comprehensive', ['body' => json_encode(['test' => 'comprehensive'])]],
            ['PUT', '/api/test/comprehensive', ['body' => json_encode(['test' => 'comprehensive'])]],
            ['PATCH', '/api/test/comprehensive', ['body' => json_encode(['test' => 'comprehensive'])]],
        ];

        // Add multiple iterations with different payloads and randomness
        for ($i = 0; $i < $this->iterations; $i++) {
            // Random payload sizes
            $dataSize = rand(50, 150) * $this->dataSizeMultiplier;
            $valuesSize = rand(20, 80) * $this->dataSizeMultiplier;
            
            $payload = [
                'iteration' => $i,
                'timestamp' => time(),
                'random_id' => rand(1000, 9999),
                'test_data' => str_repeat('comprehensive', $dataSize),
                'values' => range(1, $valuesSize)
            ];
            
            $queryParams = $skipCurl ? ['skip_curl' => 'true'] : [];
            
            // Randomly select method
            $methods = ['POST', 'GET', 'PUT', 'PATCH'];
            $selectedMethod = $methods[array_rand($methods)];
            
            $endpoints[] = [$selectedMethod, '/api/test/comprehensive', [
                'body' => json_encode($payload),
                'query' => $queryParams
            ]];
        }
        
        // Shuffle endpoints for randomness
        shuffle($endpoints);

        if ($skipCurl) {
            foreach ($endpoints as &$endpoint) {
                if (!isset($endpoint[2])) {
                    $endpoint[2] = [];
                }
                if (is_array($endpoint[2])) {
                    if (!isset($endpoint[2]['query'])) {
                        $endpoint[2]['query'] = [];
                    }
                    $endpoint[2]['query']['skip_curl'] = 'true';
                }
            }
        }

        $this->callEndpoints($client, $endpoints, 'Comprehensive');
    }

    /**
     * Call all MySQLi controller endpoints with multiple iterations for big data
     */
    private function callMySQLiController($client): void
    {
        $this->io->section('MySQLi Profiling Test Controller');

        $endpoints = [
            ['GET', '/api/test/mysqli/simple'],
            ['GET', '/api/test/mysqli/create-table'],
        ];

        // Generate lots of database operations with randomness
        $dbOperations = ['insert', 'select', 'update', 'multiple', 'complex'];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            // Always do insert and select
            $endpoints[] = ['GET', '/api/test/mysqli/insert'];
            $endpoints[] = ['GET', '/api/test/mysqli/select'];
            
            // Randomly add other operations
            if (rand(1, 3) === 1) { // 33% chance
                $endpoints[] = ['GET', '/api/test/mysqli/update'];
            }
            
            if (rand(1, 4) === 1) { // 25% chance
                $endpoints[] = ['GET', '/api/test/mysqli/multiple'];
            }
            
            if (rand(1, 5) === 1) { // 20% chance
                $endpoints[] = ['GET', '/api/test/mysqli/complex'];
            }
        }
        
        // Shuffle endpoints for randomness
        shuffle($endpoints);

        // Final operations
        $endpoints[] = ['GET', '/api/test/mysqli/select'];
        $endpoints[] = ['GET', '/api/test/mysqli/complex'];
        $endpoints[] = ['GET', '/api/test/mysqli/multiple'];

        $this->callEndpoints($client, $endpoints, 'MySQLi');
    }

    /**
     * Call all PDO controller endpoints with multiple iterations for big data
     */
    private function callPDOController($client): void
    {
        $this->io->section('PDO Profiling Test Controller');

        $endpoints = [
            ['GET', '/api/test/pdo/simple'],
            ['GET', '/api/test/pdo/create-table'],
        ];

        // Generate lots of database operations with randomness
        for ($i = 0; $i < $this->iterations; $i++) {
            // Always do insert, prepare-execute, and select
            $endpoints[] = ['GET', '/api/test/pdo/insert'];
            $endpoints[] = ['GET', '/api/test/pdo/prepare-execute'];
            $endpoints[] = ['GET', '/api/test/pdo/select'];
            
            // Randomly add other operations
            if (rand(1, 2) === 1) { // 50% chance
                $endpoints[] = ['GET', '/api/test/pdo/update'];
            }
            
            if (rand(1, 3) === 1) { // 33% chance
                $endpoints[] = ['GET', '/api/test/pdo/multiple'];
            }
            
            if (rand(1, 4) === 1) { // 25% chance
                $endpoints[] = ['GET', '/api/test/pdo/transaction'];
            }
            
            if (rand(1, 5) === 1) { // 20% chance
                $endpoints[] = ['GET', '/api/test/pdo/complex'];
            }
        }
        
        // Shuffle endpoints for randomness
        shuffle($endpoints);

        // Final operations
        $endpoints[] = ['GET', '/api/test/pdo/select'];
        $endpoints[] = ['GET', '/api/test/pdo/complex'];
        $endpoints[] = ['GET', '/api/test/pdo/multiple'];
        $endpoints[] = ['GET', '/api/test/pdo/transaction'];

        $this->callEndpoints($client, $endpoints, 'PDO');
    }

    /**
     * Call all Request/Response controller endpoints with various sizes
     */
    private function callRequestResponseController($client): void
    {
        $this->io->section('Request/Response Test Controller');

        $endpoints = [
            ['GET', '/api/test/request-response'],
            ['POST', '/api/test/request-response', ['body' => json_encode(['test' => 'request', 'data' => 'test data'])]],
            ['PUT', '/api/test/request-response', ['body' => json_encode(['test' => 'request', 'data' => 'test data'])]],
            ['PATCH', '/api/test/request-response', ['body' => json_encode(['test' => 'request', 'data' => 'test data'])]],
        ];

        // Test various request sizes
        $requestSizes = [100, 1024, 2048, 5120, 10240, 20480];
        foreach ($requestSizes as $size) {
            $multipliedSize = $size * $this->dataSizeMultiplier;
            $endpoints[] = ['POST', '/api/test/request-size', ['body' => json_encode(['test' => 'data', 'size' => $multipliedSize, 'data' => str_repeat('x', min($multipliedSize, 10000))])]];
            $endpoints[] = ['PUT', '/api/test/request-size', ['body' => json_encode(['test' => 'data', 'size' => $multipliedSize, 'data' => str_repeat('y', min($multipliedSize, 10000))])]];
        }

        // Test various response sizes
        $responseSizes = [100, 1024, 2048, 5120, 10240, 20480, 51200, 102400];
        foreach ($responseSizes as $size) {
            $multipliedSize = $size * $this->dataSizeMultiplier;
            $endpoints[] = ['GET', "/api/test/response-size/{$multipliedSize}"];
        }

        // Multiple iterations with different payloads and randomness
        for ($i = 0; $i < $this->iterations; $i++) {
            // Random payload sizes
            $dataSize = rand(30, 100) * $this->dataSizeMultiplier;
            $nestedSize = rand(10, 40) * $this->dataSizeMultiplier;
            $rangeSize = rand(20, 50) * $this->dataSizeMultiplier;
            
            $payload = [
                'iteration' => $i,
                'timestamp' => time(),
                'random_id' => rand(1000, 9999),
                'data' => str_repeat('test', $dataSize),
                'nested' => [
                    'level1' => str_repeat('data', $nestedSize),
                    'level2' => range(1, $rangeSize)
                ]
            ];
            
            // Randomly select request/response endpoints
            $requestEndpoints = ['/api/test/request-response', '/api/test/full-request-response'];
            $selectedEndpoint = $requestEndpoints[array_rand($requestEndpoints)];
            $endpoints[] = ['POST', $selectedEndpoint, ['body' => json_encode($payload)]];
            
            // Random response size
            $randomResponseSize = rand(512, 10240) * $this->dataSizeMultiplier;
            $endpoints[] = ['GET', "/api/test/response-size/{$randomResponseSize}"];
        }
        
        // Shuffle endpoints for randomness
        shuffle($endpoints);

        $this->callEndpoints($client, $endpoints, 'Request/Response');
    }

    /**
     * Call service map test controller - makes HTTP calls to various hosts
     */
    private function callServiceMapController($client, bool $skipCurl): void
    {
        $this->io->section('Service Map Test Controller');

        if ($skipCurl) {
            $this->io->note('Skipping service map tests (requires cURL)');
            return;
        }

        $endpoints = [
            ['GET', '/api/test/service-map/all'],
            ['GET', '/api/test/service-map/httpbin'],
            ['GET', '/api/test/service-map/jsonplaceholder'],
            ['GET', '/api/test/service-map/reqres'],
            ['GET', '/api/test/service-map/methods'],
            ['GET', '/api/test/service-map/status-codes'],
            ['GET', '/api/test/service-map/latencies'],
        ];

        // Add multiple iterations for service map data with randomness
        $serviceMapEndpoints = [
            '/api/test/service-map/all',
            '/api/test/service-map/methods',
            '/api/test/service-map/status-codes',
            '/api/test/service-map/latencies',
            '/api/test/service-map/httpbin',
            '/api/test/service-map/jsonplaceholder',
            '/api/test/service-map/reqres'
        ];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            // Randomly select 2-4 endpoints per iteration
            $count = rand(2, 4);
            $selected = array_rand($serviceMapEndpoints, min($count, count($serviceMapEndpoints)));
            if (!is_array($selected)) {
                $selected = [$selected];
            }
            
            foreach ($selected as $index) {
                $iterations = rand(1, 3); // Random iterations per call
                $endpoints[] = ['GET', $serviceMapEndpoints[$index] . "?iterations={$iterations}&randomize=true"];
            }
        }
        
        // Shuffle endpoints for randomness
        shuffle($endpoints);

        // Comprehensive test
        $endpoints[] = ['GET', '/api/test/service-map/comprehensive'];

        $this->callEndpoints($client, $endpoints, 'Service Map');
    }

    /**
     * Call dump test controller
     */
    private function callDumpController($client, bool $skipCurl): void
    {
        $this->io->section('Dump Test Controller');

        $endpoints = [
            ['GET', '/api/dump-test'],
        ];

        if ($skipCurl) {
            $this->io->note('Skipping cURL tests in dump controller (may cause segfaults)');
        }

        $this->callEndpoints($client, $endpoints, 'Dump');
    }

    /**
     * Call a list of endpoints
     */
    private function callEndpoints($client, array $endpoints, string $category): void
    {
        $successCount = 0;
        $errorCount = 0;
        $totalCount = count($endpoints);

        $progressBar = $this->io->createProgressBar($totalCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage("Calling {$category} endpoints...");
        $progressBar->start();

        foreach ($endpoints as $endpoint) {
            $method = $endpoint[0];
            $path = $endpoint[1];
            $options = $endpoint[2] ?? [];

            $url = $this->baseUrl . $path;

            try {
                // Handle query parameters
                if (isset($options['query'])) {
                    $queryString = http_build_query($options['query']);
                    $url .= (strpos($url, '?') !== false ? '&' : '?') . $queryString;
                    unset($options['query']);
                }

                $response = $client->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $successCount++;
                    $this->results[$category][] = [
                        'method' => $method,
                        'path' => $path,
                        'status' => $statusCode,
                        'success' => true
                    ];
                } else {
                    $errorCount++;
                    $this->results[$category][] = [
                        'method' => $method,
                        'path' => $path,
                        'status' => $statusCode,
                        'success' => false
                    ];
                }

                $progressBar->setMessage("{$method} {$path} - {$statusCode}");
            } catch (\Exception $e) {
                $errorCount++;
                $this->results[$category][] = [
                    'method' => $method,
                    'path' => $path,
                    'status' => 'error',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $progressBar->setMessage("{$method} {$path} - ERROR");
            }

            $progressBar->advance();
            
            // Random sleep delay between calls (0 to 3 seconds)
            // Apply to 30% of calls to avoid making it too slow
            if (rand(1, 10) <= 3) {
                $sleepSeconds = rand(0, 3);
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
            }
        }

        $progressBar->finish();
        $this->io->newLine(2);

        $this->io->writeln("  ✓ Success: {$successCount}");
        if ($errorCount > 0) {
            $this->io->writeln("  ✗ Errors: {$errorCount}");
        }
        $this->io->newLine();
    }

    /**
     * Display summary of all calls
     */
    private function displaySummary(): void
    {
        $this->io->section('Summary');

        $totalSuccess = 0;
        $totalErrors = 0;
        $totalCalls = 0;

        foreach ($this->results as $category => $endpoints) {
            $categorySuccess = 0;
            $categoryErrors = 0;

            foreach ($endpoints as $endpoint) {
                $totalCalls++;
                if ($endpoint['success']) {
                    $totalSuccess++;
                    $categorySuccess++;
                } else {
                    $totalErrors++;
                    $categoryErrors++;
                }
            }

            $this->io->writeln("  <info>{$category}</info>: {$categorySuccess} success, {$categoryErrors} errors");
        }

        $this->io->newLine();
        $this->io->writeln("  <info>Total Calls:</info> {$totalCalls}");
        $this->io->writeln("  <info>Total Success:</info> {$totalSuccess}");
        if ($totalErrors > 0) {
            $this->io->writeln("  <error>Total Errors:</error> {$totalErrors}");
        }

        $this->io->success('All controller endpoints have been called!');
    }
}

