<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Project;
use App\Services\Export\CsvExporter;
use App\Services\Export\ExportFormatInterface;
use App\Services\Export\JsonExporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class ExportService
 * 
 * Handles the export of translations to different file formats
 * 
 * @package App\Services
 */
class ExportService
{
    /**
     * @var TranslationService
     */
    protected TranslationService $translationService;
    
    /**
     * @var array
     */
    protected array $exporters = [];
    
    /**
     * ExportService constructor.
     * 
     * @param TranslationService $translationService
     */
    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
        
        // Register exporters
        $this->registerExporter('json', new JsonExporter());
        $this->registerExporter('csv', new CsvExporter());
    }
    
    /**
     * Register a new exporter.
     * 
     * @param string $format
     * @param ExportFormatInterface $exporter
     * @return void
     */
    public function registerExporter(string $format, ExportFormatInterface $exporter): void
    {
        $this->exporters[$format] = $exporter;
    }
    
    /**
     * Export translations for a project in the specified format.
     * 
     * @param Project|int $project Project or project ID
     * @param string $format Format to export (json, csv)
     * @param array $languageIds Language IDs to include (empty for all project languages)
     * @return string Path to the exported file
     * @throws \Exception If the export format is not supported
     */
    public function exportProject($project, string $format, array $languageIds = []): string
    {
        // Ensure we have a Project model
        if (!$project instanceof Project) {
            $project = Project::findOrFail($project);
        }
        
        // Get languages to export
        $languages = [];
        if (empty($languageIds)) {
            // Export all project languages
            $languages = $project->languages()->get();
        } else {
            // Export only specified languages
            $languages = Language::whereIn('id', $languageIds)->get();
        }
        
        // Ensure we have at least one language
        if ($languages->isEmpty()) {
            throw new \Exception('No languages specified for export');
        }
        
        // Check if the format is supported
        if (!isset($this->exporters[$format])) {
            throw new \Exception("Export format '$format' is not supported");
        }
        
        // Get translations for export
        $translations = $this->translationService->getTranslationsForExport(
            $project->id,
            $languages->pluck('id')->toArray()
        );
        
        // Create an export directory in storage
        $exportDir = storage_path('app/exports');
        File::ensureDirectoryExists($exportDir);
        
        // Generate unique subdirectory for this export
        $exportId = Str::random(10);
        $exportPath = $exportDir . '/' . $exportId;
        File::ensureDirectoryExists($exportPath);
        
        // Export using the appropriate exporter
        $exporter = $this->exporters[$format];
        $filePath = $exporter->export($translations, $languages->toArray(), $exportPath);
        
        // Return the file path
        return $filePath;
    }
} 