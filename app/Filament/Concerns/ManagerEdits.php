<?php

namespace App\Filament\Concerns;

use App\Support\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * A screen the counter reads all day but does not rewrite.
 *
 * Two kinds of screen end up here.
 *
 * The menu and the delivery zones, because the counter has to see them
 * constantly and has to be able to switch a sold-out flavour off — and that
 * edit goes through the «متوفر» toggle on the table, which Filament does not
 * gate behind canEdit(), so it keeps working. What is closed is the form: a
 * price typed at the counter is the quiet version of the same problem the
 * payment accounts have, and nobody notices until the takings are short.
 *
 * And the coupons and the website — the slides, the FAQs, the contact
 * messages. Reading these answers a customer at the till ("is that code still
 * good?"), so hiding them only sends the counter to find a manager. Publishing
 * them is a different act, and it is the manager's.
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
