<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationKeyResource extends JsonResource
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
            'project_id' => $this->project_id,
            'key' => $this->key,
            'description' => $this->description,
            'translations' => TranslationResource::collection($this->whenLoaded('translations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 