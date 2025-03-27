<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'primary_language' => new LanguageResource($this->whenLoaded('primaryLanguage')),
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ],
            'languages' => LanguageResource::collection($this->whenLoaded('languages')),
            'translation_keys_count' => $this->whenCounted('translationKeys'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 