# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in ___SINGLE_BACKTICK___resources/js/Pages___SINGLE_BACKTICK___ (unless specified in ___SINGLE_BACKTICK___vite.config.js___SINGLE_BACKTICK___). Use ___SINGLE_BACKTICK___Inertia::render()___SINGLE_BACKTICK___ for server-side routing instead of Blade views.
- ALWAYS use ___SINGLE_BACKTICK___search-docs___SINGLE_BACKTICK___ tool for version-specific Inertia documentation and updated code examples.
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($assist->hasPackage(\Laravel\Roster\Enums\Packages::INERTIA_REACT)): ?>
- IMPORTANT: Activate ___SINGLE_BACKTICK___inertia-react-development___SINGLE_BACKTICK___ when working with Inertia client-side patterns.
<?php elseif($assist->hasPackage(\Laravel\Roster\Enums\Packages::INERTIA_VUE)): ?>
- IMPORTANT: Activate ___SINGLE_BACKTICK___inertia-vue-development___SINGLE_BACKTICK___ when working with Inertia Vue client-side patterns.
<?php elseif($assist->hasPackage(\Laravel\Roster\Enums\Packages::INERTIA_SVELTE)): ?>
- IMPORTANT: Activate ___SINGLE_BACKTICK___inertia-svelte-development___SINGLE_BACKTICK___ when working with Inertia Svelte client-side patterns.
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/webong/Workspace/Projects/Akive/figurate/storage/framework/views/33b8394506957f68308bf5760c90dfd6.blade.php ENDPATH**/ ?>