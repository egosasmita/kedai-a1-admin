<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::in(['available', 'unavailable'])],
        ]);

        $menus = Menu::query()
            ->with('category')
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('tag', 'like', "%{$search}%");
                });
            })
            ->when($validated['category'] ?? null, fn (Builder $query, int $category) => $query->where('category_id', $category))
            ->when(
                isset($validated['status']),
                fn (Builder $query) => $query->where('is_available', $validated['status'] === 'available'),
            )
            ->latest()
            ->paginate(6)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get();
        $editingMenu = $request->routeIs('admin.menus.edit')
            ? Menu::with('category')->findOrFail($request->route('menu'))
            : null;

        return view('admin.menus.index', [
            'menus' => $menus,
            'categories' => $categories,
            'editingMenu' => $editingMenu,
            'menuCount' => Menu::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedMenu($request);
        $imagePath = $this->storeImage($request->file('image'));

        try {
            DB::transaction(function () use ($validated, $imagePath): void {
                $menu = new Menu($this->menuAttributes($validated));
                $menu->slug = $this->uniqueSlug($validated['name']);
                $menu->image_url = $imagePath;
                $menu->save();
            });
        } catch (Throwable $exception) {
            $this->deleteStoredImage($imagePath);

            throw $exception;
        }

        return redirect()->route('admin.menus.index')->with('status', 'Menu berhasil ditambahkan.');
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $this->validatedMenu($request);
        $newImagePath = $this->storeImage($request->file('image'));
        $oldImagePath = $menu->image_url;

        try {
            DB::transaction(function () use ($menu, $validated, $newImagePath): void {
                $menu->fill($this->menuAttributes($validated));
                $menu->slug = $this->uniqueSlug($validated['name'], $menu->id);

                if ($newImagePath !== null) {
                    $menu->image_url = $newImagePath;
                }

                $menu->save();
            });
        } catch (Throwable $exception) {
            $this->deleteStoredImage($newImagePath);

            throw $exception;
        }

        if ($newImagePath !== null) {
            $this->deleteStoredImage($oldImagePath);
        }

        return redirect()->route('admin.menus.index')->with('status', 'Perubahan menu berhasil disimpan.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $imagePath = $menu->image_url;

        DB::transaction(fn () => $menu->delete());
        $this->deleteStoredImage($imagePath);

        return redirect()->route('admin.menus.index')->with('status', 'Menu berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMenu(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'tag' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'discount_price' => ['nullable', 'numeric', 'gt:0', 'lt:price', 'required_with:discount_end_time'],
            'discount_end_time' => ['nullable', 'date', 'after:now', 'required_with:discount_price'],
            'is_available' => ['required', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'discount_price.lt' => 'Harga diskon harus lebih rendah dari harga normal.',
            'discount_end_time.after' => 'Waktu berakhir diskon harus di masa mendatang.',
            'image.max' => 'Ukuran foto maksimal 4 MB.',
            'image.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
        ]);

        if (isset($validated['discount_price']) && ! isset($validated['discount_end_time'])) {
            throw ValidationException::withMessages([
                'discount_end_time' => 'Tanggal akhir wajib diisi jika menu diberi diskon.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function menuAttributes(array $validated): array
    {
        return [
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'price' => $validated['price'],
            'tag' => filled($validated['tag'] ?? null) ? Str::upper(trim($validated['tag'])) : null,
            'discount_price' => $validated['discount_price'] ?? null,
            'discount_end_time' => $validated['discount_end_time'] ?? null,
            'is_available' => $validated['is_available'],
            'is_featured' => $validated['is_featured'] ?? false,
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::limit(Str::slug($name) ?: 'menu', 160, '');
        $slug = $baseSlug;
        $suffix = 2;

        while (Menu::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = Str::limit($baseSlug, 150, '').'-'.$suffix++;
        }

        return $slug;
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = $image->store('menus', 'public');

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Foto menu gagal disimpan. Periksa izin folder penyimpanan.');
        }

        return $path;
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'menus/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
