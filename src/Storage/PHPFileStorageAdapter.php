<?php
/*
 * This file is part of jbtronics/settings-bundle (https://github.com/jbtronics/settings-bundle).
 *
 * Copyright (c) 2024 Jan Böhmer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);


namespace Jbtronics\SettingsBundle\Storage;

/**
 * This class implements a file storage adapter, which stores the settings in a PHP file
 */
final class PHPFileStorageAdapter extends AbstractFileStorageAdapter
{

    private const FILE_TEMPLATE = <<<'PHP'
    <?php
    // This file was generated by the jbtronics/settings-bundle and contains the settings for the application. Be careful when editing this file, as it might break the application.
    return %s;
    PHP;


    protected function unserialize(string $content): array
    {
        //We overwrite the default load file behavior, as we need to do a require instead of a file_get_contents
        throw new \RuntimeException("This should never be called!");
    }

    protected function serialize(array $data): string
    {
        // Use symfony var-exporter to export the data
        $exported = \Symfony\Component\VarExporter\VarExporter::export($data);

        //Return the serialized data
        return sprintf(self::FILE_TEMPLATE, $exported);
    }

    protected function loadFileContent(string $filePath): ?array
    {
        //If the file does not exist yet, return null
        if (!file_exists($filePath)) {
            return null;
        }

        //Require the file and return the content
        return require $filePath;
    }
}