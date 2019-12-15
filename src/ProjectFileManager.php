<?php

namespace PipeDream\LaravelCreate;

use File;
use Storage;

class ProjectFileManager
{
    public function __construct($isSandboxed)
    {
        $this->isSandboxed = $isSandboxed;
        if ($this->isSandboxed) {
            $this->setupSandboxProject();
        }
        $this->history = [];
    }

    public static function make($isSandboxed)
    {
        return new self($isSandboxed);
    }

    public function write($files)
    {
        // $this->recordCurrentStateFor($files);

        collect($files)->each(function ($file) {
            $this->storage()->put($file->path, $file->content);
        });
    }

    public function delete($files)
    {
        // $this->recordCurrentStateFor($files);

        collect($files)->each(function ($file) {
            $this->storage()->delete($file->path);
        });
    }

    public function reverseHistory()
    {
        collect($this->loadHistory())->each(function ($content, $path) {
            if ($content == null) {
                return $this->storage()->delete($path);
            }

            $this->storage()->put($path, $content);
        });
    }

    public function persistHistory()
    {
        $this->storage()->put(
            'pipe-dream-history.json',
            json_encode($this->history)
        );
    }

    public function deleteDuplicateDefaultMigrations($files)
    {
        if(collect($files)->pluck('path')->filter(function($path) {
            return preg_match('/create_users_table/', $path);
        })->isNotEmpty()) {
            $this->delete([
                json_decode('{"path": "database/migrations/2014_10_12_000000_create_users_table.php"}')
            ]);            
        }
        
        if(collect($files)->pluck('path')->filter(function($path) {
            return preg_match('/create_password_resets_table/', $path);
        })->isNotEmpty()) {
            $this->delete([
                json_decode('{"path": "database/migrations/2014_10_12_100000_create_password_resets_table.php"}')
            ]);        
        }
    }

    /*********** PRIVATE ************************************************** */
    private function setupSandboxProject()
    {
        app()['config']['filesystems.disks.pipe-dream-sandbox-project'] = [
            'driver' => 'local',
            'root'   => storage_path('pipe-dream/sandbox/test-project'),
        ];

        if (!Storage::disk('pipe-dream')->exists('sandbox/test-project')) {
            File::copyDirectory(
                storage_path('pipe-dream/laravel-including-vendors'),
                storage_path('pipe-dream/sandbox/test-project')
            );
        }
    }

    private function loadHistory()
    {
        return $this->storage()->has('pipe-dream-history.json') ?
        json_decode($this->storage()->get('pipe-dream-history.json')) : [];
    }

    private function storage()
    {
        return Storage::disk(
            $this->isSandboxed ? 'pipe-dream-sandbox-project' : 'self'
        );
    }

    private function recordCurrentStateFor($files)
    {
        collect($files)->each(function ($file) {
            $this->history[$file->path] =
                $this->storage()->has($file->path) ?
                $this->storage()->get($file->path) :
                null;
        });
    }
}
