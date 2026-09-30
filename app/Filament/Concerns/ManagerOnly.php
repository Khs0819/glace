<?php

namespace App\Filament\Concerns;

use App\Support\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * A screen the counter account does not open at all.
 *
 * Filament reads canViewAny() for the sidebar as well as for the route, so a
 * resource using this disappears from the navigation rather than sitting there
 * and refusing when it is clicked — the counter never sees a door it cannot
 * open, and never has to ask why.
 *
 * A resource that defines one of these methods itself keeps its own: PHP lets
 * the class win over the trait, which is how UserResource keeps its rule that
 * nobody deletes their own account.
 */
trait ManagerOnly
{
    public static function canViewAny(): bool
    {
        return Staff::isManager();
    }

    public static function canView(Model $record): bool
    {
        return Staff::isManager();
    }

    public static function canCreate(): bool
    {
        return Staff::isManager();
    }

    public static function canEdit(Model $record): bool
    {
        return Staff::isManager();
    }

    public static function canDelete(Model $record): bool
    {
        return Staff::isManager();
    }

    public static function canDeleteAny(): bool
    {
        return Staff::isManager();
    }
}
