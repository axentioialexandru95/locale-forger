<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TranslationResource;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TranslationController extends Controller
{
    /**
     * The translation service instance.
     * 
     * @var TranslationService
     */
    protected TranslationService $translationService;
    
    /**
     * Create a new controller instance.
     * 
     * @param TranslationService $translationService
     * @return void
     */
    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $projectId = $request->input('project_id');
        $languageId = $request->input('language_id');
        $keyId = $request->input('translation_key_id');
        
        $query = Translation::query()->with(['translationKey', 'language']);
        
        if ($keyId) {
            $query->where('translation_key_id', $keyId);
        }
        
        if ($languageId) {
            $query->where('language_id', $languageId);
        }
        
        if ($projectId) {
            $query->whereHas('translationKey', function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        // Filter by machine translated
        if ($request->has('is_machine_translated')) {
            $query->where('is_machine_translated', $request->boolean('is_machine_translated'));
        }
        
        $translations = $query->paginate($perPage);
        
        return TranslationResource::collection($translations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'translation_key_id' => 'required|exists:translation_keys,id',
            'language_id' => 'required|exists:languages,id',
            'text' => 'required|string',
            'is_machine_translated' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,review,final',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Check if translation already exists
        $exists = Translation::where('translation_key_id', $request->input('translation_key_id'))
            ->where('language_id', $request->input('language_id'))
            ->exists();
            
        if ($exists) {
            return response()->json([
                'errors' => ['translation' => ['Translation for this key and language already exists.']]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Create the translation
        $translation = Translation::create([
            'translation_key_id' => $request->input('translation_key_id'),
            'language_id' => $request->input('language_id'),
            'text' => $request->input('text'),
            'is_machine_translated' => $request->input('is_machine_translated', false),
            'status' => $request->input('status', 'draft'),
            'updated_by' => Auth::id(),
        ]);

        return new TranslationResource($translation->load(['translationKey', 'language']));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $translation = Translation::with(['translationKey', 'language'])->findOrFail($id);
        
        return new TranslationResource($translation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $translation = Translation::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'text' => 'sometimes|string',
            'is_machine_translated' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,review,final',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $data = $request->only(['text', 'is_machine_translated', 'status']);
        $data['updated_by'] = Auth::id();
        
        $translation->update($data);

        return new TranslationResource($translation->load(['translationKey', 'language']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $translation = Translation::findOrFail($id);
        $translation->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
    
    /**
     * Bulk update translations.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'translations' => 'required|array',
            'translations.*.translation_key_id' => 'required|exists:translation_keys,id',
            'translations.*.language_id' => 'required|exists:languages,id',
            'translations.*.text' => 'required|string',
            'translations.*.status' => 'sometimes|in:draft,review,final',
            'translations.*.is_machine_translated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $count = $this->translationService->bulkUpdateTranslations($request->input('translations'));
        
        return response()->json([
            'message' => "$count translations updated successfully",
            'count' => $count
        ]);
    }
    
    /**
     * Get all translations for a key.
     * 
     * @param string $keyId
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByKey(string $keyId)
    {
        $key = TranslationKey::findOrFail($keyId);
        
        $translations = $key->translations()->with(['language'])->get();
        
        return TranslationResource::collection($translations);
    }
    
    /**
     * Copy translations from one language to another within a project.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function copyBetweenLanguages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'source_language_id' => 'required|exists:languages,id',
            'target_language_id' => 'required|exists:languages,id|different:source_language_id',
            'overwrite' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $count = $this->translationService->copyTranslations(
            $request->input('project_id'),
            $request->input('source_language_id'),
            $request->input('target_language_id'),
            $request->input('overwrite', false)
        );
        
        return response()->json([
            'message' => "$count translations copied successfully",
            'count' => $count
        ]);
    }
}
