<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ReadFileTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
Read the contents of a file from the project codebase.

Use this tool to:
- View source code files (PHP, JavaScript, TypeScript, etc.)
- Read configuration files
- Inspect templates and views
- Review any text-based file in the project

This helps you understand the current implementation before suggesting changes.
MARKDOWN;

    /**
     * Allowed directories for file access
     */
    protected array $allowedDirectories = [
        'app',
        'resources',
        'routes',
        'config',
        'database',
        'tests',
    ];

    /**
     * Blocked file patterns for security
     */
    protected array $blockedPatterns = [
        '.env',
        '.env.*',
        '*.key',
        '*.pem',
        'storage/*',
        'vendor/*',
        'node_modules/*',
    ];

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $filePath = $request->string('file_path');

            // Security: Validate file path
            if (! $this->isPathAllowed($filePath)) {
                return Response::error(
                    "Access denied. File path '{$filePath}' is not in an allowed directory. ".
                    'Allowed directories: '.implode(', ', $this->allowedDirectories)
                );
            }

            if ($this->isPathBlocked($filePath)) {
                return Response::error(
                    'Access denied. This file type is restricted for security reasons.'
                );
            }

            $fullPath = base_path($filePath);

            if (! File::exists($fullPath)) {
                return Response::error("File not found: {$filePath}");
            }

            if (! File::isReadable($fullPath)) {
                return Response::error("File is not readable: {$filePath}");
            }

            // Check file size (limit to 500KB)
            $fileSize = File::size($fullPath);
            if ($fileSize > 500000) {
                return Response::error(
                    "File too large ({$fileSize} bytes). Maximum size is 500KB. ".
                    'Consider reading a smaller file or specific sections.'
                );
            }

            $content = File::get($fullPath);
            $lines = substr_count($content, "\n") + 1;
            $extension = File::extension($fullPath);

            $responseData = [
                'message' => 'File read successfully',
                'file_path' => $filePath,
                'content' => $content,
                'file_info' => [
                    'size' => $fileSize,
                    'lines' => $lines,
                    'extension' => $extension,
                    'last_modified' => date('Y-m-d H:i:s', File::lastModified($fullPath)),
                ],
            ];

            $request->merge(['_response_data' => $responseData]);

            return Response::text(json_encode($responseData));

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to read file: '.$e->getMessage());
        }
    }

    /**
     * Check if path is in allowed directories
     */
    protected function isPathAllowed(string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($this->allowedDirectories as $allowedDir) {
            if (str_starts_with($normalizedPath, $allowedDir.'/') ||
                $normalizedPath === $allowedDir) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path matches blocked patterns
     */
    protected function isPathBlocked(string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($this->blockedPatterns as $pattern) {
            if (fnmatch($pattern, $normalizedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('Path to the file relative to project root (e.g., "app/Http/Controllers/OrderController.php" or "resources/js/Pages/chats/index.tsx")')
                ->required(),
        ];
    }
}
