<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_products(): void
    {
        $user = User::factory()->create();

        Product::create([
            'name' => 'Laptop',
            'description' => 'Laptop untuk kuliah',
            'price' => 7500000,
            'stock' => 10,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Laptop');
    }

    public function test_authenticated_user_can_create_product(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/products', [
            'name' => 'Keyboard',
            'description' => 'Keyboard mekanik',
            'price' => 500000,
            'stock' => 15,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Keyboard');

        $this->assertDatabaseHas('products', [
            'name' => 'Keyboard',
            'stock' => 15,
        ]);
    }

    public function test_authenticated_user_can_update_product(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Keyboard',
            'description' => 'Keyboard biasa',
            'price' => 500000,
            'stock' => 15,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->putJson("/api/products/{$product->id}", [
            'name' => 'Keyboard Premium',
            'description' => 'Keyboard mekanik',
            'price' => 750000,
            'stock' => 8,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Keyboard Premium')
            ->assertJsonPath('data.stock', 8);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Keyboard Premium',
            'stock' => 8,
        ]);
    }

    public function test_authenticated_user_can_delete_product(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Keyboard',
            'description' => 'Keyboard mekanik',
            'price' => 500000,
            'stock' => 15,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Produk berhasil dihapus.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_unauthenticated_user_can_list_products(): void
    {
        $response = $this->get('/api/products');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Keyboard',
            'price' => 500000,
            'stock' => 15,
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
