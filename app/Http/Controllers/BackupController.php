<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Owner/Admin only. Hands out a complete copy of the database as a download.
 *
 * The live site is hosted on a panel with no SSH access and no scheduled tasks,
 * and the panel's own backup feature is limited to paid remote storage, so this
 * screen is the pharmacy's practical way to get its data off the server.
 */
class BackupController extends Controller
{
    /** Files older than this many days are cleaned up on each run. */
    private const KEEP_DAYS = 2;

    public function index(): View
    {
        return view('backups.index', [
            'lastBackupAt' => Setting::value('last_backup_at'),
            'lastBackupBy' => Setting::value('last_backup_by'),
        ]);
    }

    public function download(DatabaseBackupService $backups): BinaryFileResponse|RedirectResponse
    {
        // The hosting panel locks max_execution_time to 30s. Raising it is
        // refused there, but on a normal host it gives a growing database
        // room to finish.
        @set_time_limit(180);

        $disk = Storage::disk('local');
        $directory = 'backups';
        $disk->makeDirectory($directory);

        $this->pruneOldFiles($disk, $directory);

        $filename = 'trustmed_' . now()->format('Y-m-d_His') . '.sql';
        $path = $disk->path($directory . '/' . $filename);

        try {
            $result = $backups->writeTo($path);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'The backup could not be created: ' . $e->getMessage());
        }

        Setting::put('last_backup_at', now()->toDateTimeString());
        Setting::put('last_backup_by', (string) (auth()->user()->username ?? auth()->user()->name ?? ''));

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'backup',
            // Not a database record, so there is no id. The detail that
            // matters is in `changes`.
            'model_type' => 'Backup',
            'model_id' => 0,
            'changes' => [
                'file' => $filename,
                'tables' => $result['tables'],
                'rows' => $result['rows'],
                'kilobytes' => round($result['bytes'] / 1024, 1),
            ],
        ]);

        return response()
            ->download($path, $filename, ['Content-Type' => 'application/sql'])
            ->deleteFileAfterSend(true);
    }

    /**
     * A download that is interrupted (browser closed, connection dropped)
     * leaves its file behind, because nothing calls deleteFileAfterSend. Clear
     * those out so the storage folder cannot grow without limit.
     */
    private function pruneOldFiles($disk, string $directory): void
    {
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();

        foreach ($disk->files($directory) as $file) {
            if (!str_ends_with($file, '.sql')) {
                continue;
            }

            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
