<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * Promote an existing account to Super Admin.
 *
 * Deliberately CLI-only: the role that may switch the payment gateway between
 * sandbox and production cannot be granted from inside the web UI, so a
 * compromised admin session can never escalate itself.
 *
 *   php artisan user:make-superadmin admin@example.test
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'user:make-superadmin
        {email : Email akun yang akan dijadikan Super Admin}
        {--demote : Turunkan kembali menjadi Administrator biasa}';

    protected $description = 'Promote a user to Super Admin (the only role allowed to switch DOKU modes)';

    public function handle(AuditLogger $audit): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->components->error("Akun \"{$email}\" tidak ditemukan.");

            $this->line('  Akun staf yang ada:');
            User::query()
                ->whereIn('role', [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value, UserRole::MANAGER->value, UserRole::RECEPTIONIST->value])
                ->orderBy('email')
                ->get()
                ->each(fn (User $u) => $this->line("  • {$u->email} — {$u->role->label()}"));

            return self::FAILURE;
        }

        $target = $this->option('demote') ? UserRole::ADMIN : UserRole::SUPER_ADMIN;

        if ($user->role === $target) {
            $this->components->info("{$user->email} sudah berperan {$target->label()}.");

            return self::SUCCESS;
        }

        $previous = $user->role;
        $user->role = $target;
        $user->save();

        $audit->log(
            AuditAction::ADMIN_OVERRIDE->value,
            $user,
            ['role' => $previous?->value],
            ['role' => $target->value, 'reason' => 'Diubah lewat perintah user:make-superadmin'],
            $user,
        );

        $this->components->info("{$user->email}: {$previous?->label()} → {$target->label()}");

        if ($target === UserRole::SUPER_ADMIN) {
            $this->line('  Akun ini kini dapat membuka /admin/doku dan mengalihkan mode pembayaran.');
        }

        return self::SUCCESS;
    }
}
