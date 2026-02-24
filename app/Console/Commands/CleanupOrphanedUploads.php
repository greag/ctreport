<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedUploads extends Command
{
    protected $signature = 'reports:cleanup-uploads {--days=7 : Only delete uploads older than this many days} {--force : Actually delete files}';
    protected $description = 'Delete uploaded PDFs that are not referenced by any results metadata.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $days = 0;
        }
        $force = (bool) $this->option('force');

        $cutoff = now()->subDays($days)->getTimestamp();
        $referenced = $this->loadReferencedUploads();

        $candidates = 0;
        $deleted = 0;

        foreach (Storage::files('uploads') as $path) {
            if (!str_ends_with(strtolower($path), '.pdf')) {
                continue;
            }
            if (isset($referenced[$path])) {
                continue;
            }
            $modified = Storage::lastModified($path);
            if ($modified > $cutoff) {
                continue;
            }
            $candidates++;
            if ($force) {
                Storage::delete($path);
                $deleted++;
                $this->line("Deleted: {$path}");
            } else {
                $this->line("Would delete: {$path}");
            }
        }

        if ($force) {
            $this->info("Deleted {$deleted} orphaned upload(s).");
        } else {
            $this->info("Found {$candidates} orphaned upload(s). Run with --force to delete.");
        }

        return Command::SUCCESS;
    }

    private function loadReferencedUploads(): array
    {
        $referenced = [];
        foreach (Storage::files('results') as $path) {
            if (!str_ends_with($path, '.json') || str_contains($path, '_validation.json')) {
                continue;
            }
            $meta = json_decode(Storage::get($path), true);
            if (!is_array($meta)) {
                continue;
            }
            $uploadPath = (string) (($meta['upload']['path'] ?? '') ?: ($meta['storage']['upload_path'] ?? ''));
            if ($uploadPath !== '') {
                $referenced[$uploadPath] = true;
            }
        }
        return $referenced;
    }
}
