<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TranslationKey;
use App\Models\Translation;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a demo organization
        $organization = Organization::create([
            'name' => 'Demo Organization',
            'deepl_api_key' => null, // Add your DeepL API key here if needed
        ]);

        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'organization_id' => $organization->id,
            'role' => 'admin',
        ]);

        // Create translator user
        User::create([
            'name' => 'Translator User',
            'email' => 'translator@example.com',
            'password' => Hash::make('password'),
            'organization_id' => $organization->id,
            'role' => 'translator',
        ]);

        // Create common languages
        $languages = [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'French'],
            ['code' => 'es', 'name' => 'Spanish'],
            ['code' => 'de', 'name' => 'German'],
            ['code' => 'it', 'name' => 'Italian'],
            ['code' => 'ja', 'name' => 'Japanese'],
            ['code' => 'zh', 'name' => 'Chinese'],
        ];

        foreach ($languages as $language) {
            Language::create($language);
        }

        // English is primary language
        $primaryLanguage = Language::where('code', 'en')->first();

        // Create a demo project
        $project = Project::create([
            'name' => 'Demo Website',
            'slug' => 'demo-website',
            'organization_id' => $organization->id,
            'primary_language_id' => $primaryLanguage->id,
        ]);

        // Add languages to the project
        $project->languages()->attach(Language::pluck('id'));

        // Create some example translation keys
        $translationKeys = [
            [
                'key' => 'welcome.title',
                'description' => 'Main welcome message on homepage',
            ],
            [
                'key' => 'welcome.subtitle',
                'description' => 'Secondary welcome message',
            ],
            [
                'key' => 'nav.home',
                'description' => 'Home navigation item',
            ],
            [
                'key' => 'nav.about',
                'description' => 'About navigation item',
            ],
            [
                'key' => 'nav.contact',
                'description' => 'Contact navigation item',
            ],
            [
                'key' => 'footer.copyright',
                'description' => 'Copyright text in footer',
            ],
        ];

        // Sample translations for each key
        $translations = [
            'welcome.title' => [
                'en' => 'Welcome to our website',
                'fr' => 'Bienvenue sur notre site web',
                'es' => 'Bienvenido a nuestro sitio web',
                'de' => 'Willkommen auf unserer Webseite',
            ],
            'welcome.subtitle' => [
                'en' => 'Discover our amazing products and services',
                'fr' => 'Découvrez nos produits et services incroyables',
                'es' => 'Descubra nuestros increíbles productos y servicios',
                'de' => 'Entdecken Sie unsere erstaunlichen Produkte und Dienstleistungen',
            ],
            'nav.home' => [
                'en' => 'Home',
                'fr' => 'Accueil',
                'es' => 'Inicio',
                'de' => 'Startseite',
            ],
            'nav.about' => [
                'en' => 'About Us',
                'fr' => 'À propos de nous',
                'es' => 'Sobre nosotros',
                'de' => 'Über uns',
            ],
            'nav.contact' => [
                'en' => 'Contact',
                'fr' => 'Contact',
                'es' => 'Contacto',
                'de' => 'Kontakt',
            ],
            'footer.copyright' => [
                'en' => '© 2025 Demo Company. All rights reserved.',
                'fr' => '© 2025 Demo Company. Tous droits réservés.',
                'es' => '© 2025 Demo Company. Todos los derechos reservados.',
                'de' => '© 2025 Demo Company. Alle Rechte vorbehalten.',
            ],
        ];

        foreach ($translationKeys as $keyData) {
            $key = TranslationKey::create([
                'project_id' => $project->id,
                'key' => $keyData['key'],
                'description' => $keyData['description'],
            ]);

            // Add translations for this key
            $keyTranslations = $translations[$keyData['key']];
            foreach ($keyTranslations as $langCode => $text) {
                $language = Language::where('code', $langCode)->first();
                if ($language) {
                    Translation::create([
                        'translation_key_id' => $key->id,
                        'language_id' => $language->id,
                        'text' => $text,
                        'is_machine_translated' => false,
                        'status' => 'final',
                    ]);
                }
            }
        }
    }
}
