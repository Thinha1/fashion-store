<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MCP tokens are read-only unless issued with `mcp:token --write`; only
     * a write token sees the tools that change data (propose_* and
     * apply_proposal).
     */
    public function up(): void
    {
        Schema::table('mcp_tokens', function (Blueprint $table) {
            $table->boolean('can_write')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_tokens', function (Blueprint $table) {
            $table->dropColumn('can_write');
        });
    }
};
