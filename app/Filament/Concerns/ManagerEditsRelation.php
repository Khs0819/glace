<?php

namespace App\Filament\Concerns;

use App\Support\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * The ManagerEdits rule, for the tabs inside a product.
 *
 * A resource's canEdit() guards its own page and nothing else: the relation
 * managers on that page — the items, the sizes, the mixes, the price grid —
 * authorise themselves, and with no policies registered they answer yes to
 * everyone. So opening the product page to the counter, which is what the shop
 * asked for, would have opened the price grid with it.
 *
 * What stays live is the «متوفر» toggle on each table. Filament does not route
 * editable columns through any of this, which is exactly what the counter needs
 * and all of what it needs: an item that has run out, switched off where it is
 * listed.
 */
trait ManagerEditsRelation
{
    protected function canCreate(): bool
    {
        return Staff::isManager();
    }

    protected function canEdit(Model $record): bool
    {
        return Staff::isManager();
    }

    protected function canDelete(Model $record): bool
    {
        return Staff::isManager();
    }

    protected function canDeleteAny(): bool
    {
        return Staff::isManager();
    }

    protected function canAttach(): bool
    {
        return Staff::isManager();
    }

    protected function canDetach(Model $record): bool
    {
        return Staff::isManager();
    }

    protected function canDetachAny(): bool
    {
        return Staff::isManager();
    }

    protected function canAssociate(): bool
    {
        return Staff::isManager();
    }

    protected function canDissociate(Model $record): bool
    {
        return Staff::isManager();
    }

    protected function canReorder(): bool
    {
        return Staff::isManager();
    }

    protected function canReplicate(Model $record): bool
    {
        return Staff::isManager();
    }
}
