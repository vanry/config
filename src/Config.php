<?php

namespace Vanry\Config;

use Noodlehaus\FileParser;
use Noodlehaus\AbstractConfig;
use Noodlehaus\Exception\FileNotFoundException;
use Noodlehaus\Exception\EmptyDirectoryException;
use Noodlehaus\Exception\UnsupportedFormatException;

class Config extends AbstractConfig
{
    /**
     * All file formats supported by Config
     *
     * @var array
     */
    protected $supportedFileParsers = [
        FileParser\Php::class,
        FileParser\Ini::class,
        FileParser\Xml::class,
        FileParser\Json::class,
        FileParser\Yaml::class,
    ];

    /**
     * Loads a supported configuration file format.
     *
     * @param  string|array $path
     *
     * @throws EmptyDirectoryException    If `$path` is an empty directory
     */
    public function __construct($path = null)
    {
        $this->data = [];

        if (! is_null($path)) {
            $paths = $this->getValidPath($path);

            foreach ($paths as $path) {
                $info = pathinfo($path);

                $parts = explode('.', $info['basename']);

                $extension = array_pop($parts);

                if ($extension === 'dist') {
                    $extension = array_pop($parts);
                }

                $items = $this->getParser($extension)->parse($path);

                if (count($paths) == 1) {
                    $this->data = $items;

                    break;
                }

                $this->data[$info['filename']] = $items;
            }
        }

        parent::__construct($this->data);
    }

    /**
     * Static method for loading a Config instance.
     *
     * @param  string|array $path
     *
     * @return Config
     */
    public static function load($path = null)
    {
        return new static($path);
    }

    /**
     * Gets a parser for a given file extension
     *
     * @param  string $extension
     *
     * @return Noodlehaus\FileParser\FileParserInterface
     *
     * @throws UnsupportedFormatException If `$path` is an unsupported file format
     */
    protected function getParser($extension)
    {
        $parser = null;

        foreach ($this->supportedFileParsers as $fileParser) {
            $tempParser = new $fileParser;

            if (in_array($extension, $tempParser->getSupportedExtensions($extension))) {
                $parser = $tempParser;

                break;
            }
        }

        // If none exist, then throw an exception
        if ($parser === null) {
            throw new UnsupportedFormatException('Unsupported configuration format');
        }

        return $parser;
    }

    /**
     * Gets an array of paths
     *
     * @param  array $path
     *
     * @return array
     *
     * @throws FileNotFoundException   If a file is not found at `$path`
     */
    protected function getPathFromArray($path)
    {
        $paths = [];

        foreach ($path as $unverifiedPath) {
            try {
                // Check if `$unverifiedPath` is optional
                // If it exists, then it's added to the list
                // If it doesn't, it throws an exception which we catch
                if ($unverifiedPath[0] !== '?') {
                    $paths = array_merge($paths, $this->getValidPath($unverifiedPath));

                    continue;
                }

                $optionalPath = ltrim($unverifiedPath, '?');

                $paths = array_merge($paths, $this->getValidPath($optionalPath));
            } catch (FileNotFoundException $e) {
                // If `$unverifiedPath` is optional, then skip it
                if ($unverifiedPath[0] === '?') {
                    continue;
                }

                // Otherwise rethrow the exception
                throw $e;
            }
        }

        return $paths;
    }

    /**
     * Checks `$path` to see if it is either an array, a directory, or a file
     *
     * @param  string|array $path
     *
     * @return array
     *
     * @throws EmptyDirectoryException If `$path` is an empty directory
     *
     * @throws FileNotFoundException   If a file is not found at `$path`
     */
    protected function getValidPath($path)
    {
        // If `$path` is array
        if (is_array($path)) {
            return $this->getPathFromArray($path);
        }

        // If `$path` is a directory
        if (is_dir($path)) {
            $paths = glob($path . '/*.*');

            if (empty($paths)) {
                throw new EmptyDirectoryException("Configuration directory: [$path] is empty");
            }

            return $paths;
        }

        // If `$path` is not a file, throw an exception
        if (!file_exists($path)) {
            throw new FileNotFoundException("Configuration file: [$path] cannot be found");
        }

        return (array) $path;
    }
}
