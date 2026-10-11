<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_menu_with_discount_and_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Minuman']);

        $this->actingAs($admin)
            ->get(route('admin.menus.create'))
            ->assertOk()
            ->assertSee('Tambah menu');

        $this->post(route('admin.menus.store'), [
            'name' => 'Aren Latte',
            'category_id' => $category->id,
            'tag' => 'minuman',
            'description' => 'Espresso dengan susu dan gula aren.',
            'price' => '28000',
            'discount_price' => '24000',
            'discount_end_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'is_available' => '1',
            'is_featured' => '1',
            'image' => UploadedFile::fake()->createWithContent('aren-latte.png', $this->tinyPng()),
        ])->assertRedirect(route('admin.menus.index'));

        $menu = Menu::where('slug', 'aren-latte')->firstOrFail();
        $this->assertSame('MINUMAN', $menu->tag);
        $this->assertSame('24000.00', $menu->discount_price);
        $this->assertTrue($menu->is_available);
        $this->assertTrue($menu->is_featured);
        Storage::disk('public')->assertExists($menu->image_url);

        $oldImagePath = $menu->image_url;
        $this->get(route('admin.menus.index'))
            ->assertOk()
            ->assertSee('Daftar menu')
            ->assertSee('Aren Latte')
            ->assertSee('Rp 24.000');

        $this->put(route('admin.menus.update', $menu), [
            'name' => 'Aren Latte Spesial',
            'category_id' => $category->id,
            'tag' => 'signature',
            'description' => 'Resep spesial.',
            'price' => '30000',
            'discount_price' => '',
            'discount_end_time' => '',
            'is_available' => '0',
            'image' => UploadedFile::fake()->createWithContent('aren-latte-baru.png', $this->tinyPng()),
        ])->assertRedirect(route('admin.menus.index'));

        $menu->refresh();
        $this->assertSame('aren-latte-spesial', $menu->slug);
        $this->assertNull($menu->discount_price);
        $this->assertFalse($menu->is_available);
        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($menu->image_url);

        $newImagePath = $menu->image_url;
        $this->delete(route('admin.menus.destroy', $menu))
            ->assertRedirect(route('admin.menus.index'));

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        Storage::disk('public')->assertMissing($newImagePath);
    }

    public function test_discount_must_be_less_than_regular_price_and_have_an_end_time(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Makanan']);

        $this->actingAs($admin)
            ->from(route('admin.menus.create'))
            ->post(route('admin.menus.store'), [
                'name' => 'Mie Goreng',
                'category_id' => $category->id,
                'price' => '30000',
                'discount_price' => '30000',
                'is_available' => '1',
            ])
            ->assertRedirect(route('admin.menus.create'))
            ->assertSessionHasErrors(['discount_price', 'discount_end_time']);

        $this->assertDatabaseCount('menus', 0);
    }

    public function test_only_admin_can_access_menu_management(): void
    {
        $employee = User::factory()->create(['is_admin' => false]);

        $this->actingAs($employee)
            ->get(route('admin.menus.index'))
            ->assertForbidden();
    }

    private function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC');
    }
}
