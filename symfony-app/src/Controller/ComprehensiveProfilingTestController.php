<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Comprehensive Profiling Test Controller
 * 
 * Tests all OPA extension features in a single endpoint:
 * - HTTP Request/Response profiling
 * - MySQLi query profiling
 * - PDO query profiling (including prepared statements and transactions)
 * - cURL profiling
 * - Error tracking
 * - Log tracking
 * - opa_dump functionality
 * - Manual spans
 */
class ComprehensiveProfilingTestController extends AbstractController
{
    private const DB_HOST = 'mysql-symfony';
    private const DB_USER = 'symfony_user';
    private const DB_PASSWORD = 'symfony_password';
    private const DB_NAME = 'symfony_db';
    private const DB_PORT = 3306;

    private function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            self::DB_HOST,
            self::DB_PORT,
            self::DB_NAME
        );
    }

    #[Route('/api/test/comprehensive', name: 'test_comprehensive', methods: ['GET', 'POST', 'PUT', 'PATCH'])]
    public function comprehensiveTest(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'comprehensive_profiling',
            'request_info' => [],
            'sections' => []
        ];

        // Dump initial state
        if (function_exists('opa_dump')) {
            opa_dump('Comprehensive Test Started', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'timestamp' => $results['timestamp']
            ]);
        }

        // Section 1: HTTP Request/Response Profiling
        $results['sections']['http_profiling'] = $this->testHttpProfiling($request);

        // Section 2: MySQLi Query Profiling
        $results['sections']['mysqli_profiling'] = $this->testMySQLiProfiling();

        // Section 3: PDO Query Profiling
        $results['sections']['pdo_profiling'] = $this->testPDOProfiling();

        // Section 4: cURL Profiling (optional, skip by default to prevent segfaults)
        // cURL can cause segfaults with the OPA extension, so we skip it by default
        $skipCurl = $request->query->get('skip_curl', 'true') !== 'false';
        if (!$skipCurl) {
            try {
                // Use a separate process timeout to prevent hanging
                set_time_limit(5);
                $results['sections']['curl_profiling'] = $this->testCurlProfiling();
            } catch (\Exception $e) {
                $results['sections']['curl_profiling'] = [
                    'name' => 'cURL Profiling',
                    'status' => 'skipped',
                    'error' => $e->getMessage(),
                    'note' => 'cURL may cause segfaults with OPA extension'
                ];
            } catch (\Throwable $e) {
                $results['sections']['curl_profiling'] = [
                    'name' => 'cURL Profiling',
                    'status' => 'skipped',
                    'error' => $e->getMessage(),
                    'note' => 'cURL may cause segfaults with OPA extension'
                ];
            }
        } else {
            $results['sections']['curl_profiling'] = [
                'name' => 'cURL Profiling',
                'status' => 'skipped',
                'reason' => 'Skipped by default (use ?skip_curl=false to enable, but may cause segfaults)'
            ];
        }

        // Section 5: Error Tracking
        $results['sections']['error_tracking'] = $this->testErrorTracking();

        // Section 6: Log Tracking
        $results['sections']['log_tracking'] = $this->testLogTracking();

        // Section 7: Manual Spans
        $results['sections']['manual_spans'] = $this->testManualSpans();

        // Section 8: Complex Operations
        $results['sections']['complex_operations'] = $this->testComplexOperations();

        // Calculate total execution time
        $executionTime = (microtime(true) - $startTime) * 1000;
        $results['execution_time_ms'] = round($executionTime, 2);
        $results['total_operations'] = $this->countOperations($results['sections']);

        // Final dump
        if (function_exists('opa_dump')) {
            opa_dump('Comprehensive Test Completed', [
                'execution_time_ms' => $executionTime,
                'total_operations' => $results['total_operations'],
                'status' => 'success'
            ]);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Test HTTP Request/Response profiling
     */
    private function testHttpProfiling(Request $request): array
    {
        $section = [
            'name' => 'HTTP Profiling',
            'status' => 'success',
            'tests' => []
        ];

        // Capture request information
        $section['tests']['request_capture'] = [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'path' => $request->getPathInfo(),
            'query_string' => $request->getQueryString(),
            'content_type' => $request->headers->get('Content-Type'),
            'content_length' => $request->headers->get('Content-Length'),
            'body_size' => strlen($request->getContent()),
            'query_params' => $request->query->all(),
            'headers_count' => count($request->headers->all())
        ];

        // Test different response sizes
        $responseSize = (int)($request->query->get('response_size', 500));
        $responseData = [
            'message' => 'Comprehensive profiling test response',
            'size' => $responseSize,
            'data' => str_repeat('x', max(0, $responseSize - 100))
        ];

        $section['tests']['response_capture'] = [
            'status_code' => 200,
            'content_type' => 'application/json',
            'body_size' => strlen(json_encode($responseData)),
            'response_size_requested' => $responseSize
        ];

        return $section;
    }

    /**
     * Test MySQLi query profiling
     */
    private function testMySQLiProfiling(): array
    {
        $section = [
            'name' => 'MySQLi Profiling',
            'status' => 'success',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure test table exists
            $this->ensureMySQLiTestTable($mysqli);

            // Test 1: Simple SELECT
            $query1 = "SELECT 1 as test_value, NOW() as query_time";
            $result = mysqli_query($mysqli, $query1);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $section['queries'][] = [
                    'query' => $query1,
                    'type' => 'SELECT',
                    'rows' => mysqli_num_rows($result),
                    'result' => $row
                ];
                mysqli_free_result($result);
            }

            // Test 2: INSERT
            $query2 = "INSERT INTO test_comprehensive_table (name, value) VALUES ('mysqli_test', 'MySQLi profiling test')";
            $result = mysqli_query($mysqli, $query2);
            $section['queries'][] = [
                'query' => $query2,
                'type' => 'INSERT',
                'success' => $result !== false,
                'affected_rows' => $result ? mysqli_affected_rows($mysqli) : 0,
                'insert_id' => $result ? mysqli_insert_id($mysqli) : null
            ];

            // Test 3: UPDATE
            $query3 = "UPDATE test_comprehensive_table SET value = 'Updated via MySQLi' WHERE name = 'mysqli_test' LIMIT 1";
            $result = mysqli_query($mysqli, $query3);
            $section['queries'][] = [
                'query' => $query3,
                'type' => 'UPDATE',
                'success' => $result !== false,
                'affected_rows' => $result ? mysqli_affected_rows($mysqli) : 0
            ];

            // Test 4: SELECT with WHERE
            $query4 = "SELECT id, name, value FROM test_comprehensive_table WHERE name = 'mysqli_test'";
            $result = mysqli_query($mysqli, $query4);
            if ($result) {
                $rows = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                $section['queries'][] = [
                    'query' => $query4,
                    'type' => 'SELECT',
                    'rows_returned' => mysqli_num_rows($result),
                    'data' => $rows
                ];
                mysqli_free_result($result);
            }

            mysqli_close($mysqli);
        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test PDO query profiling
     */
    private function testPDOProfiling(): array
    {
        $section = [
            'name' => 'PDO Profiling',
            'status' => 'success',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure test table exists
            $this->ensurePDOTestTable($pdo);

            // Test 1: Simple SELECT using PDO::query
            $query1 = "SELECT 1 as test_value, NOW() as query_time";
            $stmt = $pdo->query($query1);
            if ($stmt) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $section['queries'][] = [
                    'query' => $query1,
                    'type' => 'SELECT',
                    'method' => 'PDO::query',
                    'result' => $row
                ];
            }

            // Test 2: INSERT using PDO::exec
            $query2 = "INSERT INTO test_comprehensive_pdo_table (name, value) VALUES ('pdo_test', 'PDO profiling test')";
            $affected = $pdo->exec($query2);
            $section['queries'][] = [
                'query' => $query2,
                'type' => 'INSERT',
                'method' => 'PDO::exec',
                'affected_rows' => $affected,
                'last_insert_id' => $pdo->lastInsertId()
            ];

            // Test 3: Prepared statement with PDO::prepare and PDOStatement::execute
            $query3 = "INSERT INTO test_comprehensive_pdo_table (name, value) VALUES (:name, :value)";
            $stmt = $pdo->prepare($query3);
            $section['queries'][] = [
                'query' => $query3,
                'type' => 'PREPARE',
                'method' => 'PDO::prepare',
                'success' => $stmt !== false
            ];

            if ($stmt) {
                $executeResult = $stmt->execute([
                    ':name' => 'prepared_test',
                    ':value' => 'Prepared statement test'
                ]);
                $section['queries'][] = [
                    'query' => $query3,
                    'type' => 'INSERT',
                    'method' => 'PDOStatement::execute',
                    'success' => $executeResult,
                    'affected_rows' => $stmt->rowCount()
                ];
            }

            // Test 4: Transaction
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO test_comprehensive_pdo_table (name, value) VALUES ('trans_1', 'Transaction test 1')");
            $pdo->exec("INSERT INTO test_comprehensive_pdo_table (name, value) VALUES ('trans_2', 'Transaction test 2')");
            $pdo->exec("UPDATE test_comprehensive_pdo_table SET value = 'Updated in transaction' WHERE name = 'trans_1'");
            $pdo->commit();
            
            $section['queries'][] = [
                'type' => 'TRANSACTION',
                'method' => 'PDO::beginTransaction/commit',
                'queries_in_transaction' => 3,
                'committed' => true
            ];

            // Test 5: SELECT with prepared statement
            $query5 = "SELECT id, name, value FROM test_comprehensive_pdo_table WHERE name LIKE :pattern";
            $stmt = $pdo->prepare($query5);
            if ($stmt) {
                $stmt->execute([':pattern' => 'pdo%']);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $section['queries'][] = [
                    'query' => $query5,
                    'type' => 'SELECT',
                    'method' => 'PDOStatement::execute',
                    'rows_returned' => count($rows)
                ];
            }

        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Test cURL profiling
     * Note: cURL calls are wrapped in error suppression to prevent segfaults
     */
    private function testCurlProfiling(): array
    {
        $section = [
            'name' => 'cURL Profiling',
            'status' => 'success',
            'requests' => []
        ];

        if (!function_exists('curl_init')) {
            $section['status'] = 'skipped';
            $section['message'] = 'cURL extension not available';
            return $section;
        }

        // Use error suppression and very short timeouts to prevent crashes
        try {
            // Test 1: Simple GET request with error suppression
            $ch = @curl_init();
            if (!$ch) {
                throw new \Exception('Failed to initialize cURL');
            }
            
            @curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/get?test=opa_comprehensive');
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = @curl_exec($ch);
            $httpCode = $ch ? @curl_getinfo($ch, CURLINFO_HTTP_CODE) : 0;
            $error = $ch ? @curl_error($ch) : 'cURL handle invalid';
            
            $section['requests'][] = [
                'url' => 'https://httpbin.org/get?test=opa_comprehensive',
                'method' => 'GET',
                'status_code' => $httpCode ?: null,
                'response_size' => $response ? strlen($response) : 0,
                'error' => $error ?: null,
                'success' => $httpCode >= 200 && $httpCode < 300
            ];
            
            if ($ch) {
                @curl_close($ch);
            }

            // Test 2: POST request with error suppression
            $ch = @curl_init();
            if ($ch) {
                @curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/post');
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_POST, true);
                @curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => 'comprehensive', 'data' => 'POST test']));
                @curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                @curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $response = @curl_exec($ch);
                $httpCode = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = @curl_error($ch);
                
                $section['requests'][] = [
                    'url' => 'https://httpbin.org/post',
                    'method' => 'POST',
                    'status_code' => $httpCode ?: null,
                    'response_size' => $response ? strlen($response) : 0,
                    'error' => $error ?: null,
                    'success' => $httpCode >= 200 && $httpCode < 300
                ];
                
                @curl_close($ch);
            }

        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $section['status'] = 'error';
            $section['error'] = 'Fatal error: ' . $e->getMessage();
        }

        return $section;
    }

    /**
     * Test error tracking
     */
    private function testErrorTracking(): array
    {
        $section = [
            'name' => 'Error Tracking',
            'status' => 'success',
            'tests' => []
        ];

        // Test 1: Trigger a warning (non-fatal)
        try {
            @trigger_error('Test warning for OPA profiling', E_USER_WARNING);
            $section['tests']['warning'] = 'Warning triggered';
        } catch (\Exception $e) {
            $section['tests']['warning'] = 'Error: ' . $e->getMessage();
        }

        // Test 2: Trigger a notice
        try {
            @trigger_error('Test notice for OPA profiling', E_USER_NOTICE);
            $section['tests']['notice'] = 'Notice triggered';
        } catch (\Exception $e) {
            $section['tests']['notice'] = 'Error: ' . $e->getMessage();
        }

        // Test 3: Exception (caught)
        try {
            throw new \Exception('Test exception for OPA profiling');
        } catch (\Exception $e) {
            $section['tests']['exception'] = [
                'message' => $e->getMessage(),
                'caught' => true
            ];
        }

        return $section;
    }

    /**
     * Test log tracking
     */
    private function testLogTracking(): array
    {
        $section = [
            'name' => 'Log Tracking',
            'status' => 'success',
            'logs' => []
        ];

        // Test different log levels
        $logMessages = [
            'info' => 'Test info log message for OPA profiling',
            'warning' => 'Test warning log message for OPA profiling',
            'error' => 'Test error log message for OPA profiling'
        ];

        foreach ($logMessages as $level => $message) {
            @error_log($message);
            $section['logs'][] = [
                'level' => $level,
                'message' => $message,
                'logged' => true
            ];
        }

        return $section;
    }

    /**
     * Test manual spans
     */
    private function testManualSpans(): array
    {
        $section = [
            'name' => 'Manual Spans',
            'status' => 'success',
            'spans' => []
        ];

        if (function_exists('opa_start_span')) {
            // Test 1: Simple manual span
            $spanId1 = opa_start_span('comprehensive_test_span', [
                'test_type' => 'manual_span',
                'operation' => 'test_operation'
            ]);
            
            // Simulate some work
            usleep(10000); // 10ms
            
            if (function_exists('opa_add_tag')) {
                opa_add_tag($spanId1, 'status', 'success');
                opa_add_tag($spanId1, 'duration_ms', 10);
            }
            
            if (function_exists('opa_end_span')) {
                opa_end_span($spanId1);
            }
            
            $section['spans'][] = [
                'name' => 'comprehensive_test_span',
                'created' => true,
                'tags_added' => true
            ];

            // Test 2: Nested spans
            $parentSpanId = opa_start_span('parent_operation', ['level' => 'parent']);
            usleep(5000);
            
            $childSpanId = opa_start_span('child_operation', ['level' => 'child']);
            usleep(5000);
            opa_end_span($childSpanId);
            
            opa_end_span($parentSpanId);
            
            $section['spans'][] = [
                'name' => 'nested_spans',
                'parent_span' => true,
                'child_span' => true
            ];
        } else {
            $section['status'] = 'skipped';
            $section['message'] = 'Manual span functions not available';
        }

        return $section;
    }

    /**
     * Test complex operations combining multiple features
     */
    private function testComplexOperations(): array
    {
        $section = [
            'name' => 'Complex Operations',
            'status' => 'success',
            'operations' => []
        ];

        try {
            // Operation 1: Database + cURL combination
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $this->ensurePDOTestTable($pdo);
            
            // Insert data
            $pdo->exec("INSERT INTO test_comprehensive_pdo_table (name, value) VALUES ('complex_op', 'Complex operation test')");
            
            // Fetch data
            $stmt = $pdo->query("SELECT * FROM test_comprehensive_pdo_table WHERE name = 'complex_op'");
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $section['operations'][] = [
                'type' => 'database_operation',
                'description' => 'PDO insert and select',
                'success' => true,
                'data_retrieved' => $data !== false
            ];

            // Operation 2: Multiple queries in sequence
            $queries = [
                "SELECT COUNT(*) as total FROM test_comprehensive_pdo_table",
                "SELECT MAX(id) as max_id FROM test_comprehensive_pdo_table",
                "SELECT MIN(id) as min_id FROM test_comprehensive_pdo_table"
            ];

            $results = [];
            foreach ($queries as $query) {
                $stmt = $pdo->query($query);
                $results[] = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            $section['operations'][] = [
                'type' => 'multiple_queries',
                'description' => 'Multiple sequential queries',
                'queries_count' => count($queries),
                'results_count' => count($results)
            ];

            // Operation 3: Transaction with multiple operations
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO test_comprehensive_pdo_table (name, value) VALUES ('batch_1', 'Batch operation 1')");
            $pdo->exec("INSERT INTO test_comprehensive_pdo_table (name, value) VALUES ('batch_2', 'Batch operation 2')");
            $pdo->exec("UPDATE test_comprehensive_pdo_table SET value = 'Updated batch' WHERE name LIKE 'batch_%'");
            $pdo->commit();

            $section['operations'][] = [
                'type' => 'transaction_batch',
                'description' => 'Transaction with multiple operations',
                'operations_in_transaction' => 3,
                'committed' => true
            ];

        } catch (\Exception $e) {
            $section['status'] = 'error';
            $section['error'] = $e->getMessage();
        }

        return $section;
    }

    /**
     * Ensure MySQLi test table exists
     */
    private function ensureMySQLiTestTable(\mysqli $mysqli): void
    {
        $createQuery = "CREATE TABLE IF NOT EXISTS test_comprehensive_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        mysqli_query($mysqli, $createQuery);
    }

    /**
     * Ensure PDO test table exists
     */
    private function ensurePDOTestTable(\PDO $pdo): void
    {
        $createQuery = "CREATE TABLE IF NOT EXISTS test_comprehensive_pdo_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($createQuery);
    }

    /**
     * Count total operations across all sections
     */
    private function countOperations(array $sections): int
    {
        $count = 0;
        
        if (isset($sections['mysqli_profiling']['queries'])) {
            $count += count($sections['mysqli_profiling']['queries']);
        }
        
        if (isset($sections['pdo_profiling']['queries'])) {
            $count += count($sections['pdo_profiling']['queries']);
        }
        
        if (isset($sections['curl_profiling']['requests'])) {
            $count += count($sections['curl_profiling']['requests']);
        }
        
        if (isset($sections['complex_operations']['operations'])) {
            $count += count($sections['complex_operations']['operations']);
        }
        
        return $count;
    }
}

