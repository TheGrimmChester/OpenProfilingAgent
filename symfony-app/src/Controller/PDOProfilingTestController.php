<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PDOProfilingTestController extends AbstractController
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

    #[Route('/api/test/pdo/simple', name: 'test_pdo_simple', methods: ['GET'])]
    public function testSimpleQuery(Request $request): JsonResponse
    {
        // Set W3C Trace Context if headers are present
        if (function_exists('opa_set_w3c_context')) {
            $traceparent = $request->headers->get('traceparent');
            $tracestate = $request->headers->get('tracestate');
            if ($traceparent) {
                opa_set_w3c_context($traceparent, $tracestate);
            }
        }
        
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_simple_query',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Simple SELECT query using PDO::query
            $query = "SELECT 1 as test_value, NOW() as query_time";
            $stmt = $pdo->query($query);
            
            if ($stmt) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $results['queries'][] = [
                    'query' => $query,
                    'type' => 'SELECT',
                    'method' => 'PDO::query',
                    'result' => $row,
                    'row_count' => $stmt->rowCount()
                ];
            }

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Simple Query Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/create-table', name: 'test_pdo_create_table', methods: ['GET'])]
    public function testCreateTable(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_create_table',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Drop table if exists using PDO::exec
            $dropQuery = "DROP TABLE IF EXISTS test_pdo_profiling_table";
            $affected = $pdo->exec($dropQuery);
            $results['queries'][] = [
                'query' => $dropQuery,
                'type' => 'DROP',
                'method' => 'PDO::exec',
                'affected_rows' => $affected
            ];

            // Create table using PDO::exec
            $createQuery = "CREATE TABLE test_pdo_profiling_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $affected = $pdo->exec($createQuery);
            $results['queries'][] = [
                'query' => $createQuery,
                'type' => 'CREATE',
                'method' => 'PDO::exec',
                'affected_rows' => $affected,
                'success' => true
            ];

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Create Table Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/insert', name: 'test_pdo_insert', methods: ['GET'])]
    public function testInsert(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_insert',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists
            $this->ensureTestTable($pdo);

            // Insert using PDO::exec
            $insertQuery = "INSERT INTO test_pdo_profiling_table (name, value) VALUES ('test_insert', 'Test value for PDO profiling')";
            $affected = $pdo->exec($insertQuery);
            
            $results['queries'][] = [
                'query' => $insertQuery,
                'type' => 'INSERT',
                'method' => 'PDO::exec',
                'affected_rows' => $affected,
                'last_insert_id' => $pdo->lastInsertId()
            ];

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Insert Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/prepare-execute', name: 'test_pdo_prepare_execute', methods: ['GET'])]
    public function testPrepareExecute(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_prepare_execute',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists
            $this->ensureTestTable($pdo);

            // Prepare and execute INSERT
            $insertQuery = "INSERT INTO test_pdo_profiling_table (name, value) VALUES (:name, :value)";
            $stmt = $pdo->prepare($insertQuery);
            
            $results['queries'][] = [
                'query' => $insertQuery,
                'type' => 'PREPARE',
                'method' => 'PDO::prepare',
                'success' => $stmt !== false
            ];

            if ($stmt) {
                $executeResult = $stmt->execute([
                    ':name' => 'prepared_insert',
                    ':value' => 'Value from prepared statement'
                ]);
                
                $results['queries'][] = [
                    'query' => $insertQuery,
                    'type' => 'INSERT',
                    'method' => 'PDOStatement::execute',
                    'success' => $executeResult,
                    'affected_rows' => $stmt->rowCount(),
                    'last_insert_id' => $pdo->lastInsertId()
                ];
            }

            // Prepare and execute SELECT
            $selectQuery = "SELECT id, name, value FROM test_pdo_profiling_table WHERE name = :name";
            $stmt = $pdo->prepare($selectQuery);
            
            if ($stmt) {
                $executeResult = $stmt->execute([':name' => 'prepared_insert']);
                
                if ($executeResult) {
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $results['queries'][] = [
                        'query' => $selectQuery,
                        'type' => 'SELECT',
                        'method' => 'PDOStatement::execute',
                        'rows_returned' => count($rows),
                        'data' => $rows
                    ];
                }
            }

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Prepare/Execute Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/select', name: 'test_pdo_select', methods: ['GET'])]
    public function testSelect(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_select',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists and has data
            $this->ensureTestTable($pdo);
            $this->ensureTestData($pdo);

            // SELECT query using PDO::query
            $selectQuery = "SELECT id, name, value, created_at FROM test_pdo_profiling_table ORDER BY id DESC LIMIT 10";
            $stmt = $pdo->query($selectQuery);
            
            if ($stmt) {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $results['queries'][] = [
                    'query' => $selectQuery,
                    'type' => 'SELECT',
                    'method' => 'PDO::query',
                    'rows_returned' => count($rows),
                    'data' => $rows
                ];
            }

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Select Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/update', name: 'test_pdo_update', methods: ['GET'])]
    public function testUpdate(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_update',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists and has data
            $this->ensureTestTable($pdo);
            $this->ensureTestData($pdo);

            // UPDATE query using PDO::exec
            $updateQuery = "UPDATE test_pdo_profiling_table SET value = 'Updated value' WHERE name = 'test_insert' LIMIT 1";
            $affected = $pdo->exec($updateQuery);
            
            $results['queries'][] = [
                'query' => $updateQuery,
                'type' => 'UPDATE',
                'method' => 'PDO::exec',
                'affected_rows' => $affected
            ];

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Update Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/delete', name: 'test_pdo_delete', methods: ['GET'])]
    public function testDelete(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_delete',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists and has data
            $this->ensureTestTable($pdo);
            $this->ensureTestData($pdo);

            // DELETE query using PDO::exec
            $deleteQuery = "DELETE FROM test_pdo_profiling_table WHERE name = 'test_insert' LIMIT 1";
            $affected = $pdo->exec($deleteQuery);
            
            $results['queries'][] = [
                'query' => $deleteQuery,
                'type' => 'DELETE',
                'method' => 'PDO::exec',
                'affected_rows' => $affected
            ];

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Delete Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/multiple', name: 'test_pdo_multiple', methods: ['GET'])]
    public function testMultipleQueries(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_multiple_queries',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists
            $this->ensureTestTable($pdo);

            // Execute multiple queries using different methods
            $queries = [
                ['query' => "INSERT INTO test_pdo_profiling_table (name, value) VALUES ('multi_1', 'Value 1')", 'method' => 'exec'],
                ['query' => "INSERT INTO test_pdo_profiling_table (name, value) VALUES ('multi_2', 'Value 2')", 'method' => 'exec'],
                ['query' => "INSERT INTO test_pdo_profiling_table (name, value) VALUES ('multi_3', 'Value 3')", 'method' => 'exec'],
                ['query' => "SELECT COUNT(*) as total FROM test_pdo_profiling_table", 'method' => 'query'],
                ['query' => "UPDATE test_pdo_profiling_table SET value = 'Updated' WHERE name LIKE 'multi_%'", 'method' => 'exec']
            ];

            foreach ($queries as $queryInfo) {
                $query = $queryInfo['query'];
                $method = $queryInfo['method'];
                
                if ($method === 'query') {
                    $stmt = $pdo->query($query);
                    if ($stmt) {
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        $results['queries'][] = [
                            'query' => $query,
                            'type' => 'SELECT',
                            'method' => 'PDO::query',
                            'result' => $row
                        ];
                    }
                } else {
                    $affected = $pdo->exec($query);
                    $results['queries'][] = [
                        'query' => $query,
                        'type' => strtoupper(substr(trim($query), 0, 6)),
                        'method' => 'PDO::exec',
                        'affected_rows' => $affected
                    ];
                }
            }

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Multiple Queries Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/complex', name: 'test_pdo_complex', methods: ['GET'])]
    public function testComplexQuery(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_complex_query',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists and has data
            $this->ensureTestTable($pdo);
            $this->ensureTestData($pdo);

            // Complex query with WHERE, GROUP BY, ORDER BY, LIMIT
            $complexQuery = "SELECT 
                t1.id,
                t1.name,
                t1.value,
                COUNT(*) as count
            FROM test_pdo_profiling_table t1
            WHERE t1.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            GROUP BY t1.id, t1.name, t1.value
            ORDER BY t1.id DESC
            LIMIT 5";
            
            $stmt = $pdo->query($complexQuery);
            
            if ($stmt) {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $results['queries'][] = [
                    'query' => $complexQuery,
                    'type' => 'SELECT',
                    'method' => 'PDO::query',
                    'rows_returned' => count($rows),
                    'data' => $rows
                ];
            }

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Complex Query Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/pdo/transaction', name: 'test_pdo_transaction', methods: ['GET'])]
    public function testTransaction(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'pdo_transaction',
            'queries' => []
        ];

        try {
            $pdo = new \PDO($this->getDsn(), self::DB_USER, self::DB_PASSWORD);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Ensure table exists
            $this->ensureTestTable($pdo);

            // Start transaction
            $pdo->beginTransaction();

            // Execute multiple queries within transaction
            $queries = [
                "INSERT INTO test_pdo_profiling_table (name, value) VALUES ('trans_1', 'Transaction value 1')",
                "INSERT INTO test_pdo_profiling_table (name, value) VALUES ('trans_2', 'Transaction value 2')",
                "UPDATE test_pdo_profiling_table SET value = 'Updated in transaction' WHERE name = 'trans_1'"
            ];

            foreach ($queries as $query) {
                $affected = $pdo->exec($query);
                $results['queries'][] = [
                    'query' => $query,
                    'type' => strtoupper(substr(trim($query), 0, 6)),
                    'method' => 'PDO::exec',
                    'affected_rows' => $affected,
                    'in_transaction' => true
                ];
            }

            // Commit transaction
            $pdo->commit();
            $results['transaction_committed'] = true;

            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
                $results['transaction_rolled_back'] = true;
            }
        }

        if (function_exists('opa_dump')) {
            opa_dump('PDO Transaction Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Ensure test table exists
     */
    private function ensureTestTable(\PDO $pdo): void
    {
        $createQuery = "CREATE TABLE IF NOT EXISTS test_pdo_profiling_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($createQuery);
    }

    /**
     * Ensure test data exists
     */
    private function ensureTestData(\PDO $pdo): void
    {
        // Check if data exists
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM test_pdo_profiling_table");
        
        if ($stmt) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['count'] == 0) {
                // Insert test data
                $pdo->exec("INSERT INTO test_pdo_profiling_table (name, value) VALUES 
                    ('test_1', 'Test value 1'),
                    ('test_2', 'Test value 2'),
                    ('test_3', 'Test value 3')");
            }
        }
    }
}

