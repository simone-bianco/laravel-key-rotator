<?php

namespace SimoneBianco\LaravelKeyRotator\Console\Commands;

use Illuminate\Console\Command;
use SimoneBianco\LaravelKeyRotator\Models\RotableApiKey;

class ResetUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'key-rotator:reset-usage 
                            {service? : The service name to reset usage for (optional, resets all if not provided)}
                            {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset usage counters for all API keys or a specific service';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = $this->argument('service');
        $force = $this->option('force');
        
        $this->newLine();
        $this->line("🔄 \e[1;34mResetting API key usage counters...\e[0m");
        $this->line('======================================');
        
        try {
            // Build the query
            $query = RotableApiKey::query();
            
            if ($service) {
                $query->where('service', $service);
                $message = "Reset usage for all '{$service}' keys?";
            } else {
                $message = "Reset usage for ALL API keys across all services?";
            }
            
            // Get the count of keys that will be affected
            $count = $query->count();
            
            if ($count === 0) {
                $this->warn('   ⚠️  No API keys found to reset.');
                return self::SUCCESS;
            }
            
            // Ask for confirmation unless --force is used
            if (!$force && !$this->confirm($message, false)) {
                $this->comment('   Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Reset the usage counters
            $this->line("   - Resetting usage for {$count} key(s)...");
            
            $updated = $query->update([
                'current_base_usage' => 0,
                'current_free_usage' => 0,
                'is_depleted' => false,
                'depleted_at' => null,
            ]);
            
            // Display success message
            $this->line('   ----------------------------------------');
            $this->info("🎉 \e[1;32mUsage reset successfully!\e[0m");
            $this->comment("   Updated {$updated} key(s)");
            
            if ($service) {
                $this->comment("   Service: {$service}");
            } else {
                $this->comment("   All services");
            }
            
            $this->newLine();
            
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

