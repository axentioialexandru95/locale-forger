<?php

namespace App\Services;

class InterpolationService
{
    /**
     * Parse a string with interpolation variables
     *
     * @param string $template String with {variable} placeholders
     * @param array $variables Key-value pair of variables to replace
     * @return string
     */
    public function parse(string $template, array $variables): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($variables) {
            $key = $matches[1];
            return $variables[$key] ?? $matches[0];
        }, $template);
    }
    
    /**
     * Extract all interpolation variables from a string
     *
     * @param string $template String with {variable} placeholders
     * @return array List of variable names
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Validate if a translation contains all required variables
     *
     * @param string $source Source string (usually in base language)
     * @param string $translation Translation to validate
     * @return bool
     */
    public function validateVariables(string $source, string $translation): bool
    {
        $sourceVars = $this->extractVariables($source);
        $translationVars = $this->extractVariables($translation);
        
        return empty(array_diff($sourceVars, $translationVars));
    }
} 