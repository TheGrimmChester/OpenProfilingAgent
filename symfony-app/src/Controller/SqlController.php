<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SqlController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    #[Route('/api/sql/pdo', name: 'sql_pdo', methods: ['GET'])]
    public function testPdo(): JsonResponse
    {
        // Test PDO queries - automatically instrumented by OPA
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        // INSERT
        $this->connection->executeStatement(
            'INSERT INTO test_users (name, email) VALUES (?, ?)',
            ['John Doe', 'john@example.com']
        );

        // SELECT
        $users = $this->connection->fetchAllAssociative('SELECT * FROM test_users LIMIT 10');

        // UPDATE
        $this->connection->executeStatement(
            'UPDATE test_users SET email = ? WHERE name = ?',
            ['john.updated@example.com', 'John Doe']
        );

        // COUNT
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM test_users');

        return new JsonResponse([
            'status' => 'success',
            'users' => $users,
            'count' => $count,
            'message' => 'PDO queries executed successfully'
        ]);
    }

    #[Route('/api/sql/mysqli', name: 'sql_mysqli', methods: ['GET'])]
    public function testMysqli(): JsonResponse
    {
        // Parse DATABASE_URL or use defaults
        $databaseUrl = $_ENV['DATABASE_URL'] ?? 'mysql://symfony_user:symfony_password@mysql-symfony:3306/symfony_db';
        $parsed = parse_url($databaseUrl);
        $host = $parsed['host'] ?? 'mysql-symfony';
        $port = $parsed['port'] ?? 3306;
        $db = ltrim($parsed['path'] ?? '/symfony_db', '/');
        $user = $parsed['user'] ?? 'symfony_user';
        $pass = $parsed['pass'] ?? 'symfony_password';

        // Test MySQLi queries - automatically instrumented by OPA
        $mysqli = new \mysqli($host, $user, $pass, $db, $port);

        if ($mysqli->connect_error) {
            return new JsonResponse(['error' => $mysqli->connect_error], 500);
        }

        $mysqli->query('CREATE TABLE IF NOT EXISTS test_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            category VARCHAR(50)
        )');

        $mysqli->query("INSERT INTO test_products (name, price, category) VALUES 
            ('Product A', 19.99, 'Electronics'),
            ('Product B', 29.99, 'Clothing'),
            ('Product C', 39.99, 'Electronics')");

        $result = $mysqli->query('SELECT * FROM test_products');
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();

        $countResult = $mysqli->query('SELECT COUNT(*) as total FROM test_products');
        $count = $countResult->fetch_assoc()['total'];
        $countResult->free();

        $mysqli->close();

        return new JsonResponse([
            'status' => 'success',
            'products' => $products,
            'count' => $count,
            'message' => 'MySQLi queries executed successfully'
        ]);
    }

    #[Route('/api/sql/prepared', name: 'sql_prepared', methods: ['GET'])]
    public function testPreparedStatements(): JsonResponse
    {
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS test_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(100),
            amount DECIMAL(10,2),
            status VARCHAR(20)
        )');

        // Prepared statement with PDO
        $stmt = $this->connection->prepare('INSERT INTO test_orders (customer_name, amount, status) VALUES (?, ?, ?)');
        $stmt->executeStatement(['Alice', 100.50, 'pending']);
        $stmt->executeStatement(['Bob', 200.75, 'completed']);
        $stmt->executeStatement(['Charlie', 150.25, 'pending']);

        $orders = $this->connection->fetchAllAssociative(
            'SELECT * FROM test_orders WHERE status = ?',
            ['pending']
        );

        return new JsonResponse([
            'status' => 'success',
            'orders' => $orders,
            'message' => 'Prepared statements executed successfully'
        ]);
    }
}

