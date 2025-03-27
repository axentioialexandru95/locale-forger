<?php

namespace Tests\Unit\Services;

use App\Services\InterpolationService;
use PHPUnit\Framework\TestCase;

class InterpolationServiceTest extends TestCase
{
    private InterpolationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InterpolationService();
    }

    public function testParse()
    {
        $template = 'Hello, {name}! Welcome to {location}.';
        $variables = [
            'name' => 'John',
            'location' => 'Paris'
        ];

        $result = $this->service->parse($template, $variables);
        $this->assertEquals('Hello, John! Welcome to Paris.', $result);
    }

    public function testParseWithMissingVariables()
    {
        $template = 'Hello, {name}! Welcome to {location}.';
        $variables = [
            'name' => 'John'
        ];

        $result = $this->service->parse($template, $variables);
        $this->assertEquals('Hello, John! Welcome to {location}.', $result);
    }

    public function testExtractVariables()
    {
        $template = 'Hello, {name}! You have {count} items in your {container}.';
        
        $variables = $this->service->extractVariables($template);
        $this->assertEquals(['name', 'count', 'container'], $variables);
    }

    public function testValidateVariablesSuccess()
    {
        $source = 'Hello, {name}! You have {count} messages.';
        $translation = 'Bonjour, {name}! Vous avez {count} messages.';
        
        $result = $this->service->validateVariables($source, $translation);
        $this->assertTrue($result);
    }

    public function testValidateVariablesFailure()
    {
        $source = 'Hello, {name}! You have {count} messages.';
        $translation = 'Bonjour, {name}! Vous avez des messages.';
        
        $result = $this->service->validateVariables($source, $translation);
        $this->assertFalse($result);
    }

    public function testHandleComplexSource()
    {
        $source = 'Please click <a href="{url}">here</a> to verify your email address within {hours} hours.';
        $variables = [
            'url' => 'https://example.com/verify',
            'hours' => 24
        ];
        
        $result = $this->service->parse($source, $variables);
        $this->assertEquals('Please click <a href="https://example.com/verify">here</a> to verify your email address within 24 hours.', $result);
    }
} 