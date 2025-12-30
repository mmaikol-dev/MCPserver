<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFilesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
List files and directories in a specified path.

Use this tool to:
- Browse the project structure
- Find specific files
- Understand the codebase organization
- Discover what files exist before reading them

Returns a list of files and directories with their details.
MARKDOWN;

    /**
     * Allowed directories for listing
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
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $directory = $request->string('directory', '.');
            $recursive = $request->boolean('recursive', false);
            $pattern = $request->get('pattern');

            // Security: Validate directory path
            if (! $this->isPathAllowed($directory)) {
                return Response::error(
                    "Access denied. Directory '{$directory}' is not in an allowed directory. ".
                    'Allowed directories: '.implode(', ', $this->allowedDirectories)
                );
            }

            $fullPath = base_path($directory);

            if (! File::exists($fullPath)) {
                return Response::error("Directory not found: {$directory}");
            }

            if (! File::isDirectory($fullPath)) {
                return Response::error("Path is not a directory: {$directory}");
            }

            // Get files
            if ($recursive) {
                $files = File::allFiles($fullPath);
                $directories = File::directories($fullPath);
            } else {
                $files = File::files($fullPath);
                $directories = File::directories($fullPath);
            }

            // Filter by pattern if provided
            if ($pattern) {
                $files = array_filter($files, function ($file) use ($pattern) {
                    return fnmatch($pattern, $file->getFilename());
                });
            }

            // Format file information
            $fileList = collect($files)->map(function ($file) {
                $relativePath = str_replace(base_path().'/', '', $file->getPathname());

                return [
                    'name' => $file->getFilename(),
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'extension' => $file->getExtension(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            })->values()->toArray();

            // Format directory information
            $dirList = collect($directories)->map(function ($dir) {
                $relativePath = str_replace(base_path().'/', '', $dir);

                return [
                    'name' => basename($dir),
                    'path' => $relativePath,
                    'type' => 'directory',
                ];
            })->values()->toArray();

            $responseData = [
                'message' => 'Directory listed successfully',
                'directory' => $directory,
                'files' => $fileList,
                'directories' => $dirList,
                'total_files' => count($fileList),
                'total_directories' => count($dirList),
                'recursive' => $recursive,
            ];

            $request->merge(['_response_data' => $responseData]);

            return Response::text(json_encode($responseData));

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to list directory: '.$e->getMessage());
        }
    }

    /**
     * Check if path is in allowed directories
     */
    protected function isPathAllowed(string $path): bool
    {
        if ($path === '.') {
            return true;
        }

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
            'directory' => $schema->string()
                ->description('Directory path relative to project root (e.g., "app/Http/Controllers", "resources/js/Pages")')
                ->default('.')
                ->nullable(),

            'recursive' => $schema->boolean()
                ->description('Whether to list files recursively in subdirectories')
                ->default(false)
                ->nullable(),

            'pattern' => $schema->string()
                ->description('File name pattern to filter results (e.g., "*.php", "Order*")')
                ->nullable(),
        ];
    }
}
