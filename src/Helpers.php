<?php
namespace Xlient\Doc\Php;

use InvalidArgumentException;
use RuntimeException;

use const PHP_EOL;
use const DIRECTORY_SEPARATOR as DS;

/**
 * Cleans and normalizes a directory string.
 *
 * @param string $dir A directory.
 *
 * @return string A cleaned directory.
 *
 * @throws InvalidArgumentException When the directory is invalid.
 */
function clean_dir(string $dir): string
{
    if (DS !== '/') {
        $dir = str_replace('/', DS, $dir);
    }

    // Explode twice so starting / and C:\ will be cut
    $parts = explode(DS, $dir, 2);

    // Check if windows directory.
    if ($parts[0] !== '' && !preg_match('/^[a-zA-Z]:\\$/', $parts[0])) {
        throw new InvalidArgumentException('Invalid directory.');
    }

    $parts[1] = clean_path($parts[1]);

    if ($parts[0] === '') {
        return $parts[1];
    }

    return implode(DS, $parts);
}

/**
 * Cleans and normalizes a directory path string.
 *
 * @param string $path A directory path.
 *
 * @return string A cleaned directory path.
 */
function clean_path(string $path): string
{
    if (DS !== '/') {
        $path = str_replace('/', DS, $path);
    }

    $parts = explode(DS, $path);

    foreach ($parts as $key => $value) {
        if ($value === '') {
            unset($parts[$key]);
        }
    }

    if (!$parts) {
        return '';
    }

    return DS . implode(DS, $parts);
}

/**
 * Creates a new directory in the specified directory.
 *
 * @param string $dir A directory.
 * @param int $mode The directories permissions.
 *
 * @return void
 *
 * @throws InvalidArgumentException When the specified directory is invalid.
 * @throws RuntimeException When the directory could not be made.
 */
function make_dir(string $dir, int $mode = 0755): void
{
    $currentDir = getcwd();

    if ($currentDir === false || !is_dir($currentDir)) {
        throw new InvalidArgumentException('Invalid directory.');
    }

    if (str_starts_with($dir, $currentDir . DS)) {
        $paths = substr($dir, strlen($currentDir . DS));
        $paths = explode(DS, $paths);
    } else {
        throw new InvalidArgumentException('Invalid directory.');
    }

    foreach ($paths as $path) {
        if ($path === '') {
            continue;
        }

        $currentDir .= DS . $path;

        if (!file_exists($currentDir) && !mkdir($currentDir)) {
            throw new RuntimeException('Directory could not be made.');
        }

        chmod($currentDir, $mode);
    }
}

/**
 * Gets whether or not the specified directory is empty.
 *
 * @param string $dir A directory.
 *
 * @return bool True if the specified directory is empther, false otherwise.
 *
 * @throws InvalidArgumentException When the directory is invalid.
 * @throws RuntimeException When the directory could not be read.
 */
function is_dir_empty(string $dir): bool
{
    $dir = clean_dir($dir);

    if (!is_dir($dir)) {
        throw new InvalidArgumentException('Invalid directory.');
    }

    $handle = opendir($dir);

    if (!$handle) {
        throw new RuntimeException('Directory could not be read.');
    }

    $isEmpty = true;

    while (($path = readdir($handle)) !== false) {
        if ($path === '.' || $path === '..') {
            continue;
        }

        $isEmpty = false;
        break;
    }

    closedir($handle);

    return $isEmpty;
}

/**
 * Gets an array of directory names contained within the specified directory.
 *
 * @param string $dir A directory.
 *
 * @return array<string> An array of directories.
 *
 * @throws InvalidArgumentException When the directory is invalid.
 * @throws RuntimeException When the directory could not be read.
 */
function dirnames(string $dir): array
{
    $dir = clean_dir($dir);

    if (!is_dir($dir)) {
        throw new InvalidArgumentException('Invalid directory.');
    }

    $handle = opendir($dir);

    if (!$handle) {
        throw new RuntimeException('Directory could not be read.');
    }

    $dirnames = [];

    while (($path = readdir($handle)) !== false) {
        if ($path === '.' || $path === '..') {
            continue;
        }

        if (!is_dir($dir . DS . $path)) {
            continue;
        }

        $info = pathinfo($dir . DS . $path);

        $dirnames[] = $info['basename'];
    }

    closedir($handle);

    return $dirnames;
}

/**
 * Gets an array of PHP files in the specified directory.
 *
 * @param string $dir A directory.
 *
 * @return array<string> An array of PHP files.
 *
 * @throws InvalidArgumentException When the directory is invalid.
 * @throws RuntimeException When the directory could not be read.
 */
function php_filenames(string $dir): array
{
    $dir = clean_dir($dir);

    if (!is_dir($dir)) {
        throw new InvalidArgumentException('Invalid directory.');
    }

    $handle = opendir($dir);

    if (!$handle) {
        throw new RuntimeException('Directory could not be read.');
    }

    $filenames = [];

    while (($path = readdir($handle)) !== false) {
        if ($path === '.' || $path === '..') {
            continue;
        }

        if (is_dir($dir . DS . $path)) {
            continue;
        }

        $info = pathinfo($dir . DS . $path);

        if (!array_key_exists('extension', $info)) {
            continue;
        }

        if (strtolower($info['extension']) !== 'php') {
            continue;
        }

        $filenames[] = $info['basename'];
    }

    closedir($handle);

    return $filenames;
}

/**
 * Gets a filename from the specified file.
 *
 * Both the directory and extension will be removed.
 *
 * @param string $file A file.
 *
 * @return string|null A filename or null if none present.
 */
function filename(string $file): ?string
{
    $pos = strrpos($file, DS);
    if ($pos !== false) {
        $file = substr($file, $pos + strlen(DS));
    }

    $pos = strrpos($file, '.');
    if ($pos !== false) {
        $file = substr($file, 0, $pos);
    }

    return ($file !== '' ? $file : null);
}

/**
 * Deletes the contents of the specified directory.
 *
 * @param string $dir A directory.
 *
 * @return void
 *
 * @throws InvalidArgumentException When the directory is invalid.
 * @throws RuntimeException When the directory could not be read.
 */
function delete_contents(string $dir): void
{
    $dir = clean_dir($dir);

    if (!is_dir($dir)) {
        throw new InvalidArgumentException('Invalid directory.');
    }

    $handle = opendir($dir);

    if (!$handle) {
        throw new RuntimeException('Directory could not be read.');
    }

    while (($path = readdir($handle)) !== false) {
        if ($path === '.' || $path === '..') {
            continue;
        }

        delete($dir . DS . $path);
    }

    closedir($handle);
}

/**
 * Deletes the specified file or directory.
 *
 * @param string $file A file or directory to delete.
 *
 * @return void
 *
 * @throws RuntimeException When the file or directory can't be deleted.
 */
function delete(string $file): void
{
    $file = clean_dir($file);

    if (!file_exists($file)) {
        throw new RuntimeException('File not found.');
    }

    if (!is_dir($file) || is_link($file)) {
        if (!unlink($file)) { // Delete the file
            throw new RuntimeException('File could not be deleted.');
        }

        return;
    }

    $handle = opendir($file);

    if (!$handle) {
        throw new RuntimeException('Directory could not be read.');
    }

    while (($path = readdir($handle)) !== false) {
        if ($path === '.' || $path === '..') {
            continue;
        }

        delete($file . DS . $path);
    }

    closedir($handle);

    if (!rmdir($file)) {
        throw new RuntimeException('Directory could not be deleted.');
    }
}

/**
 * Replaces the specified files extension with another one.
 *
 * If no extension is provided, the extension will be removed.
 *
 * @param string $file A file.
 * @param string|null $extension A file extension.
 *
 * @return string A file with the specified new extension.
 */
function replace_extension(string $file, ?string $extension = null): string
{
    $pos = strrpos($file, '.');
    if ($pos !== false) {
        $file = substr($file, 0, $pos);
    }

    if ($extension !== null) {
        $file .= '.' . ltrim($extension, '.');
    }

    return $file;
}

/**
 * Converts the specifed string to kebab case.
 *
 * @param string $string A string.
 *
 * @return string A string in kebab case.
 */
function to_kebab_case(string $string): string
{
    return implode('-', array_map('strtolower', split_case($string)));
}

/**
 * Splits a string a camel, pascal, snake, or kebab case string into separate
 * values.
 *
 * @param string $string A string to split.
 *
 * @return array<int, string> An array of split values.
 */
function split_case(string $string): array
{
    $result = preg_split(
        '/((?<=[a-z])(?=[A-Z])|(?=[A-Z][a-z])|_|-)/',
        $string,
        0,
        PREG_SPLIT_NO_EMPTY
    );

    if ($result === false) {
        return [];
    }

    return $result;
}

/**
 * Esacpes the specified string so that it won't interfere with markdown
 * syntax.
 *
 * @param string $string A string to escape.
 *
 * @return string An escaped string.
 */
function markdown_escape(string $string): string
{
    return str_replace(
        [
            '\\', '-', '#', '*', '+', '`', '[', ']', '(', ')',
            '!', '&', '<', '>', '_', '{', '}', '|'
        ],
        [
            '\\\\', '\-', '\#', '\*', '\+', '\`', '\[', '\]', '\(', '\)',
            '\!', '\&', '\<', '\>', '\_', '\{', '\}', '\|'
        ],
        $string
    );
}

/**
 * Unesacpes the specified markdown string.
 *
 * @param string $string A markdown string to unescape.
 *
 * @return string An unescaped string.
 */
function markdown_unescape(string $string): string
{
    return str_replace(
        [
            '\\\\', '\-', '\#', '\*', '\+', '\`', '\[', '\]', '\(', '\)',
                '\!', '\&', '\<', '\>', '\_', '\{', '\}', '\|'
        ],
        [
            '\\', '-', '#', '*', '+', '`', '[', ']', '(', ')',
            '!', '&', '<', '>', '_', '{', '}', '|'
        ],
        $string
    );
}

/**
 * Cleans and normalizes a value returned from var_export.
 *
 * @internal
 *
 * @param string $value A var_export value.
 *
 * @return string A cleaned var_export value.
 */
function clean_var_export(string $value): string
{
    if (str_starts_with($value, 'array (')) {
        $output = '';

        $in_string = false;
        $len = strlen($value);
        for ($i = 0; $i < $len; ++$i) {
            $char = $value[$i];

            if ($char == '"') {
                $in_string = !$in_string;
            }

            if (substr($value, $i, 7) === 'array (' && !$in_string) {
                $output .= '[';
                $i += 6;
            } else if ($char == ')' && !$in_string) {
                $output .= ']';
            } else {
                $output .= $char;
            }
        }

        if ($output === '[' . PHP_EOL . ']') {
            $output = '[]';
        }

        return $output;
    }

    return $value;
}

/**
 * Indents the specified value the specified number of times at the specified
 * length.
 *
 * @param string $string A string to be indented.
 * @param int $indentCount The number of times the string should be indented.
 * @param int $indentLength The length in spaces of each indent.
 *
 * @return string An indented string.
 */
function indent(string $string, int $indentCount, int $indentLength): string
{
    $string = explode("\n", $string);

    $indent = str_pad(' ', $indentCount * $indentLength, ' ');

    foreach ($string as $key => $value) {
        $string[$key] = $indent . $value;
    }

    return implode("\n", $string);
}

// ✝
