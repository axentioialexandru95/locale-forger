<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectCollection;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $organizationId = $request->input('organization_id');
        
        $query = Project::query()->with(['primaryLanguage', 'organization']);
        
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }
        
        $projects = $query->withCount('translationKeys')->paginate($perPage);
        
        return new ProjectCollection($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:projects',
            'organization_id' => 'required|exists:organizations,id',
            'primary_language_id' => 'required|exists:languages,id',
            'languages' => 'required|array',
            'languages.*' => 'exists:languages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Generate slug if not provided
        $data = $request->all();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $project = Project::create($data);
        
        // Attach languages
        if (!empty($data['languages'])) {
            $project->languages()->attach($data['languages']);
        }

        return new ProjectResource($project->load(['primaryLanguage', 'languages']));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::with(['primaryLanguage', 'languages', 'organization'])
            ->withCount('translationKeys')
            ->findOrFail($id);
            
        return new ProjectResource($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $project = Project::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:projects,slug,' . $id,
            'organization_id' => 'sometimes|exists:organizations,id',
            'primary_language_id' => 'sometimes|exists:languages,id',
            'languages' => 'sometimes|array',
            'languages.*' => 'exists:languages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->all();
        
        // Update slug if name is changed but slug is not provided
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $project->update($data);
        
        // Sync languages if provided
        if (isset($data['languages'])) {
            $project->languages()->sync($data['languages']);
        }

        return new ProjectResource($project->load(['primaryLanguage', 'languages']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::findOrFail($id);
        $project->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
