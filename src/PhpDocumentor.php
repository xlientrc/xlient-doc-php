<?php
namespace Xlient\Doc\Php;

use RuntimeException;
use Xlient\Doc\Php\PhpFileParser;

use function Xlient\Doc\Php\clean_dir as xlient_clean_dir;
use function Xlient\Doc\Php\delete_contents as xlient_delete_contents;
use function Xlient\Doc\Php\dirnames as xlient_dirnames;
use function Xlient\Doc\Php\filename as xlient_filename;
use function Xlient\Doc\Php\php_filenames as xlient_php_filenames;
use function Xlient\Doc\Php\is_dir_empty as xlient_is_dir_empty;
use function Xlient\Doc\Php\replace_extension as xlient_replace_extension;
use function token_get_all;

use const DIRECTORY_SEPARATOR as DS;

/**
 * Generates markdown documentation files for PHP files.
 */
class PhpDocumentor
{
    /**
     * @var array<string> An array of source directories.
     */
    protected array $srcDirs;

    /**
     * @param string|array<string> $srcDirs One or more source directories to
     *  scan.
     * @param string $destDir A destination directory.
     * @param Configuration $config The configuration to use to generate the
     *  documentation.
     */
    public function __construct(
        string|array $srcDirs,
        protected string $destDir,
        protected Configuration $config,
    ) {
        if (is_string($srcDirs)) {
            $srcDirs = [$srcDirs];
        }

        $this->srcDirs = array_map(xlient_clean_dir(...), $srcDirs);

        $this->destDir = xlient_clean_dir($destDir);
    }

    /**
     * Generates the documentation files.
     *
     * @return array<string> An array of files.
     */
    public function make(): array
    {
        foreach ($this->srcDirs as $key => $srcDir) {
            if (!is_dir($srcDir)) {
                throw new RuntimeException(
                    'The specified source directory, ' . $srcDir . ', was not found.'
                );
            }
        }

        if (!is_dir($this->destDir)) {
            throw new RuntimeException(
                'The specified destination directory, ' . $this->destDir . ', was not found.'
            );
        }

        if (!xlient_is_dir_empty($this->destDir)) {
            if (!$this->config->override) {
                throw new RuntimeException(
                    'The specified destination directory, ' . $this->destDir . ', was not empty.'
                );
            }

            xlient_delete_contents($this->destDir);
        }

        $results = [];

        foreach ($this->srcDirs as $srcDir) {
            $results = [
                ...$results,
                ...$this->makeDir($srcDir, $this->destDir)
            ];
        }

        return $results;
    }

    /**
     * Generates documentaton files for a specific source directory.
     *
     * @param string $srcDir A source directory.
     * @param string $destDir A destination directory.
     *
     * @return array<string> An array of files.
     */
    protected function makeDir(string $srcDir, string $destDir): array
    {
        $results = [];

        $files = xlient_php_filenames($srcDir);

        foreach ($files as $file) {
            $results = [
                ...$results,
                ...$this->makeFile(
                    $srcDir . DS . $file,
                    $destDir,
                )
            ];
        }

        $dirs = xlient_dirnames($srcDir);

        foreach ($dirs as $dir) {
            $results = [
                ... $results,
                ...$this->makeDir(
                    $srcDir . DS . $dir,
                    $destDir
                )
            ];
        }

        return $results;
    }

    /**
     * Generates documentation for a specific PHP file.
     *
     * @param string $srcFile A source file.
     * @param string $destDir A destination directory.
     *
     * @return array<string> An array of files.
     */
    protected function makeFile(string $srcFile, string $destDir): array
    {
        $parser = new PhpFileParser($srcFile, $destDir, $this->config);

        $parser->parse();

        $files = [];

        foreach ($parser->getClasses() as $value) {
            $files = [
                ...$files,
                ...$value->make()
            ];
        }

        foreach ($parser->getInterfaces() as $value) {
            $files = [
                ...$files,
                ...$value->make()
            ];
        }

        foreach ($parser->getTraits() as $value) {
            $files = [
                ...$files,
                ...$value->make()
            ];
        }

        foreach ($parser->getEnums() as $value) {
            $files = [
                ...$files,
                ...$value->make()
            ];
        }

        foreach ($parser->getFunctions() as $value) {
            $files = [
                ...$files,
                ...$value->make()
            ];
        }

        foreach ($parser->getConstants() as $value) {
            $files = [
                ...$files,
                ...$value->make()
            ];
        }

        return $files;
    }
}

// ✝
