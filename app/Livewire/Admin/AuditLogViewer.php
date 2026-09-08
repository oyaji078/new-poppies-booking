<?php

namespace App\Livewire\Admin;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Audit Log (§33). Read-only by design: the audit trail is evidence, so
 * nothing here edits or deletes a row.
 */
class AuditLogViewer extends Component
{
    use WithPagination;

    public string $search = '';

    public string $action = '';

    public string $from = '';

    public string $until = '';

    /** Row whose before/after snapshot is expanded. */
    public ?int $expandedId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingUntil(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'action', 'from', 'until', 'expandedId']);
        $this->resetPage();
    }

    public function toggleDetail(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->until !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->until))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($inner) use ($term) {
                    $inner->whereLike('entity_id', $term, caseSensitive: false)
                        ->orWhereLike('entity_type', $term, caseSensitive: false)
                        ->orWhereLike('ip_address', $term, caseSensitive: false)
                        // The closure needs its own group: Laravel does not wrap
                        // whereHas constraints, so a bare orWhere here escapes
                        // the correlation to users and matches every log row.
                        ->orWhereHas('user', fn ($u) => $u->where(
                            fn ($name) => $name->whereLike('name', $term, caseSensitive: false)
                                ->orWhereLike('email', $term, caseSensitive: false)
                        ));
                });
            })
            ->latest('id')
            ->paginate(25);

        // Only offer actions that actually occur, so the filter never presents
        // a choice that returns nothing.
        $usedActions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => AuditAction::tryFrom($value)?->label() ?? $value,
            ]);

        return view('livewire.admin.audit-log-viewer', [
            'logs' => $logs,
            'usedActions' => $usedActions,
            'totalCount' => AuditLog::count(),
        ])->layout('components.layouts.admin', [
            'title' => 'Audit Log',
            'heading' => 'Audit Log',
            'breadcrumb' => 'Admin / Audit Log',
        ]);
    }
}
