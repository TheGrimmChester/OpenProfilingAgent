<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    #[Route('/api/files/read', name: 'files_read', methods: ['GET'])]
    public function testFileRead(): JsonResponse
    {
        // Test file I/O operations - automatically instrumented by OPA
        $testFile = sys_get_temp_dir() . '/opa_test_' . uniqid() . '.txt';
        file_put_contents($testFile, 'Test content for file I/O operations');

        $content = file_get_contents($testFile);
        $size = filesize($testFile);
        $exists = file_exists($testFile);

        unlink($testFile);

        return new JsonResponse([
            'status' => 'success',
            'content' => $content,
            'size' => $size,
            'exists' => $exists,
            'message' => 'File read operations completed'
        ]);
    }

    #[Route('/api/files/write', name: 'files_write', methods: ['GET'])]
    public function testFileWrite(): JsonResponse
    {
        $testFile = sys_get_temp_dir() . '/opa_test_write_' . uniqid() . '.txt';
        $data = 'Test data written at ' . date('Y-m-d H:i:s');

        file_put_contents($testFile, $data);
        $written = file_get_contents($testFile);

        // Append
        file_put_contents($testFile, "\nAppended line", FILE_APPEND);
        $appended = file_get_contents($testFile);

        unlink($testFile);

        return new JsonResponse([
            'status' => 'success',
            'written' => $written,
            'appended' => $appended,
            'message' => 'File write operations completed'
        ]);
    }

    #[Route('/api/files/multiple', name: 'files_multiple', methods: ['GET'])]
    public function testMultipleFiles(): JsonResponse
    {
        $files = [];
        $baseDir = sys_get_temp_dir() . '/opa_test_dir_' . uniqid();
        mkdir($baseDir);

        // Create multiple files
        for ($i = 1; $i <= 5; $i++) {
            $file = $baseDir . "/file_$i.txt";
            file_put_contents($file, "Content of file $i");
            $files[] = [
                'file' => $file,
                'size' => filesize($file),
                'content' => file_get_contents($file)
            ];
        }

        // Cleanup
        foreach (glob($baseDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($baseDir);

        return new JsonResponse([
            'status' => 'success',
            'files_created' => count($files),
            'files' => $files,
            'message' => 'Multiple file operations completed'
        ]);
    }
}

