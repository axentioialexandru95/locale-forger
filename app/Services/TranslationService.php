<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Project;
use App\Models\TranslationKey;
use App\Repositories\Interfaces\TranslationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Class TranslationService
 * 
 * Handles business logic related to translations
 * 
 * @package App\Services
 */
class TranslationService
{
    /**
     * @var TranslationRepositoryInterface
     */
    protected TranslationRepositoryInterface $translationRepository;

    /**
     * TranslationService constructor.
     * 
     * @param TranslationRepositoryInterface $translationRepository
     */
    public function __construct(TranslationRepositoryInterface $translationRepository)
    {
        $this->translationRepository = $translationRepository;
    }

    /**
     * Get all translations for a project.
     * 
     * @param int $projectId
     * @param int|null $languageId
     * @return Collection
     */
    public function getProjectTranslations(int $projectId, ?int $languageId = null): Collection
    {
        return $this->translationRepository->getByProject($projectId, $languageId);
    }

    /**
     * Get translations formatted for export.
     * 
     * @param int $projectId
     * @param array $languageIds
     * @return array
     */
    public function getTranslationsForExport(int $projectId, array $languageIds): array
    {
        return $this->translationRepository->getGroupedForExport($projectId, $languageIds);
    }

    /**
     * Find missing translations for a project and language.
     * 
     * @param int $projectId
     * @param int $languageId
     * @return Collection
     */
    public function findMissingTranslations(int $projectId, int $languageId): Collection
    {
        return $this->translationRepository->getMissingTranslations($projectId, $languageId);
    }

    /**
     * Update or create a translation.
     * 
     * @param int $keyId
     * @param int $languageId
     * @param string $text
     * @param bool $isMachineTranslated
     * @param string $status
     * @return bool
     */
    public function updateTranslation(
        int $keyId,
        int $languageId,
        string $text,
        bool $isMachineTranslated = false,
        string $status = 'draft'
    ): bool {
        $translation = $this->translationRepository->findByKeyAndLanguage($keyId, $languageId);
        
        $data = [
            'translation_key_id' => $keyId,
            'language_id' => $languageId,
            'text' => $text,
            'is_machine_translated' => $isMachineTranslated,
            'status' => $status,
            'updated_by' => Auth::id(),
        ];
        
        if ($translation) {
            $translation->update($data);
            return true;
        } else {
            $this->translationRepository->create($data);
            return true;
        }
    }

    /**
     * Bulk update translations.
     * 
     * @param array $translationsData
     * @return int Number of updated translations
     */
    public function bulkUpdateTranslations(array $translationsData): int
    {
        return $this->translationRepository->bulkUpdate($translationsData, Auth::id());
    }

    /**
     * Copy translations from one language to another within a project.
     * 
     * @param int $projectId
     * @param int $sourceLanguageId
     * @param int $targetLanguageId
     * @param bool $overwrite Whether to overwrite existing translations
     * @return int Number of copied translations
     */
    public function copyTranslations(
        int $projectId,
        int $sourceLanguageId,
        int $targetLanguageId,
        bool $overwrite = false
    ): int {
        $sourceTranslations = $this->translationRepository->getByProject($projectId, $sourceLanguageId);
        
        $translationsData = [];
        foreach ($sourceTranslations as $translation) {
            // Check if target translation exists
            $targetTranslation = $this->translationRepository->findByKeyAndLanguage(
                $translation->translation_key_id,
                $targetLanguageId
            );
            
            // Skip if target exists and we're not overwriting
            if ($targetTranslation && !$overwrite) {
                continue;
            }
            
            $translationsData[] = [
                'translation_key_id' => $translation->translation_key_id,
                'language_id' => $targetLanguageId,
                'text' => $translation->text,
                'is_machine_translated' => false, // It's a manual copy, not machine translated
                'status' => 'draft', // Start as draft since it's a copy
            ];
        }
        
        return $this->translationRepository->bulkUpdate($translationsData, Auth::id());
    }
} 