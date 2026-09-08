<?php

namespace Tests\Feature\Livewire;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Livewire ships client-side helpers on $wire under these names. A PHP action
 * with the same name is shadowed by the JS one: wire:submit="upload" calls
 * $wire.upload() with no arguments, which throws on `undefined.name` and never
 * reaches the server. The form then does nothing at all — no request, no
 * validation message, no log line — which is close to undiagnosable from the
 * outside. The gallery shipped with exactly that bug.
 */
class ReservedActionNameTest extends TestCase
{
    /** @var array<int, string> */
    private const RESERVED = ['upload', 'uploadMultiple', 'removeUpload'];

    public function test_no_livewire_component_defines_an_action_livewire_reserves(): void
    {
        $offenders = [];

        foreach ($this->componentClasses() as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Only what we wrote — Livewire's own base class legitimately
                // carries some of these names.
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                if (in_array($method->getName(), self::RESERVED, true)) {
                    $offenders[] = $class.'::'.$method->getName().'()';
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ["These Livewire actions are shadowed by Livewire's own \$wire helpers and will",
                'never run. Rename them (for example upload() to addPhotos()):'],
            $offenders,
        )));
    }

    /**
     * Every component class under app/Livewire.
     *
     * @return array<int, class-string>
     */
    private function componentClasses(): array
    {
        $classes = [];
        $separators = ['/', DIRECTORY_SEPARATOR];

        foreach (Finder::create()->files()->in(app_path('Livewire'))->name('*.php') as $file) {
            $relative = str_replace($separators, '\\', $file->getRelativePathname());
            $classes[] = 'App\\Livewire\\'.substr($relative, 0, -strlen('.php'));
        }

        return $classes;
    }
}
