<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_exposes_prometheus_text_format(): void
    {
        $response = $this->get('/metrics');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $response->assertSee('app_database_up 1', false);
        $response->assertSee('# TYPE app_products_total gauge', false);
    }

    public function test_metrics_endpoint_counts_products(): void
    {
        Product::query()->insert([
            ['name' => 'Kopi', 'price' => 15000, 'stock' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Teh', 'price' => 8000, 'stock' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/metrics')->assertSee('app_products_total 2', false);
    }
}
