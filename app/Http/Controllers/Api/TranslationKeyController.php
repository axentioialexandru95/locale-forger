<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TranslationKeyResource;
use App\Models\Project;
use App\Models\TranslationKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TranslationKeyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $projectId = $request->input('project_id');
        
        $query = TranslationKey::query()->with(['translations.language']);
        
        // Filter by project ID if provided
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        
        // Search by key if provided
        if ($request->has('search')) {
            $query->where('key', 'like', '%' . $request->input('search') . '%');
        }
        
        // Filter by missing translations for a specific language
        if ($request->has('missing_translations_for_language_id')) {
            $languageId = $request->input('missing_translations_for_language_id');
            $query->whereDoesntHave('translations', function ($query) use ($languageId) {
                $query->where('language_id', $languageId);
            });
        }
        
        $keys = $query->paginate($perPage);
        
        return TranslationKeyResource::collection($keys);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'key' => 'required|string|max:255',
            'description' => 'nullable|string',
            'translations' => 'sometimes|array',
            'translations.*.language_id' => 'required|exists:languages,id',
            'translations.*.text' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify the key is unique within the project
        $exists = TranslationKey::where('project_id', $request->input('project_id'))
            ->where('key', $request->input('key'))
            ->exists();
            
        if ($exists) {
            return response()->json([
                'errors' => ['key' => ['The key already exists in this project.']]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $translationKey = TranslationKey::create($request->only(['project_id', 'key', 'description']));
        
        // Create translations if provided
        if ($request->has('translations')) {
            foreach ($request->input('translations') as $translation) {
                $translationKey->translations()->create([
                    'language_id' => $translation['language_id'],
                    'text' => $translation['text'],
                    'status' => $translation['status'] ?? 'draft',
                    'is_machine_translated' => $translation['is_machine_translated'] ?? false,
                    'updated_by' => Auth::id(),
                ]);
            }
        }

        return new TranslationKeyResource($translationKey->load('translations.language'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $translationKey = TranslationKey::with(['translations.language', 'project'])->findOrFail($id);
        
        return new TranslationKeyResource($translationKey);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $translationKey = TranslationKey::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if key is unique in the project when updating
        if ($request->has('key') && $request->input('key') !== $translationKey->key) {
            $exists = TranslationKey::where('project_id', $translationKey->project_id)
                ->where('key', $request->input('key'))
                ->where('id', '!=', $id)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'errors' => ['key' => ['The key already exists in this project.']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $translationKey->update($request->only(['key', 'description']));

        return new TranslationKeyResource($translationKey->load('translations.language'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $translationKey = TranslationKey::findOrFail($id);
        $translationKey->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get all translation keys for a specific project.
     * 
     * @param string $projectId
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByProject(string $projectId, Request $request)
    {
        $project = Project::findOrFail($projectId);
        
        $perPage = $request->input('per_page', 50);
        
        $query = $project->translationKeys()->with(['translations.language']);
        
        // Search by key if provided
        if ($request->has('search')) {
            $query->where('key', 'like', '%' . $request->input('search') . '%');
        }
        
        $keys = $query->paginate($perPage);
        
        return TranslationKeyResource::collection($keys);
    }
}
