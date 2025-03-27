<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\File;

/**
 * Class CsvExporter
 * 
 * @package App\Services\Export
 */
class CsvExporter implements ExportFormatInterface
{
    /**
     * @inheritDoc
     */
    public function export(array $translations, $languages, string $outputPath): string
    {
        // Ensure languages is an array
        $languages = is_array($languages) ? $languages : $languages->toArray();
        
        // Create the CSV file path
        $filePath = $outputPath . '/translations.csv';
        
        // Open file for writing
        $file = fopen($filePath, 'w');
        
        // Write UTF-8 BOM to ensure Excel opens the file correctly with UTF-8 encoding
        fputs($file, "\xEF\xBB\xBF");
        
        // Write header row with language codes
        $headerRow = ['Key'];
        foreach ($languages as $language) {
            $languageCode = is_array($language) ? $language['code'] : $language->code;
            $headerRow[] = $languageCode;
        }
        fputcsv($file, $headerRow);
        
        // Write each translation key and its translations
        foreach ($translations as $key => $langData) {
            $row = [$key];
            
            foreach ($languages as $language) {
                $languageCode = is_array($language) ? $language['code'] : $language->code;
                $row[] = $langData[$languageCode] ?? '';
            }
            
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return $filePath;
    }
    
    /**
     * @inheritDoc
     */
    public function getFileExtension(): string
    {
        return 'csv';
    }
} 