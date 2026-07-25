<?php

namespace Tests\Feature;

use App\Support\ComposerLocalModules;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ComposerLocalModulesCommandTest extends TestCase
{
    public function test_enable_and_disable_update_composer_local_json(): void
    {
        $originalComposerLocal = file_exists(base_path('composer.local.json'))
            ? file_get_contents(base_path('composer.local.json'))
            : null;

        try {
            Artisan::call('app:plugins', [
                'action' => 'disable-all',
            ]);

            $this->assertSame([], ComposerLocalModules::at(base_path())->enabledPackagePaths());

            Artisan::call('app:plugins', [
                'action' => 'enable',
                'targets' => ['desktop-native', 'account-manager'],
            ]);

            $this->assertSame([
                'ext/desktop-native/composer.json',
                'mod/account-manager/composer.json',
            ], ComposerLocalModules::at(base_path())->enabledPackagePaths());

            Artisan::call('app:plugins', [
                'action' => 'disable',
                'targets' => ['desktop-native'],
            ]);

            $this->assertSame([
                'mod/account-manager/composer.json',
            ], ComposerLocalModules::at(base_path())->enabledPackagePaths());
        } finally {
            if ($originalComposerLocal === null) {
                @unlink(base_path('composer.local.json'));
            } else {
                file_put_contents(base_path('composer.local.json'), $originalComposerLocal);
            }
        }
    }

    public function test_list_supports_directory_include_paths_in_composer_local_json(): void
    {
        $originalComposerLocal = file_exists(base_path('composer.local.json'))
            ? file_get_contents(base_path('composer.local.json'))
            : null;

        try {
            file_put_contents(base_path('composer.local.json'), <<<'JSON'
{
    "extra": {
        "merge-plugin": {
            "include": [
                "ext/desktop-native/",
                "mod/account-manager/composer.json"
            ]
        }
    }
}
JSON
            );

            $this->assertSame(0, Artisan::call('app:plugins', [
                'action' => 'list',
            ]));
        } finally {
            if ($originalComposerLocal === null) {
                @unlink(base_path('composer.local.json'));
            } else {
                file_put_contents(base_path('composer.local.json'), $originalComposerLocal);
            }
        }
    }

    public function test_resolved_merge_plugin_includes_follow_required_composer_local_file(): void
    {
        $originalComposerLocal = file_exists(base_path('composer.local.json'))
            ? file_get_contents(base_path('composer.local.json'))
            : null;

        try {
            file_put_contents(base_path('composer.local.json'), <<<'JSON'
{
    "extra": {
        "merge-plugin": {
            "include": [
                "mod/account-manager/composer.json",
                "mod/multi-site/composer.json"
            ]
        }
    }
}
JSON
            );

            $this->assertSame([
                base_path('mod/account-manager/composer.json'),
                base_path('mod/multi-site/composer.json'),
            ], ComposerLocalModules::at(base_path())->resolvedMergePluginIncludes());
        } finally {
            if ($originalComposerLocal === null) {
                @unlink(base_path('composer.local.json'));
            } else {
                file_put_contents(base_path('composer.local.json'), $originalComposerLocal);
            }
        }
    }
}
