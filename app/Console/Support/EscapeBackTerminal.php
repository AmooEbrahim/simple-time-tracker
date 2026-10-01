<?php

namespace App\Console\Support;

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use Throwable;

/**
 * Makes a bare Esc press act as laravel/prompts' built-in "go back" key (Ctrl+U), which
 * makes the active prompt throw FormRevertedException for the caller to handle.
 *
 * Prompts has no public hook for a terminal, so this swaps the shared instance via
 * reflection. If that ever stops working, enable() reports false and the UI simply
 * degrades to "no Esc" (Ctrl+U still works).
 */
class EscapeBackTerminal extends Terminal
{
    private static ?Terminal $previous = null;

    public function read(): string
    {
        $key = parent::read();

        // Arrow keys arrive as one chunk ("\e[A"); only a lone ESC byte is a real Esc press.
        return $key === Key::ESCAPE ? Key::CTRL_U : $key;
    }

    public static function enable(): bool
    {
        try {
            $property = new \ReflectionProperty(Prompt::class, 'terminal');
            self::$previous = $property->isInitialized() ? $property->getValue() : null;
            $property->setValue(new self);
            Prompt::revertUsing(fn () => null);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function disable(): void
    {
        try {
            Prompt::preventReverting();
            $property = new \ReflectionProperty(Prompt::class, 'terminal');
            $property->setValue(self::$previous ?? new Terminal);
        } catch (Throwable) {
            // nothing to restore
        }
        self::$previous = null;
    }
}
