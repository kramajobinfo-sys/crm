<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the second factor was switched on.
 *
 * Tokens carry a `twofa` claim decided at login (see RequireTwoFactor). A user who logs in
 * while 2FA is OFF gets `twofa=ok`, and that claim stayed valid after they turned 2FA on —
 * so every session that already existed kept full access without ever presenting a code,
 * for the remainder of its TTL. Enabling 2FA advertised protection that did not yet apply
 * to the sessions most likely to matter (other devices).
 *
 * RequireTwoFactor now compares the token's `iat` against this timestamp: a token minted
 * before the factor was confirmed is treated as unverified and must complete the challenge.
 * Cheaper and more predictable than blacklisting every outstanding token, and it stays
 * stateless per request — one column read that is already loaded with the user.
 *
 * Backfilled for users who already have 2FA on, so this does not force a re-verification
 * for anyone who set it up before this column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });

        // Existing 2FA users are treated as confirmed in the past, so their current tokens
        // (iat > this) stay valid — enabling this check must not log anybody out.
        DB::table('users')
            ->where('two_factor_enabled', true)
            ->update(['two_factor_confirmed_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_confirmed_at');
        });
    }
};
