<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PhpHeaderSafetyTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | PHP Header Safety Test
    |--------------------------------------------------------------------------
    |
    | Check project PHP files for unexpected
    | characters before the opening PHP tag.
    |
    | This helps prevent premature output
    | that can break HTTP headers, redirects,
    | cookies and Laravel sessions.
    |
    | Blade templates are excluded because
    | they normally contain HTML.
    |
    */

    public function test_php_files_start_with_opening_tag(): void
    {
        $projectRoot = dirname(__DIR__, 2);

        $directories = [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'tests',
        ];

        $invalidFiles = [];
        $checkedFiles = 0;

        foreach ($directories as $directory) {
            $directoryPath = $projectRoot
                . DIRECTORY_SEPARATOR
                . $directory;

            if (!is_dir($directoryPath)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directoryPath,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();

                // Only check PHP files.
                if (!str_ends_with($filename, '.php')) {
                    continue;
                }

                // Blade views may begin with HTML.
                if (str_ends_with($filename, '.blade.php')) {
                    continue;
                }

                $checkedFiles++;

                $path = $file->getPathname();

                // Read only the first five bytes.
                $firstBytes = file_get_contents(
                    $path,
                    false,
                    null,
                    0,
                    5
                );

                // Every checked PHP file must begin with <?php.
                if ($firstBytes !== '<?php') {
                    $relativePath = substr(
                        $path,
                        strlen($projectRoot) + 1
                    );

                    $invalidFiles[] = str_replace(
                        '\\',
                        '/',
                        $relativePath
                    );
                }
            }
        }

        // Ensure the test actually checked PHP files.
        $this->assertGreaterThan(
            0,
            $checkedFiles,
            'No PHP files were checked.'
        );

        // Fail and show every file needing attention.
        $this->assertSame(
            [],
            $invalidFiles,
            "PHP files must start directly with <?php.\n"
                . "Remove leading blank lines or BOM from:\n"
                . implode("\n", $invalidFiles)
        );
    }
}
