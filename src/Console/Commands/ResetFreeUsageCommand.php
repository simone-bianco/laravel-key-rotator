<?php

namespace SimoneBianco\LaravelKeyRotator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use SimoneBianco\LaravelKeyRotator\Models\RotableApiKey;

class ResetFreeUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'key-rotator:reset-free-usage 
                            {--force : Force the operation without confirmation}
                            {--dry-run : Show what would be reset without actually resetting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset free usage for keys that are due for a reset based on their schedule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        
        $this->newLine();
        $this->line("🔄 \e[1;34mResetting free usage for scheduled keys...\e[0m");
        $this->line('======================================');
        
        try {
            // Find keys that need their free usage reset
            $keysToReset = $this->getKeysToReset();
            
            if ($keysToReset->isEmpty()) {
                $this->info('   ✅ No keys are due for free usage reset at this time.');
                return self::SUCCESS;
            }
            
            // Display the keys that will be reset
            $this->line("   Found {$keysToReset->count()} key(s) due for reset:");
            $this->newLine();
            
            $this->table(
                ['ID', 'Service', 'Free Limit Type', 'Current Free Usage', 'Max Free Usage', 'Last Reset', 'Next Reset'],
                $keysToReset->map(function ($key) {
                    return [
                        $key->id,
                        $key->service,
                        $key->free_limit_type,
                        number_format($key->current_free_usage, 2),
                        number_format($key->max_free_usage ?? 0, 2),
                        $key->last_free_usage_reset_at?->format('Y-m-d H:i') ?? 'Never',
                        $key->free_usage_resets_at?->format('Y-m-d H:i') ?? 'N/A',
                    ];
                })->toArray()
            );
            
            if ($dryRun) {
                $this->comment('   🔍 Dry run mode - no changes were made.');
                return self::SUCCESS;
            }
            
            // Ask for confirmation unless --force is used
            if (!$force && !$this->confirm('Proceed with resetting free usage for these keys?', true)) {
                $this->comment('   Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Reset the free usage
            $resetCount = 0;
            
            foreach ($keysToReset as $key) {
                $this->resetKeyFreeUsage($key);
                $resetCount++;
            }
            
            // Display success message
            $this->line('   ----------------------------------------');
            $this->info("🎉 \e[1;32mFree usage reset successfully!\e[0m");
            $this->comment("   Reset {$resetCount} key(s)");
            $this->newLine();
            
            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
            $this->error('   ' . $e->getTraceAsString());
            return self::FAILURE;
        }
    }
    
    /**
     * Get the keys that need their free usage reset.
     */
    protected function getKeysToReset()
    {
        return RotableApiKey::query()
            ->where('is_active', true)
            ->whereIn('free_limit_type', ['daily', 'monthly'])
            ->where(function ($query) {
                // Keys that have never been reset
                $query->whereNull('last_free_usage_reset_at')
                    // OR keys where the reset time has passed
                    ->orWhere(function ($q) {
                        $q->whereNotNull('free_usage_resets_at')
                          ->where('free_usage_resets_at', '<=', now());
                    });
            })
            ->get();
    }
    
    /**
     * Reset the free usage for a specific key.
     */
    protected function resetKeyFreeUsage(RotableApiKey $key): void
    {
        $now = now();
        $timezone = $key->reset_timezone ?? 'UTC';
        
        // Calculate the next reset time based on the limit type
        $nextResetAt = match ($key->free_limit_type) {
            'daily' => Carbon::now($timezone)->addDay()->startOfDay(),
            'monthly' => Carbon::now($timezone)->addMonth()->startOfMonth(),
            default => null,
        };
        
        // Reset the free usage
        $key->update([
            'current_free_usage' => 0,
            'last_free_usage_reset_at' => $now,
            'free_usage_resets_at' => $nextResetAt,
            'is_depleted' => false,
            'depleted_at' => null,
        ]);
        
        $this->line("   ✓ Reset key ID {$key->id} ({$key->service}) - Next reset: " . ($nextResetAt?->format('Y-m-d H:i') ?? 'N/A'));
    }
}

