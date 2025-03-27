<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportTranslationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The project to export
     * 
     * @var int
     */
    protected int $projectId;
    
    /**
     * The format to export to
     * 
     * @var string
     */
    protected string $format;
    
    /**
     * The language IDs to include in the export
     * 
     * @var array
     */
    protected array $languageIds;
    
    /**
     * User ID who requested the export
     * 
     * @var int
     */
    protected int $userId;

    /**
     * Create a new job instance.
     * 
     * @param int $projectId
     * @param string $format
     * @param array $languageIds
     * @param int $userId
     */
    public function __construct(int $projectId, string $format, array $languageIds, int $userId)
    {
        $this->projectId = $projectId;
        $this->format = $format;
        $this->languageIds = $languageIds;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        try {
            Log::info("Starting export job for project {$this->projectId} in {$this->format} format");
            
            // Get the project for file naming
            $project = Project::findOrFail($this->projectId);
            
            // Create export record with initial status
            $export = Export::create([
                'project_id' => $this->projectId,
                'user_id' => $this->userId,
                'format' => $this->format,
                'file_path' => '',  // Will be updated after export
                'file_name' => $project->slug . '.' . ($this->format === 'json' && empty($this->languageIds) ? 'zip' : $this->format),
                'status' => 'processing',
            ]);
            
            // Get the export file path
            $filePath = $exportService->exportProject(
                $this->projectId,
                $this->format,
                empty($this->languageIds) ? [] : $this->languageIds
            );
            
            // Update the export record with the file path and completed status
            $export->update([
                'file_path' => $filePath,
                'status' => 'completed',
            ]);
            
            Log::info("Export completed successfully for project {$this->projectId}: {$filePath}");
        } catch (\Exception $e) {
            Log::error("Export failed for project {$this->projectId}: {$e->getMessage()}");
            
            // Update the export record with error status
            if (isset($export)) {
                $export->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
            
            $this->fail($e);
        }
    }
}
