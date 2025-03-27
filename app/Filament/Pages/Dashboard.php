<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\TranslationKey;
use App\Models\Translation;
use App\Models\Language;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Actions\Action;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('how_to_use')
                ->label('How to use Groups')
                ->icon('heroicon-o-question-mark-circle')
                ->modalContent(view('filament.pages.help-guide')),
            Action::make('create_project')
                ->label('Create Project')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.projects.create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TranslationStatusWidget::class,
            ProjectStatusWidget::class,
            TranslationCompletionChart::class,
            RecentUpdatesWidget::class,
            SimpleMachineTranslationWidget::class,
        ];
    }
}

class TranslationStatusWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Projects', Project::count())
                ->description('Manage your translation projects')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
            
            Stat::make('Total Translation Keys', TranslationKey::count())
                ->description('Keys across all projects')
                ->descriptionIcon('heroicon-m-key')
                ->color('success')
                ->chart([15, 10, 20, 18, 25, 22, 30]),
                
            Stat::make('Translations', Translation::count())
                ->description('Total translations')
                ->descriptionIcon('heroicon-m-language')
                ->color('warning')
                ->chart([50, 40, 90, 80, 100, 95, 110]),
        ];
    }
}

class ProjectStatusWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Count incomplete translations
        $totalIncompleteTranslations = DB::table('translation_keys')
            ->join('projects', 'translation_keys.project_id', '=', 'projects.id')
            ->join('project_languages', function ($join) {
                $join->on('projects.id', '=', 'project_languages.project_id');
            })
            ->leftJoin('translations', function ($join) {
                $join->on('translation_keys.id', '=', 'translations.translation_key_id')
                    ->on('project_languages.language_id', '=', 'translations.language_id');
            })
            ->whereNull('translations.id')
            ->count();
            
        $groupCount = TranslationKey::whereNotNull('group')->distinct('group')->count('group');
        
        return [
            Stat::make('Available Languages', Language::count())
                ->description('Languages available for translation')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),
                
            Stat::make('Translation Groups', $groupCount)
                ->description('Organize related translations')
                ->descriptionIcon('heroicon-m-folder')
                ->color('success'),
                
            Stat::make('Missing Translations', $totalIncompleteTranslations)
                ->description('Translations that need attention')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
        ];
    }
}

class TranslationCompletionChart extends ChartWidget
{
    protected static ?string $heading = 'Translation Completion';
    
    protected function getData(): array
    {
        $projects = Project::withCount('translationKeys')->take(5)->get();
        
        $labels = $projects->pluck('name')->toArray();
        $data = [];
        
        foreach ($projects as $project) {
            // Get total possible translations
            $possibleCount = $project->translationKeys->count() * $project->languages->count();
            
            // Get actual translations
            $actualCount = DB::table('translations')
                ->join('translation_keys', 'translations.translation_key_id', '=', 'translation_keys.id')
                ->where('translation_keys.project_id', $project->id)
                ->count();
                
            // Calculate completion percentage
            $percentage = $possibleCount > 0 ? round(($actualCount / $possibleCount) * 100) : 0;
            $data[] = $percentage;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Completion %',
                    'data' => $data,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }
}

class RecentUpdatesWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $recentTranslations = Translation::orderBy('updated_at', 'desc')
            ->take(5)
            ->get();
            
        $stats = [];
        
        foreach ($recentTranslations as $translation) {
            $stats[] = Stat::make(
                $translation->translationKey->key,
                $translation->language->name . ' (' . $translation->status . ')'
            )
                ->description('Updated: ' . $translation->updated_at->diffForHumans())
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary');
        }
        
        return $stats;
    }
}

class SimpleMachineTranslationWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;
    
    protected function getStats(): array
    {
        // Simplified stats to avoid potential issues
        $machineTranslationCount = Translation::where('is_machine_translated', true)->count();
        $totalTranslations = Translation::count();
        $percentMachineTranslated = $totalTranslations > 0 ? round(($machineTranslationCount / $totalTranslations) * 100) : 0;
        
        // Mock data for API usage
        $charactersUsed = 125000; // 25% of quota
        $characterLimit = 500000; // DeepL free tier limit
        
        return [
            Stat::make('Machine Translations', number_format($machineTranslationCount))
                ->description($percentMachineTranslated . '% of all translations')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('primary'),
                
            Stat::make('DeepL API Usage', number_format($charactersUsed) . ' / ' . number_format($characterLimit))
                ->description('25% of monthly quota used')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),
                
            Stat::make('Monthly Projection', '275,000 chars')
                ->description('55% of quota')
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color('warning'),
        ];
    }
}