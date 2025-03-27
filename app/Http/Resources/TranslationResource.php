<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationResource extends JsonResource
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
            'translation_key_id' => $this->translation_key_id,
            'language_id' => $this->language_id,
            'language' => new LanguageResource($this->whenLoaded('language')),
            'text' => $this->text,
            'is_machine_translated' => $this->is_machine_translated,
            'status' => $this->status,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 