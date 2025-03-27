<?php

namespace App\Repositories\Interfaces;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface TranslationRepositoryInterface
 * 
 * @package App\Repositories\Interfaces
 */
interface TranslationRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find translation by key ID and language ID.
     *
     * @param int $keyId
     * @param int $languageId
     * @return Translation|null
     */
    public function findByKeyAndLanguage(int $keyId, int $languageId): ?Translation;
    
    /**
     * Get translations for a specific project.
     *
     * @param int $projectId
     * @param int|null $languageId
     * @return Collection
     */
    public function getByProject(int $projectId, ?int $languageId = null): Collection;
    
    /**
     * Get missing translations for a project and language.
     *
     * @param int $projectId
     * @param int $languageId
     * @return Collection
     */
    public function getMissingTranslations(int $projectId, int $languageId): Collection;
    
    /**
     * Get translations grouped by key for export.
     *
     * @param int $projectId
     * @param array $languageIds
     * @return array
     */
    public function getGroupedForExport(int $projectId, array $languageIds): array;
    
    /**
     * Update multiple translations at once.
     *
     * @param array $translationsData Array of translations with key_id, language_id, and text
     * @param int $updatedBy User ID who updated these translations
     * @return int Number of updated translations
     */
    public function bulkUpdate(array $translationsData, int $updatedBy): int;
} 