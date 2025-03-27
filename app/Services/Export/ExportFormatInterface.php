<?php

namespace App\Services\Export;

/**
 * Interface ExportFormatInterface
 * 
 * @package App\Services\Export
 */
interface ExportFormatInterface
{
    /**
     * Export translations to a file format.
     * 
     * @param array $translations Key-value translations where key is the translation key and value is an array of language codes to translated text
     * @param array|object $languages Languages to include in the export (array or Collection)
     * @param string $outputPath Path where to save the exported file
     * @return string Path to the exported file
     */
    public function export(array $translations, $languages, string $outputPath): string;
    
    /**
     * Get the file extension for this format.
     * 
     * @return string
     */
    public function getFileExtension(): string;
} 