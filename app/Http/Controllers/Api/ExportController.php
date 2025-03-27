<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExportTranslationsJob;
use App\Models\Language;
use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Validator;

class ExportController extends Controller
{
    /**
     * The export service instance.
     * 
     * @var ExportService
     */
    protected ExportService $exportService;
    
    /**
     * Create a new controller instance.
     * 
     * @param ExportService $exportService
     * @return void
     */
    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }
    
    /**
     * Export translations for a project.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'format' => 'required|in:json,csv',
            'languages' => 'sometimes|array',
            'languages.*' => 'exists:languages,id',
            'async' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $projectId = $request->input('project_id');
        $format = $request->input('format');
        $languageIds = $request->input('languages', []);
        $async = $request->input('async', false);
        
        // For async processing, dispatch a job and return a job ID
        if ($async) {
            ExportTranslationsJob::dispatch(
                $projectId,
                $format,
                $languageIds,
                Auth::id()
            );
            
            return response()->json([
                'message' => 'Export job has been queued and will be processed shortly.',
                'async' => true,
            ]);
        }
        
        // For synchronous processing, export now and return the file
        try {
            $filePath = $this->exportService->exportProject(
                $projectId,
                $format,
                $languageIds
            );
            
            // Get the project and determine filename
            $project = Project::findOrFail($projectId);
            $filename = $project->slug . '.' . ($format === 'json' ? 'zip' : $format);
            
            // Return the file as a download
            return ResponseFacade::download($filePath, $filename);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Export translations for a specific language in a project (direct JSON export).
     * 
     * @param string $projectId
     * @param string $languageCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportLanguage(string $projectId, string $languageCode)
    {
        // Validate inputs
        $project = Project::findOrFail($projectId);
        $language = Language::where('code', $languageCode)->firstOrFail();
        
        // Check if this language is available for the project
        $isProjectLanguage = $project->languages()->where('language_id', $language->id)->exists();
        if (!$isProjectLanguage) {
            return response()->json([
                'error' => "Language '$languageCode' is not available for this project."
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            // Export only this language
            $filePath = $this->exportService->exportProject(
                $project->id,
                'json',
                [$language->id]
            );
            
            // Return the file as a download
            return ResponseFacade::download($filePath, $project->slug . '_' . $languageCode . '.json');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
