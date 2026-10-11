<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        [$menuIdType, $menuIdUnsigned] = $this->menuIdColumnDefinition();

        if (! Schema::hasTable('menu_ingredients')) {
            Schema::create('menu_ingredients', function (Blueprint $table) use ($menuIdType, $menuIdUnsigned) {
                $table->id();

                if ($menuIdType === 'foreignId') {
                    $table->foreignId('menu_id');
                } else {
                    $table->{$menuIdType}('menu_id', false, $menuIdUnsigned);
                }

                $table->foreignId('stock_item_id');
                $table->decimal('quantity_per_serving', 12, 3);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } elseif ($menuIdType !== 'foreignId') {
            Schema::table('menu_ingredients', function (Blueprint $table) use ($menuIdType, $menuIdUnsigned) {
                $table->{$menuIdType}('menu_id', false, $menuIdUnsigned)->change();
            });
        }

        $foreignKeys = Schema::getForeignKeys('menu_ingredients');
        $hasMenuForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['menu_id'],
        );
        $hasStockForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['stock_item_id'],
        );
        $indexes = Schema::getIndexes('menu_ingredients');
        $hasMenuStockUnique = collect($indexes)->contains(
            fn (array $index): bool => $index['columns'] === ['menu_id', 'stock_item_id'] && $index['unique'],
        );

        Schema::table('menu_ingredients', function (Blueprint $table) use ($hasMenuForeignKey, $hasStockForeignKey, $hasMenuStockUnique) {
            if (! $hasMenuForeignKey) {
                $table->foreign('menu_id', 'menu_ingredients_menu_id_foreign')
                    ->references('id')
                    ->on('menus')
                    ->cascadeOnDelete();
            }

            if (! $hasStockForeignKey) {
                $table->foreign('stock_item_id', 'menu_ingredients_stock_item_id_foreign')
                    ->references('id')
                    ->on('stock_items')
                    ->restrictOnDelete();
            }

            if (! $hasMenuStockUnique) {
                $table->unique(['menu_id', 'stock_item_id'], 'menu_ingredients_menu_stock_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_ingredients');
    }

    /**
     * @return array{string, bool}
     */
    private function menuIdColumnDefinition(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return ['foreignId', true];
        }

        $menuId = collect(Schema::getColumns('menus'))->firstWhere('name', 'id');

        if ($menuId === null) {
            throw new RuntimeException('The menus table does not have an id column.');
        }

        $type = strtolower($menuId['type_name']);
        $method = match ($type) {
            'int', 'integer' => 'integer',
            'bigint' => 'bigInteger',
            default => throw new RuntimeException("Unsupported menus.id column type: {$type}."),
        };
        $unsigned = str_contains(strtolower($menuId['type']), 'unsigned');

        return [$method, $unsigned];
    }
};
