<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Size charts shown on the product page ("Bảng size") and used to
     * suggest a size from height and weight. A chart applies to a brand
     * and/or a category (and every category under it); a product may also
     * point at one chart directly (products.size_chart_id), which wins.
     * See App\Services\Catalog\SizeChartResolver.
     */
    public function up(): void
    {
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // Extra measurement column labels, e.g. ["Vòng ngực (cm)", "Dài áo (cm)"].
            $table->json('columns');
            // One row per size: {size, height_min, height_max, weight_min, weight_max, values: [...]}.
            $table->json('rows');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('size_chart_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('size_chart_id');
        });
        Schema::dropIfExists('size_charts');
    }
};
