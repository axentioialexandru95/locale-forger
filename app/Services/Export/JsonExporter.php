<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Class JsonExporter
 * 
 * @package App\Services\Export
 */
class JsonExporter implements ExportFormatInterface
{
    /**
     * @inheritDoc
     */
    public function export(array $translations, $languages, string $outputPath): string
    {
        // Ensure languages is an array
        $languages = is_array($languages) ? $languages : $languages->toArray();
        
        // If only one language, output a single JSON file
        if (count($languages) === 1) {
            $language = $languages[0];
            $languageCode = is_array($language) ? $language['code'] : $language->code;
            $singleLanguageData = [];
            
            foreach ($translations as $key => $langData) {
                $singleLanguageData[$key] = $langData[$languageCode] ?? '';
            }
            
            $jsonData = json_encode($singleLanguageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $filePath = $outputPath . '/' . $languageCode . '.json';
            
            File::put($filePath, $jsonData);
            return $filePath;
        }
        
        // Multiple languages - create a zip with one JSON file per language
        $zipPath = $outputPath . '/translations.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create zip archive: $zipPath");
        }
        
        foreach ($languages as $language) {
            $languageCode = is_array($language) ? $language['code'] : $language->code;
            $singleLanguageData = [];
            
            foreach ($translations as $key => $langData) {
                $singleLanguageData[$key] = $langData[$languageCode] ?? '';
            }
            
            $jsonData = json_encode($singleLanguageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $tempFile = tempnam(sys_get_temp_dir(), 'json_export_');
            
            // Write data to a temporary file
            file_put_contents($tempFile, $jsonData);
            
            // Add the file to the ZIP with the language code as the filename
            $zip->addFile($tempFile, $languageCode . '.json');
        }
        
        $zip->close();
        
        return $zipPath;
    }
    
    /**
     * @inheritDoc
     */
    public function getFileExtension(): string
    {
        return 'json';
    }
} 