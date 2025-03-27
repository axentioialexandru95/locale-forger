<?php

namespace App\Services\Export;

use App\Models\Project;
use App\Models\TranslationKey;
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
            
            // Group the translations with nesting structure
            $groupedData = $this->groupTranslations($translations, $languageCode);
            
            $jsonData = json_encode($groupedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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
            
            // Group the translations with nesting structure
            $groupedData = $this->groupTranslations($translations, $languageCode);
            
            $jsonData = json_encode($groupedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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
     * Group translations with nesting structure
     * 
     * @param array $translations Array of translations by key
     * @param string $languageCode Language code to export
     * @return array Grouped and nested translations
     */
    protected function groupTranslations(array $translations, string $languageCode): array
    {
        $result = [];
        
        foreach ($translations as $key => $langData) {
            $translationValue = $langData[$languageCode] ?? '';
            
            // Get group and key from database if available
            $parts = explode('.', $key);
            
            if (count($parts) === 1) {
                // No dots in the key, check if we have a group in the database
                $translationKey = TranslationKey::where('key', $key)->first();
                
                if ($translationKey && $translationKey->group) {
                    // If we have a group, use it for nesting
                    $this->setNestedArrayValue($result, [$translationKey->group, $key], $translationValue);
                } else {
                    // Otherwise, just set the key directly
                    $result[$key] = $translationValue;
                }
            } else {
                // Key contains dots, use them for nesting
                $this->setNestedArrayValue($result, $parts, $translationValue);
            }
        }
        
        return $result;
    }
    
    /**
     * Set a value in a nested array using dot notation
     * 
     * @param array &$array Reference to the array
     * @param array $keys Array of keys representing the path
     * @param mixed $value Value to set
     * @return void
     */
    protected function setNestedArrayValue(array &$array, array $keys, $value): void
    {
        $current = &$array;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                // Last key, set the value
                $current[$key] = $value;
            } else {
                // Not the last key, create the nesting if it doesn't exist
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                
                $current = &$current[$key];
            }
        }
    }
    
    /**
     * @inheritDoc
     */
    public function getFileExtension(): string
    {
        return 'json';
    }
} 