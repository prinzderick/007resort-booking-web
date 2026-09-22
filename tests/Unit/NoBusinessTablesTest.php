<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Architecture guard: the Otueke API is the ONLY owner of the MySQL schema.
 * This application must not ship migrations or Eloquent models for business
 * data. Changing this requires an approved ADR.
 */
class NoBusinessTablesTest extends TestCase
{
    private function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2).($path !== '' ? '/'.$path : '');
    }

    public function test_no_migration_creates_tables(): void
    {
        $files = glob($this->basePath('database/migrations/*.php')) ?: [];

        foreach ($files as $file) {
            $this->assertStringNotContainsString(
                'Schema::create',
                (string) file_get_contents($file),
                basename($file).' creates a table; business tables belong to the Otueke API.',
            );
        }

        $this->assertSame([], preg_grep('/users|password_reset/i', array_map('basename', $files)));
    }

    public function test_no_eloquent_models_in_app(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->basePath('app'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/extends\s+(\\\\?Illuminate\\\\Database\\\\Eloquent\\\\)?(Model|Authenticatable)\b/',
                (string) file_get_contents($file->getPathname()),
                $file->getPathname().' defines an Eloquent model; business data is owned by the Otueke API.',
            );
        }
    }
}
