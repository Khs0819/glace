<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Who is at the keyboard, and what that account is allowed to be.
 *
 * Two kinds of dashboard account, deliberately:
 *
 *   **مدير** — everything. What the shop charges, where its money goes, who
 *   can sign in, and what the website says.
 *
 *   **محاسب / كاشير** — the day. Orders, the till, refunds, top-ups, drivers,
 *   and switching a sold-out item off.
 *
 * The split is not about trusting anyone. It is that the counter account is
 * signed in on a screen in a public room for a whole shift, and a mis-tap on it
 * must not be able to rewrite the bank account customers transfer to, hand out
 * wallet credit, or invent a discount code.
 *
 * Every screen asks this class rather than reading the role itself, so there is
 * one answer to change if a third kind of account is ever added.
 */
class Staff
{
    public static function current(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /** The manager's account: no screen is closed to it. */
    public static function isManager(): bool
    {
        return self::current()?->isManager() ?? false;
    }

    /** The counter's account: the day's work, and nothing that outlives it. */
    public static function isCounter(): bool
    {
        return self::current()?->isAccountant() ?? false;
    }
}
