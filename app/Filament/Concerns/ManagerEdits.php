<?php

namespace App\Filament\Concerns;

use App\Support\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * A screen the counter reads all day but does not rewrite.
 *
 * The menu, the delivery zones, the drivers. The counter has to see these
 * constantly, and has to be able to switch a sold-out flavour off — but that
 * edit goes through the «متوفر» toggle on the table, which Filament does not
 * gate behind canEdit(), so it keeps working here. What is closed is the form:
 * prices, new rows, and deletions belong to the manager.
 *
 * A price typed at the counter is the quiet version of the same problem the
 * payment accounts have: nobody notices until the day's takings are short.
 */
trait ManagerEdits
{
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
