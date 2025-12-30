<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class WriteFileTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
Write or modify files in the project codebase.

⚠️ CAUTION: This tool can modify your code. Use carefully!

Use this tool to:
- Create new files
- Update existing files
- Fix bugs in the code
- Implement new features
- Refactor code

Always create a backup before making changes.
MARKDOWN;

    /**
     * Password required for file modifications
     */
    protected const WRITE_PASSWORD = 'qwerty2025!';

    /**
     * Allowed directories for writing
     */
    protected array $allowedDirectories = [
        'app/Mcp/Tools',
        'resources/js/Pages',
        'app/Http/Controllers/Ai',
    ];

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $filePath = $request->string('file_path');
            $content = $request->string('content');
            $password = $request->string('password');
            $backup = $request->boolean('backup', true);

            // Verify password
            if ($password !== self::WRITE_PASSWORD) {
                return Response::error(
                    '❌ Invalid password. File modification cancelled for security reasons.'
                );
            }

            // Security: Validate file path
            if (! $this->isPathAllowed($filePath)) {
                return Response::error(
                    "Access denied. File path '{$filePath}' is not in an allowed directory. ".
                    'Allowed directories: '.implode(', ', $this->allowedDirectories)
                );
            }

            $fullPath = base_path($filePath);
            $fileExists = File::exists($fullPath);

            // Create backup if file exists and backup is requested
            $backupPath = null;
            if ($fileExists && $backup) {
                $backupPath = $fullPath.'.backup.'.date('YmdHis');
                File::copy($fullPath, $backupPath);
            }

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Write the file
            File::put($fullPath, $content);

            $responseData = [
                'message' => $fileExists ? 'File updated successfully' : 'File created successfully',
                'file_path' => $filePath,
                'action' => $fileExists ? 'updated' : 'created',
                'backup_created' => $backup && $fileExists,
                'backup_path' => $backupPath ? str_replace(base_path().'/', '', $backupPath) : null,
                'file_size' => File::size($fullPath),
                'lines' => substr_count($content, "\n") + 1,
            ];

            $request->merge(['_response_data' => $responseData]);

            return Response::text(json_encode($responseData));

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to write file: '.$e->getMessage());
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
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('Path to the file to write/modify (e.g., "app/Mcp/Tools/NewTool.php")')
                ->required(),

            'content' => $schema->string()
                ->description('Complete file content to write')
                ->required(),

            'password' => $schema->string()
                ->description('Password required for file modification (security measure)')
                ->required(),

            'backup' => $schema->boolean()
                ->description('Whether to create a backup of existing file before modifying')
                ->default(true)
                ->nullable(),
        ];
    }
}
