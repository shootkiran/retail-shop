<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $categories = collect([
            'Beverages' => 'Hot and cold drinks ready for checkout.',
            'Snacks' => 'Quick bites and packaged treats for customers on the go.',
            'Household Supplies' => 'Daily essentials and cleaning materials for home care.',
        ])->map(fn (string $description, string $name) => ProductCategory::firstOrCreate(
            ['name' => $name],
            ['description' => $description],
        ));

        $vendors = collect([
            [
                'name' => 'Fresh Distributors',
                'email' => 'contact@freshdistributors.test',
                'phone' => '+1 (555) 111-2233',
                'contact_person' => 'Amelia Stone',
                'address' => '221 Market Street, Springfield',
            ],
            [
                'name' => 'Snack World Supply',
                'email' => 'hello@snackworld.test',
                'phone' => '+1 (555) 444-8899',
                'contact_person' => 'Jordan Lee',
                'address' => '78 Industrial Ave, Capital City',
            ],
            [
                'name' => 'Home Essentials Depot',
                'email' => 'sales@homeessentials.test',
                'phone' => '+1 (555) 707-9090',
                'contact_person' => 'Priya Desai',
                'address' => '19 Warehouse Road, Lakeside',
            ],
        ])->mapWithKeys(fn (array $vendor) => [
            $vendor['name'] => Vendor::updateOrCreate(
                ['email' => $vendor['email']],
                $vendor,
            ),
        ]);

        collect([
            [
                'name' => 'Sparkling Orange Soda',
                'sku' => 'BEV-001',
                'barcode' => '100000000001',
                'description' => 'Citrus soda made with natural flavors.',
                'unit_cost' => 0.55,
                'unit_price' => 0.99,
                'stock_quantity' => 180,
                'reorder_level' => 30,
                'category' => 'Beverages',
                'vendor' => 'Fresh Distributors',
            ],
            [
                'name' => 'Classic Black Coffee',
                'sku' => 'BEV-002',
                'barcode' => '100000000002',
                'description' => 'Ready-to-drink brewed coffee with no sugar.',
                'unit_cost' => 0.45,
                'unit_price' => 1.25,
                'stock_quantity' => 140,
                'reorder_level' => 25,
                'category' => 'Beverages',
                'vendor' => 'Fresh Distributors',
            ],
            [
                'name' => 'Sea Salt Potato Chips',
                'sku' => 'SNK-001',
                'barcode' => '100000000101',
                'description' => 'Kettle-cooked potato chips sprinkled with sea salt.',
                'unit_cost' => 0.65,
                'unit_price' => 1.49,
                'stock_quantity' => 200,
                'reorder_level' => 40,
                'category' => 'Snacks',
                'vendor' => 'Snack World Supply',
            ],
            [
                'name' => 'Trail Mix Energy Pack',
                'sku' => 'SNK-002',
                'barcode' => '100000000102',
                'description' => 'Nut and dried fruit mix for quick energy.',
                'unit_cost' => 0.85,
                'unit_price' => 1.89,
                'stock_quantity' => 160,
                'reorder_level' => 35,
                'category' => 'Snacks',
                'vendor' => 'Snack World Supply',
            ],
            [
                'name' => 'Multi-Surface Cleaner',
                'sku' => 'HHS-001',
                'barcode' => '100000000201',
                'description' => 'All-purpose cleaner suitable for kitchens and bathrooms.',
                'unit_cost' => 1.10,
                'unit_price' => 2.49,
                'stock_quantity' => 90,
                'reorder_level' => 15,
                'category' => 'Household Supplies',
                'vendor' => 'Home Essentials Depot',
            ],
            [
                'name' => 'Eco Laundry Detergent',
                'sku' => 'HHS-002',
                'barcode' => '100000000202',
                'description' => 'Plant-based detergent safe for sensitive skin.',
                'unit_cost' => 1.35,
                'unit_price' => 2.89,
                'stock_quantity' => 75,
                'reorder_level' => 20,
                'category' => 'Household Supplies',
                'vendor' => 'Home Essentials Depot',
            ],
        ])->each(function (array $product) use ($categories, $vendors): void {
            ProductItem::updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'product_category_id' => $categories[$product['category']]->id,
                    'vendor_id' => $vendors[$product['vendor']]->id,
                    'name' => $product['name'],
                    'barcode' => $product['barcode'],
                    'description' => $product['description'],
                    'unit_cost' => $product['unit_cost'],
                    'unit_price' => $product['unit_price'],
                    'stock_quantity' => $product['stock_quantity'],
                    'reorder_level' => $product['reorder_level'],
                    'is_active' => true,
                ],
            );
        });

        collect([
            [
                'name' => 'Cash',
                'type' => 'offline',
                'description' => 'Physical currency payments at the register.',
            ],
            [
                'name' => 'Card',
                'type' => 'card',
                'description' => 'Credit and debit card transactions via POS terminal.',
            ],
            [
                'name' => 'Online Transfer',
                'type' => 'online',
                'description' => 'Bank and mobile money transfers with confirmation.',
            ],
        ])->each(fn (array $method) => PaymentMethod::updateOrCreate(
            ['name' => $method['name']],
            [
                'type' => $method['type'],
                'description' => $method['description'],
                'is_active' => true,
            ],
        ));

        collect([
            [
                'name' => 'Olivia Carter',
                'email' => 'olivia.carter@example.com',
                'phone' => '+1 (555) 123-4567',
                'company' => 'Carter Consulting',
                'billing_address' => '415 Center Plaza, Springfield',
                'credit_limit' => 1000,
                'outstanding_balance' => 125,
            ],
            [
                'name' => 'Marcus Alvarez',
                'email' => 'marcus.alvarez@example.com',
                'phone' => '+1 (555) 765-4321',
                'company' => 'Alvarez Auto Repairs',
                'billing_address' => '982 Elm Street, Capital City',
                'credit_limit' => 500,
                'outstanding_balance' => 0,
            ],
            [
                'name' => 'Lena Okafor',
                'email' => 'lena.okafor@example.com',
                'phone' => '+1 (555) 246-8101',
                'company' => 'Okafor Boutique',
                'billing_address' => '67 Riverside Drive, Lakeside',
                'credit_limit' => 750,
                'outstanding_balance' => 210,
            ],
            [
                'name' => 'Community Center',
                'email' => 'accounts@communitycenter.example',
                'phone' => '+1 (555) 333-1212',
                'company' => 'Lakeside Community Center',
                'billing_address' => '9 Unity Lane, Lakeside',
                'credit_limit' => 1500,
                'outstanding_balance' => 450,
            ],
        ])->each(fn (array $customer) => Customer::updateOrCreate(
            ['email' => $customer['email']],
            $customer,
        ));
    }
}
