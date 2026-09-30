<?php

namespace App\Filament\Concerns;

use App\Support\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * A screen the counter works on fully — except for deleting.
 *
 * Adding a driver at eleven at night, when one turns up and a delivery is
 * waiting, is the counter's job and nobody else is there to do it. Removing a
 * driver is not: his name is frozen onto every order he carried and onto the
 * fees he was paid, and deleting the row is a question about records rather
 * than about tonight.
 *
 * That is the line this trait draws, and it is the line the shop asked for:
 * the counter is not slowed down, and nothing disappears without the manager.
 */
trait ManagerDeletes
{
    public static function canDelete(Model $record): bool
    {
        return Staff::isManager();
    }

    public static function canDeleteAny(): bool
    {
        return Staff::isManager();
    }
}
