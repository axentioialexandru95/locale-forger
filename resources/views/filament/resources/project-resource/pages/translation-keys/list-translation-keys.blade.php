<x-filament::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Project: {{ $record->name }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Primary Language: {{ $record->primaryLanguage->name }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($record->languages as $language)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                        {{ $language->name }}
                    </span>
                @endforeach
            </div>
        </div>
        
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Using Groups</h3>
            <p class="mt-1 text-xs text-yellow-700 dark:text-yellow-200">
                Groups help organize related translations. You can create groups in two ways:
            </p>
            <ul class="mt-2 list-disc list-inside text-xs text-yellow-700 dark:text-yellow-200">
                <li>When creating or editing a translation key, click the + icon next to the Group field</li>
                <li>Visit the <a href="{{ route('filament.admin.pages.manage-groups') }}" class="underline">Manage Groups</a> page to create and manage groups directly</li>
            </ul>
        </div>
        
        {{ $this->table }}
    </div>
</x-filament::page> 