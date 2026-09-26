// database/migrations/2024_01_01_000002_create_contract_wbs_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_wbs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wbs_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 20, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'wbs_item_id']);
            $table->index(['wbs_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_wbs');
    }
};
