<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide appearance defaults (theme, accent, sidebar/top-bar colors) an admin can set for the
 * whole tenant. When `enforced` is true these override each user's personal choice; otherwise they are
 * just the default a user who hasn't personalized inherits. Shape:
 *   { theme, accent, chrome: { sidebar, topbar }, enforced }
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->json('appearance')->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('appearance');
        });
    }
};
