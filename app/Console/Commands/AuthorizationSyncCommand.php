<?php

namespace App\Console\Commands;

use App\Services\Authorization\AuthorizationSynchronizer;
use Illuminate\Console\Command;

class AuthorizationSyncCommand extends Command
{
    protected $signature = 'authorization:sync {--dry-run : Report changes without modifying the database}';

    protected $description = 'Synchronize source-controlled authorization safely';

    public function handle(AuthorizationSynchronizer $synchronizer): int
    {
        $result = $synchronizer->synchronize((bool) $this->option('dry-run'));

        $this->info($result['dry_run'] ? 'Authorization synchronization dry run' : 'Authorization synchronization complete');
        $this->line('Canonical permissions: '.$result['canonical']);
        $this->line('Present canonical permissions: '.$result['present']);
        $this->line('Created permissions: '.$result['created']);
        $this->line('Roles created: '.$result['roles_created']);
        $this->line('Role grants added: '.$result['grants_added']);
        $this->line('Unexpected permissions retained: '.count($result['extra']));
        $this->line('Cache reset: '.($result['cache_reset'] ? 'yes' : 'no'));

        $this->renderList('Missing permissions', $result['missing']);
        $this->renderList('Unexpected permissions', $result['extra']);

        foreach ($result['managed_roles'] as $role => $details) {
            $this->line(sprintf('Managed role %s: %s, missing grants: %d', $role, $details['exists'] ? 'present' : 'created/absent', count($details['missing'])));
        }

        return self::SUCCESS;
    }

    /** @param list<string> $values */
    private function renderList(string $label, array $values): void
    {
        if ($values === []) {
            return;
        }

        $this->warn($label.':');
        foreach ($values as $value) {
            $this->line('  '.$value);
        }
    }
}
