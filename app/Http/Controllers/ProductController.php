<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function categoryHints()
    {
        return [
            'Cosmetics' => 'Makeup, beauty products, and cosmetic accessories.',
            'Hair Care' => 'Shampoo, conditioner, hair oil, hair cream, and treatments.',
            'Skin Care' => 'Face wash, moisturizer, sunscreen, lotion, and skin creams.',
            'Oral Care' => 'Toothbrushes, toothpaste, mouthwash, and dental floss.',
            'Personal Care' => 'Deodorant, body spray, body wash, and personal hygiene items.',
            'Baby Care' => 'Baby shampoo, baby lotion, baby soap, diapers, and baby items.',
            'Hygiene Products' => 'Soap, sanitary products, hand sanitizer, and tissues.',
            'Medical Supplies' => 'Gloves, masks, bandages, gauze, and non-drug supplies.',
            'Other Products' => 'A retail product that does not fit another category.',
        ];
    }
    public function index(Request $request)
    {
        $query = Product::with('category')->orderBy('name');
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->input('status') === 'inactive') $query->where('active', false);
        elseif ($request->input('status') === 'active') $query->where('active', true);

        return view('products.index', [
            'products' => $query->paginate(15)->withQueryString(),
            'categories' => ProductCategory::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $categories = ProductCategory::where('active', true)->orderBy('name')->get();
        if ($categories->isEmpty()) return redirect()->route('product-categories.create')->with('error', 'Add a product category first.');
        return view('products.create', ['categories' => $categories, 'categoryHints' => $this->categoryHints()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'category_id' => 'required|exists:product_categories,id',
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock_level' => 'required|numeric|min:0',
        ]);
        $validated['minimum_stock'] = $validated['minimum_stock_level'];
        $validated['sku'] = filled($validated['sku'] ?? null) ? trim($validated['sku']) : null;
        unset($validated['minimum_stock_level']);
        Product::create($validated + ['active' => true]);
        return redirect()->route('products.index')->with('success', 'Product added. Record a purchase to add stock.');
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::where('active', true)->orderBy('name')->get();
        return view('products.edit', ['product' => $product, 'categories' => $categories, 'categoryHints' => $this->categoryHints()]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:product_categories,id',
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock_level' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);
        $validated['minimum_stock'] = $validated['minimum_stock_level'];
        $validated['sku'] = filled($validated['sku'] ?? null) ? trim($validated['sku']) : null;
        unset($validated['minimum_stock_level']);
        $product->update($validated + ['active' => $request->boolean('active')]);
        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        if ($product->purchaseBatches()->exists()) return back()->with('error', 'Cannot delete a product with stock history. Deactivate it instead.');
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }
}
