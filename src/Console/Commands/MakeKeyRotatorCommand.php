<?php

namespace SimoneBianco\LaravelKeyRotator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeKeyRotatorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:key-rotator {name : The name of the KeyRotator class (e.g., OpenAIKeyRotator)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new KeyRotator class';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line("🚀 \e[1;34mCreating a new KeyRotator class...\e[0m");
        $this->line('======================================');

        try {
            // Get the class name from the user input
            $name = (string) $this->argument('name');
            
            // Ensure the name ends with 'KeyRotator'
            if (!str_ends_with($name, 'KeyRotator')) {
                $name .= 'KeyRotator';
            }
            
            // Convert to StudlyCase
            $className = Str::studly($name);
            
            // Determine the directory and file path
            $directory = app_path('KeyRotators');
            $filePath = $directory . '/' . $className . '.php';
            
            // Check if the file already exists
            if (File::exists($filePath)) {
                $this->error("   ❌ KeyRotator class '{$className}' already exists!");
                return self::FAILURE;
            }
            
            // Create the directory if it doesn't exist
            if (!File::isDirectory($directory)) {
                $this->line('   - Creating KeyRotators directory...');
                File::makeDirectory($directory, 0755, true);
            }
            
            // Ask for service name
            $serviceName = $this->ask('What is the service name? (e.g., openai, anthropic)', Str::snake(str_replace('KeyRotator', '', $className)));
            
            // Ask for config key
            $configKey = $this->ask('What is the config key? (e.g., services.openai.api_key)', "services.{$serviceName}.api_key");
            
            // Ask if multiple config keys are needed
            $multipleKeys = $this->confirm('Do you need to inject the key into multiple config locations?', false);
            
            $configKeyDeclaration = $multipleKeys 
                ? "protected static array \$configKey = [\n        '{$configKey}',\n        // Add more config keys here\n    ];"
                : "protected static string \$configKey = '{$configKey}';";
            
            // Generate the file content
            $content = $this->getStubContent($className, $serviceName, $configKeyDeclaration);
            
            // Write the file
            File::put($filePath, $content);
            
            // Display success message
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            $this->line('   ----------------------------------------');
            $this->info("🎉 \e[1;32mKeyRotator class created successfully!\e[0m");
            $this->comment("   File created at: \e[0;33m{$relativePath}\e[0m");
            $this->newLine();
            $this->comment("   Next steps:");
            $this->comment("   1. Register your API keys using the registerKey() method");
            $this->comment("   2. Use {$className}::make()->pickKey()->injectKey() to rotate keys");
            $this->newLine();
            
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
    
    /**
     * Get the stub content for the KeyRotator class.
     */
    protected function getStubContent(string $className, string $serviceName, string $configKeyDeclaration): string
    {
        return <<<PHP
<?php

namespace App\KeyRotators;

use SimoneBianco\LaravelKeyRotator\KeyRotator;

/**
 * KeyRotator for {$serviceName} service.
 * 
 * This class manages API key rotation for {$serviceName}.
 * You can override any method to customize the behavior.
 */
class {$className} extends KeyRotator
{
    /**
     * The service name as stored in the database.
     */
    protected static string \$serviceName = '{$serviceName}';

    /**
     * The Laravel configuration key(s) to override.
     */
    {$configKeyDeclaration}
    
    // You can override methods here to customize behavior:
    
    // public function pickKey(): static
    // {
    //     // Your custom key selection logic here
    //     return parent::pickKey();
    // }
    
    // public function injectKey(): static
    // {
    //     parent::injectKey();
    //     
    //     // Your custom logic here (e.g., inject extra_data values)
    //     // if (\$this->currentKey->extra_data) {
    //     //     Config::set('services.{$serviceName}.organization', 
    //     //         \$this->currentKey->extra_data['organization_id'] ?? null);
    //     // }
    //     
    //     return \$this;
    // }
    
    // public function isDepletedException(Exception \$exception): bool
    // {
    //     // Your custom depletion detection logic here
    //     return parent::isDepletedException(\$exception);
    // }
}

PHP;
    }
}

