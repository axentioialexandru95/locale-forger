<?php

namespace App\Repositories;

use App\Models\Translation;
use App\Models\TranslationKey;
use App\Repositories\Interfaces\TranslationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class TranslationRepository
 * 
 * @package App\Repositories
 */
class TranslationRepository extends BaseRepository implements TranslationRepositoryInterface
{
    /**
     * TranslationRepository constructor.
     * 
     * @param Translation $model
     */
    public function __construct(Translation $model)
    {
        parent::__construct($model);
    }

    /**
     * @inheritDoc
     */
    public function findByKeyAndLanguage(int $keyId, int $languageId): ?Translation
    {
        return $this->model
            ->where('translation_key_id', $keyId)
            ->where('language_id', $languageId)
            ->first();
    }

    /**
     * @inheritDoc
     */
    public function getByProject(int $projectId, ?int $languageId = null): Collection
    {
        $query = $this->model
            ->whereHas('translationKey', function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })
            ->with(['translationKey', 'language']);

        if ($languageId) {
            $query->where('language_id', $languageId);
        }

        return $query->get();
    }

    /**
     * @inheritDoc
     */
    public function getMissingTranslations(int $projectId, int $languageId): Collection
    {
        // Find all keys in the project that don't have a translation for the specified language
        return TranslationKey::where('project_id', $projectId)
            ->whereDoesntHave('translations', function ($query) use ($languageId) {
                $query->where('language_id', $languageId);
            })
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function getGroupedForExport(int $projectId, array $languageIds): array
    {
        $translations = $this->model
            ->whereHas('translationKey', function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })
            ->whereIn('language_id', $languageIds)
            ->with(['translationKey', 'language'])
            ->get();

        $result = [];
        foreach ($translations as $translation) {
            $key = $translation->translationKey->key;
            $langCode = $translation->language->code;
            
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            
            $result[$key][$langCode] = $translation->text;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function bulkUpdate(array $translationsData, int $updatedBy): int
    {
        $count = 0;
        
        DB::beginTransaction();
        try {
            foreach ($translationsData as $data) {
                $translation = $this->findByKeyAndLanguage(
                    $data['translation_key_id'],
                    $data['language_id']
                );
                
                if ($translation) {
                    // Update existing translation
                    $translation->text = $data['text'];
                    $translation->updated_by = $updatedBy;
                    $translation->status = $data['status'] ?? $translation->status;
                    $translation->is_machine_translated = $data['is_machine_translated'] ?? $translation->is_machine_translated;
                    $translation->save();
                } else {
                    // Create new translation
                    $this->create([
                        'translation_key_id' => $data['translation_key_id'],
                        'language_id' => $data['language_id'],
                        'text' => $data['text'],
                        'status' => $data['status'] ?? 'draft',
                        'is_machine_translated' => $data['is_machine_translated'] ?? false,
                        'updated_by' => $updatedBy,
                    ]);
                }
                
                $count++;
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $count;
    }
} 