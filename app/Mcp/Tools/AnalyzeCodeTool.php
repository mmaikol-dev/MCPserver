<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AnalyzeCodeTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
Analyze code structure and relationships.

Use this tool to:
- Find where a class or function is used
- Identify dependencies
- Search for specific code patterns
- Understand code relationships

This helps you understand the impact of changes before making them.
MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $searchType = $request->string('search_type', 'text');
            $searchTerm = $request->string('search_term');
            $directory = $request->string('directory', 'app');
            $filePattern = $request->string('file_pattern', '*.php');

            $basePath = base_path($directory);

            if (! File::isDirectory($basePath)) {
                return Response::error("Directory not found: {$directory}");
            }

            $results = [];
            $files = File::allFiles($basePath);

            foreach ($files as $file) {
                if (! fnmatch($filePattern, $file->getFilename())) {
                    continue;
                }

                $content = File::get($file->getPathname());
                $matches = [];

                switch ($searchType) {
                    case 'text':
                        if (stripos($content, $searchTerm) !== false) {
                            $matches = $this->findTextMatches($content, $searchTerm);
                        }
                        break;

                    case 'class':
                        if (preg_match('/class\s+'.preg_quote($searchTerm).'\s/', $content)) {
                            $matches = ['Class definition found'];
                        }
                        if (preg_match_all('/new\s+'.preg_quote($searchTerm).'\s*\(/', $content, $m)) {
                            $matches[] = 'Instantiated '.count($m[0]).' times';
                        }
                        break;

                    case 'function':
                        if (preg_match_all('/'.preg_quote($searchTerm).'\s*\(/', $content, $m)) {
                            $matches[] = 'Called '.count($m[0]).' times';
                        }
                        break;

                    case 'import':
                        if (preg_match('/use\s+.*'.preg_quote($searchTerm).'/', $content)) {
                            $matches = ['Imported/used'];
                        }
                        break;
                }

                if (! empty($matches)) {
                    $relativePath = str_replace(base_path().'/', '', $file->getPathname());
                    $results[] = [
                        'file' => $relativePath,
                        'matches' => $matches,
                    ];
                }
            }

            $responseData = [
                'message' => 'Code analysis complete',
                'search_type' => $searchType,
                'search_term' => $searchTerm,
                'directory' => $directory,
                'results' => $results,
                'total_matches' => count($results),
            ];

            $request->merge(['_response_data' => $responseData]);

            return Response::text(json_encode($responseData));

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to analyze code: '.$e->getMessage());
        }
    }

    /**
     * Find text matches with line numbers
     */
    protected function findTextMatches(string $content, string $searchTerm): array
    {
        $lines = explode("\n", $content);
        $matches = [];

        foreach ($lines as $index => $line) {
            if (stripos($line, $searchTerm) !== false) {
                $matches[] = 'Line '.($index + 1).': '.trim($line);

                if (count($matches) >= 5) {
                    $matches[] = '... and more';
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search_type' => $schema->string()
                ->description('Type of search: text, class, function, import')
                ->default('text')
                ->nullable(),

            'search_term' => $schema->string()
                ->description('Term to search for (class name, function name, or text)')
                ->required(),

            'directory' => $schema->string()
                ->description('Directory to search in')
                ->default('app')
                ->nullable(),

            'file_pattern' => $schema->string()
                ->description('File pattern to match (e.g., *.php, *.tsx)')
                ->default('*.php')
                ->nullable(),
        ];
    }
}
