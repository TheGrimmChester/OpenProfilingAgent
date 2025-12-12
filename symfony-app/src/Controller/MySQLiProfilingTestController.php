<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MySQLiProfilingTestController extends AbstractController
{
    private const DB_HOST = 'mysql-symfony';
    private const DB_USER = 'symfony_user';
    private const DB_PASSWORD = 'symfony_password';
    private const DB_NAME = 'symfony_db';
    private const DB_PORT = 3306;

    #[Route('/api/test/mysqli/simple', name: 'test_mysqli_simple', methods: ['GET'])]
    public function testSimpleQuery(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_simple_query',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Simple SELECT query
            $query = "SELECT 1 as test_value, NOW() as query_time";
            $result = mysqli_query($mysqli, $query);
            
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $results['queries'][] = [
                    'query' => $query,
                    'type' => 'SELECT',
                    'result' => $row,
                    'rows' => mysqli_num_rows($result)
                ];
                mysqli_free_result($result);
            } else {
                $results['queries'][] = [
                    'query' => $query,
                    'error' => mysqli_error($mysqli)
                ];
            }

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Simple Query Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/create-table', name: 'test_mysqli_create_table', methods: ['GET'])]
    public function testCreateTable(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_create_table',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Drop table if exists
            $dropQuery = "DROP TABLE IF EXISTS test_profiling_table";
            mysqli_query($mysqli, $dropQuery);
            $results['queries'][] = [
                'query' => $dropQuery,
                'type' => 'DROP'
            ];

            // Create table
            $createQuery = "CREATE TABLE test_profiling_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $result = mysqli_query($mysqli, $createQuery);
            $results['queries'][] = [
                'query' => $createQuery,
                'type' => 'CREATE',
                'success' => $result !== false,
                'error' => $result ? null : mysqli_error($mysqli)
            ];

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Create Table Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/insert', name: 'test_mysqli_insert', methods: ['GET'])]
    public function testInsert(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_insert',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure table exists
            $this->ensureTestTable($mysqli);

            // Insert single row
            $insertQuery = "INSERT INTO test_profiling_table (name, value) VALUES ('test_insert', 'Test value for profiling')";
            $result = mysqli_query($mysqli, $insertQuery);
            
            $results['queries'][] = [
                'query' => $insertQuery,
                'type' => 'INSERT',
                'success' => $result !== false,
                'affected_rows' => $result ? mysqli_affected_rows($mysqli) : 0,
                'insert_id' => $result ? mysqli_insert_id($mysqli) : null,
                'error' => $result ? null : mysqli_error($mysqli)
            ];

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Insert Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/select', name: 'test_mysqli_select', methods: ['GET'])]
    public function testSelect(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_select',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure table exists and has data
            $this->ensureTestTable($mysqli);
            $this->ensureTestData($mysqli);

            // SELECT query
            $selectQuery = "SELECT id, name, value, created_at FROM test_profiling_table ORDER BY id DESC LIMIT 10";
            $result = mysqli_query($mysqli, $selectQuery);
            
            if ($result) {
                $rows = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                
                $results['queries'][] = [
                    'query' => $selectQuery,
                    'type' => 'SELECT',
                    'rows_returned' => mysqli_num_rows($result),
                    'data' => $rows
                ];
                mysqli_free_result($result);
            } else {
                $results['queries'][] = [
                    'query' => $selectQuery,
                    'error' => mysqli_error($mysqli)
                ];
            }

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Select Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/update', name: 'test_mysqli_update', methods: ['GET'])]
    public function testUpdate(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_update',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure table exists and has data
            $this->ensureTestTable($mysqli);
            $this->ensureTestData($mysqli);

            // UPDATE query
            $updateQuery = "UPDATE test_profiling_table SET value = 'Updated value' WHERE name = 'test_insert' LIMIT 1";
            $result = mysqli_query($mysqli, $updateQuery);
            
            $results['queries'][] = [
                'query' => $updateQuery,
                'type' => 'UPDATE',
                'success' => $result !== false,
                'affected_rows' => $result ? mysqli_affected_rows($mysqli) : 0,
                'error' => $result ? null : mysqli_error($mysqli)
            ];

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Update Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/delete', name: 'test_mysqli_delete', methods: ['GET'])]
    public function testDelete(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_delete',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure table exists and has data
            $this->ensureTestTable($mysqli);
            $this->ensureTestData($mysqli);

            // DELETE query
            $deleteQuery = "DELETE FROM test_profiling_table WHERE name = 'test_insert' LIMIT 1";
            $result = mysqli_query($mysqli, $deleteQuery);
            
            $results['queries'][] = [
                'query' => $deleteQuery,
                'type' => 'DELETE',
                'success' => $result !== false,
                'affected_rows' => $result ? mysqli_affected_rows($mysqli) : 0,
                'error' => $result ? null : mysqli_error($mysqli)
            ];

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Delete Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/multiple', name: 'test_mysqli_multiple', methods: ['GET'])]
    public function testMultipleQueries(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_multiple_queries',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure table exists
            $this->ensureTestTable($mysqli);

            // Execute multiple queries
            $queries = [
                "INSERT INTO test_profiling_table (name, value) VALUES ('multi_1', 'Value 1')",
                "INSERT INTO test_profiling_table (name, value) VALUES ('multi_2', 'Value 2')",
                "INSERT INTO test_profiling_table (name, value) VALUES ('multi_3', 'Value 3')",
                "SELECT COUNT(*) as total FROM test_profiling_table",
                "UPDATE test_profiling_table SET value = 'Updated' WHERE name LIKE 'multi_%'"
            ];

            foreach ($queries as $query) {
                $result = mysqli_query($mysqli, $query);
                
                if ($result && is_object($result)) {
                    // SELECT query
                    $row = mysqli_fetch_assoc($result);
                    $results['queries'][] = [
                        'query' => $query,
                        'type' => 'SELECT',
                        'result' => $row,
                        'rows' => mysqli_num_rows($result)
                    ];
                    mysqli_free_result($result);
                } else {
                    // INSERT/UPDATE/DELETE query
                    $results['queries'][] = [
                        'query' => $query,
                        'type' => strtoupper(substr(trim($query), 0, 6)),
                        'success' => $result !== false,
                        'affected_rows' => $result ? mysqli_affected_rows($mysqli) : 0,
                        'error' => $result ? null : mysqli_error($mysqli)
                    ];
                }
            }

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Multiple Queries Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    #[Route('/api/test/mysqli/complex', name: 'test_mysqli_complex', methods: ['GET'])]
    public function testComplexQuery(Request $request): JsonResponse
    {
        $results = [
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => 'mysqli_complex_query',
            'queries' => []
        ];

        try {
            $mysqli = new \mysqli(self::DB_HOST, self::DB_USER, self::DB_PASSWORD, self::DB_NAME, self::DB_PORT);
            
            if ($mysqli->connect_error) {
                throw new \Exception('Connection failed: ' . $mysqli->connect_error);
            }

            // Ensure table exists and has data
            $this->ensureTestTable($mysqli);
            $this->ensureTestData($mysqli);

            // Complex query with JOIN, WHERE, ORDER BY, LIMIT
            $complexQuery = "SELECT 
                t1.id,
                t1.name,
                t1.value,
                COUNT(*) as count
            FROM test_profiling_table t1
            WHERE t1.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            GROUP BY t1.id, t1.name, t1.value
            ORDER BY t1.id DESC
            LIMIT 5";
            
            $result = mysqli_query($mysqli, $complexQuery);
            
            if ($result) {
                $rows = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                
                $results['queries'][] = [
                    'query' => $complexQuery,
                    'type' => 'SELECT',
                    'rows_returned' => mysqli_num_rows($result),
                    'data' => $rows
                ];
                mysqli_free_result($result);
            } else {
                $results['queries'][] = [
                    'query' => $complexQuery,
                    'error' => mysqli_error($mysqli)
                ];
            }

            mysqli_close($mysqli);
            $results['status'] = 'success';
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        if (function_exists('opa_dump')) {
            opa_dump('MySQLi Complex Query Test', $results);
        }

        return new JsonResponse($results, 200);
    }

    /**
     * Ensure test table exists
     */
    private function ensureTestTable(\mysqli $mysqli): void
    {
        $createQuery = "CREATE TABLE IF NOT EXISTS test_profiling_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        mysqli_query($mysqli, $createQuery);
    }

    /**
     * Ensure test data exists
     */
    private function ensureTestData(\mysqli $mysqli): void
    {
        // Check if data exists
        $checkQuery = "SELECT COUNT(*) as count FROM test_profiling_table";
        $result = mysqli_query($mysqli, $checkQuery);
        
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            if ($row['count'] == 0) {
                // Insert test data
                mysqli_query($mysqli, "INSERT INTO test_profiling_table (name, value) VALUES 
                    ('test_1', 'Test value 1'),
                    ('test_2', 'Test value 2'),
                    ('test_3', 'Test value 3')");
            }
            mysqli_free_result($result);
        }
    }
}

