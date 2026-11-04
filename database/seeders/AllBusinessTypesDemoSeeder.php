<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Development-only: makes sure EVERY business type has one demo client with
 * a business and a little realistic (Pakistani) data — products/services,
 * suppliers, customers, purchases, ~a month of sales and expenses — so each
 * type can be logged into and tested straight away. Then sets every client
 * user's password to the shared demo password.
 *
 *   php artisan db:seed --class=AllBusinessTypesDemoSeeder
 *
 * Businesses that already have customers (e.g. the dental clinic and
 * installment store, which have their own richer seeders) are left as
 * they are. Safe to re-run. NEVER run against production — it resets
 * every client user's password.
 */
class AllBusinessTypesDemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'demo0987';

    public function run(PurchaseService $purchases, SaleService $sales): void
    {
        mt_srand(2026);

        foreach ($this->blueprints() as $slug => $bp) {
            $type = BusinessType::query()->where('slug', $slug)->first();
            if (! $type) {
                continue;
            }

            DB::transaction(function () use ($type, $bp, $purchases, $sales) {
                [$business, $owner, $branch] = $this->businessFor($type, $bp);

                if (Customer::query()->where('business_id', $business->id)->exists()) {
                    $this->command?->line("  {$type->name}: \"{$business->name}\" already has data — skipped.");

                    return;
                }

                $cashier = $this->cashier($business, $branch, $bp);
                $products = $this->catalog($business, $bp);
                $suppliers = $this->suppliers($business, $bp['suppliers']);
                $this->stockUp($purchases, $owner, $branch, $suppliers, $products);
                $customers = $this->customers($business, $bp['customers']);
                $this->sales($sales, [$owner, $cashier], $branch, $customers, $products);
                $this->expenses($business, $owner, $branch, $bp['expenses']);

                $this->command?->info("  {$type->name}: \"{$business->name}\" — demo data added.");
            });
        }

        $count = User::query()->where('is_super_admin', false)->update(['password' => Hash::make(self::DEMO_PASSWORD)]);
        $this->command?->info("Password of all {$count} client users set to ".self::DEMO_PASSWORD);
    }

    /**
     * Reuses the type's existing business (oldest) or creates a new demo
     * client + business + owner for it.
     *
     * @return array{0: Business, 1: User, 2: Branch}
     */
    private function businessFor(BusinessType $type, array $bp): array
    {
        $business = Business::query()->where('business_type_id', $type->id)->oldest('id')->first();

        if (! $business) {
            $client = Client::query()->firstOrCreate(
                ['email' => $bp['email']],
                ['name' => $bp['client'], 'status' => 'active'],
            );
            $client->forceFill(['phone' => $bp['phone'], 'notes' => 'Demo client — '.$type->name.'. '.$bp['address']])->save();

            $business = new Business([
                'business_type_id' => $type->id,
                'name' => $bp['business'],
                'slug' => Str::slug($bp['business']),
                'phone' => $bp['phone'],
                'email' => $bp['email'],
                'address' => $bp['address'],
                'currency' => 'PKR',
                'timezone' => 'Asia/Karachi',
                'status' => 'active',
            ]);
            $business->client_id = $client->id;
            $business->save();
        }

        $client = $business->client;

        // Enterprise has every feature, so all modules of every type work.
        if (! $client->currentSubscription) {
            $package = Package::query()->where('slug', 'enterprise')->first() ?? Package::query()->first();
            Subscription::query()->create([
                'client_id' => $client->id,
                'package_id' => $package->id,
                'status' => 'active',
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->addYear(),
            ]);
        }

        $branch = Branch::query()->where('business_id', $business->id)->orderByDesc('is_main')->oldest('id')->first()
            ?? Branch::query()->create([
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'address' => $business->address,
                'phone' => $business->phone,
                'is_main' => true,
                'status' => 'active',
            ]);

        $ownerRole = Role::query()->where('slug', 'client-owner')->value('id');
        $owner = User::query()->where('client_id', $client->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'client-owner'))
            ->first();

        if (! $owner) {
            $owner = User::query()->firstOrNew(['email' => $bp['email']]);
            $owner->forceFill([
                'name' => $bp['owner'],
                'password' => Hash::make(self::DEMO_PASSWORD),
                'status' => 'active',
                'client_id' => $client->id,
                'business_id' => $business->id,
                'email_verified_at' => now(),
            ])->save();
            $owner->roles()->syncWithoutDetaching(array_filter([$ownerRole]));
        }

        return [$business, $owner, $branch];
    }

    private function cashier(Business $business, Branch $branch, array $bp): User
    {
        $email = 'cashier.'.Str::before($bp['email'], '@').'@demo.test';
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $bp['cashier'],
            'password' => Hash::make(self::DEMO_PASSWORD),
            'status' => 'active',
            'client_id' => $business->client_id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'email_verified_at' => now(),
        ])->save();

        $roleId = Role::query()->where('slug', 'cashier')->value('id');
        if ($roleId) {
            $user->roles()->syncWithoutDetaching([$roleId]);
        }

        return $user;
    }

    /** @return list<Product> */
    private function catalog(Business $business, array $bp): array
    {
        $products = [];
        foreach ($bp['items'] as $i => [$name, $category, $price, $cost, $isService]) {
            $cat = Category::query()->firstOrCreate(
                ['business_id' => $business->id, 'slug' => Str::slug($category)],
                ['name' => $category, 'status' => 'active'],
            );

            $sku = strtoupper($bp['sku']).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $product = Product::query()->firstOrNew(['business_id' => $business->id, 'sku' => $sku]);
            $product->fill([
                'name' => $name,
                'sku' => $sku,
                'barcode' => $isService ? null : '8964'.str_pad((string) ($business->id * 1000 + $i + 1), 9, '0', STR_PAD_LEFT),
                'type' => $isService ? 'service' : 'product',
                'sale_price' => $price,
                'purchase_price' => $cost,
                'tax_rate' => 0,
                'unit' => $isService ? 'service' : ($bp['unit'] ?? 'pcs'),
                'minimum_stock' => $isService ? 0 : 5,
                'status' => 'active',
            ]);
            $product->business_id = $business->id;
            $product->category_id = $cat->id;
            $product->save();
            $products[] = $product;
        }

        return $products;
    }

    /** @return list<Supplier> */
    private function suppliers(Business $business, array $rows): array
    {
        return array_map(function (array $row) use ($business) {
            [$name, $phone, $address] = $row;
            $s = new Supplier(['name' => $name, 'phone' => $phone, 'address' => $address, 'status' => 'active']);
            $s->business_id = $business->id;
            $s->save();

            return $s;
        }, $rows);
    }

    /** One opening purchase (and a recent re-order) so physical items have stock. */
    private function stockUp(PurchaseService $service, User $owner, Branch $branch, array $suppliers, array $products): void
    {
        $physical = array_values(array_filter($products, fn (Product $p) => $p->type === 'product'));
        if (! $physical || ! $suppliers) {
            return;
        }

        $line = fn (Product $p, int $qty) => ['product_id' => $p->id, 'quantity' => $qty, 'purchase_price' => (float) $p->purchase_price];

        $service->create($branch, $suppliers[0], $owner, array_map(fn ($p) => $line($p, mt_rand(25, 60)), $physical),
            'OPN-'.mt_rand(1000, 9999), now('Asia/Karachi')->subDays(40)->toDateString(), 'Opening stock');

        $reorder = array_slice($physical, 0, min(3, count($physical)));
        $service->create($branch, $suppliers[count($suppliers) - 1], $owner, array_map(fn ($p) => $line($p, mt_rand(10, 20)), $reorder),
            'RO-'.mt_rand(1000, 9999), now('Asia/Karachi')->subDays(7)->toDateString(), 'Re-order');
    }

    /** @return list<Customer> */
    private function customers(Business $business, array $rows): array
    {
        return array_map(function (array $row) use ($business) {
            [$name, $phone, $address] = $row;
            $c = new Customer(['name' => $name, 'phone' => $phone, 'address' => $address, 'status' => 'active']);
            $c->business_id = $business->id;
            $c->save();

            return $c;
        }, $rows);
    }

    /** About a month of sales: 1–3 lines each, cash/card, some walk-ins. */
    private function sales(SaleService $service, array $sellers, Branch $branch, array $customers, array $products): void
    {
        $cash = PaymentMethod::query()->where('slug', 'cash')->value('id');
        $card = PaymentMethod::query()->where('slug', 'card')->value('id') ?? $cash;

        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo -= mt_rand(1, 3)) {
            $picked = [];
            foreach ((array) array_rand($products, min(count($products), mt_rand(1, 3))) as $idx) {
                $picked[] = $products[$idx];
            }

            $items = [];
            $total = 0.0;
            foreach ($picked as $p) {
                $qty = $p->type === 'service' ? 1 : mt_rand(1, 3);
                $items[] = ['product_id' => $p->id, 'quantity' => $qty];
                $total += (float) $p->sale_price * $qty;
            }

            $payments = [mt_rand(1, 100) <= 70
                ? ['payment_method_id' => $cash, 'amount' => $total]
                : ['payment_method_id' => $card, 'amount' => $total, 'reference' => 'POS-'.mt_rand(1000, 9999)]];

            $at = Carbon::parse(now('Asia/Karachi')->subDays($daysAgo)->toDateString().sprintf(' %02d:%02d:00', mt_rand(10, 21), mt_rand(0, 59)), 'Asia/Karachi');
            if ($at->isFuture()) {
                $at = now('Asia/Karachi')->subMinutes(mt_rand(10, 90));
            }

            try {
                $service->create(
                    $branch,
                    $sellers[mt_rand(0, count($sellers) - 1)],
                    mt_rand(1, 100) <= 60 ? $customers[mt_rand(0, count($customers) - 1)] : null,
                    $items,
                    $payments,
                    (string) Str::uuid(),
                    ['items' => $items, 'payments' => $payments],
                    null,
                    $at->clone()->utc(),
                );
            } catch (\Throwable $e) {
                // e.g. a line ran out of stock — skip that sale, keep seeding.
                $this->command?->warn('    sale skipped: '.$e->getMessage());
            }
        }
    }

    private function expenses(Business $business, User $owner, Branch $branch, array $rows): void
    {
        foreach ($rows as $i => [$title, $category, $amount]) {
            $e = new Expense([
                'title' => $title,
                'category' => $category,
                'amount' => $amount,
                'expense_date' => now('Asia/Karachi')->subDays(3 + $i * 6)->toDateString(),
            ]);
            $e->business_id = $business->id;
            $e->branch_id = $branch->id;
            $e->user_id = $owner->id;
            $e->save();
        }
    }

    /**
     * Per business type: who the demo client is, and what they sell.
     * items: [name, category, sale price, cost, is service]
     */
    private function blueprints(): array
    {
        $people = [
            ['Muhammad Ali', '0300-1234567', 'Model Town, Lahore'],
            ['Ayesha Khan', '0321-7654321', 'Gulshan-e-Iqbal, Karachi'],
            ['Bilal Ahmed', '0333-9876543', 'F-10, Islamabad'],
            ['Fatima Noor', '0345-1122334', 'Saddar, Rawalpindi'],
            ['Usman Tariq', '0312-5566778', 'Peoples Colony, Faisalabad'],
            ['Sana Javed', '0301-9988776', 'Cantt, Multan'],
        ];

        $common = fn (string $rent, int $rentAmount) => [
            [$rent, 'Rent', $rentAmount],
            ['Staff salaries', 'Salaries', (int) round($rentAmount * 1.6, -3)],
            ['Electricity bill', 'Utilities', (int) round($rentAmount * 0.3, -2)],
            ['Internet & mobile bills', 'Utilities', 4500],
        ];

        return [
            'grocery' => [
                'client' => 'Al-Madina Mart', 'business' => 'Al-Madina Mart', 'owner' => 'Haji Rafiq', 'cashier' => 'Kamran (Cashier)',
                'email' => 'grocery@demo.test', 'phone' => '042-35761234', 'address' => 'Main Boulevard, Johar Town, Lahore', 'sku' => 'GRC',
                'items' => [
                    ['Tapal Danedar Tea 950g', 'Tea & Beverages', 1650, 1480, false],
                    ['Nestle Milkpak 1L', 'Dairy', 290, 262, false],
                    ['Dalda Cooking Oil 5L', 'Cooking Oil & Ghee', 2950, 2700, false],
                    ['Shan Biryani Masala', 'Spices', 140, 118, false],
                    ['Basmati Rice 5kg (Guard)', 'Rice & Flour', 2100, 1850, false],
                    ['Sunsilk Shampoo 360ml', 'Personal Care', 720, 610, false],
                    ['Surf Excel 1kg', 'Household', 650, 560, false],
                    ['Peek Freans Sooper Biscuits', 'Snacks', 60, 50, false],
                ],
                'suppliers' => [['Metro Cash & Carry', '042-111-638-762', 'Thokar Niaz Baig, Lahore'], ['Hassan Traders (FMCG Distributor)', '0300-4567890', 'Akbari Mandi, Lahore']],
                'customers' => array_slice($people, 0, 5),
                'expenses' => $common('Shop rent', 120000),
            ],
            'restaurant' => [
                'client' => 'Karachi Grill & BBQ', 'business' => 'Karachi Grill & BBQ', 'owner' => 'Shahid Mehmood', 'cashier' => 'Faisal (Counter)',
                'email' => 'restaurant@demo.test', 'phone' => '021-34567890', 'address' => 'Burns Road, Saddar, Karachi', 'sku' => 'RST',
                'items' => [
                    ['Chicken Tikka (Full)', 'BBQ', 950, 520, true],
                    ['Seekh Kabab (4 pcs)', 'BBQ', 780, 410, true],
                    ['Chicken Karahi (Half)', 'Karahi', 1450, 820, true],
                    ['Mutton Karahi (Half)', 'Karahi', 2400, 1500, true],
                    ['Chicken Biryani', 'Rice', 450, 230, true],
                    ['Garlic Naan', 'Breads', 120, 40, true],
                    ['Mint Margarita', 'Drinks', 350, 120, true],
                    ['Coca-Cola 1.5L', 'Drinks', 260, 210, false],
                    ['Mineral Water 1.5L', 'Drinks', 120, 85, false],
                ],
                'suppliers' => [['Karachi Meat Supplier', '0300-2223344', 'Empress Market, Karachi'], ['Coca-Cola Beverages Distributor', '021-111-262-262', 'SITE Area, Karachi']],
                'customers' => array_slice($people, 1, 5),
                'expenses' => array_merge($common('Restaurant rent', 250000), [['Gas bill (kitchen)', 'Utilities', 38000], ['Foodpanda commission', 'Marketing', 22000]]),
            ],
            'pharmacy' => [
                'client' => 'Shifa Pharmacy', 'business' => 'Shifa Pharmacy', 'owner' => 'Dr. Imran Qureshi', 'cashier' => 'Waqas (Dispenser)',
                'email' => 'pharmacy@demo.test', 'phone' => '051-4412233', 'address' => 'Jinnah Super Market, F-7, Islamabad', 'sku' => 'PHR',
                'items' => [
                    ['Panadol 500mg (Strip of 10)', 'Pain Relief', 45, 36, false],
                    ['Brufen 400mg (Strip of 10)', 'Pain Relief', 70, 56, false],
                    ['Augmentin 625mg (6 tabs)', 'Antibiotics', 520, 445, false],
                    ['Risek 20mg (14 caps)', 'Gastro', 410, 350, false],
                    ['Calpol Syrup 90ml', 'Syrups', 160, 128, false],
                    ['ORS Sachet', 'Hydration', 30, 22, false],
                    ['Surgical Face Mask (Box of 50)', 'Medical Supplies', 450, 330, false],
                    ['Blood Pressure Check', 'Services', 100, 0, true],
                ],
                'suppliers' => [['Muller & Phipps Pakistan', '051-2287766', 'I-9 Industrial Area, Islamabad'], ['Rawal Medical Distributors', '0333-5123456', 'Raja Bazar, Rawalpindi']],
                'customers' => array_slice($people, 2, 4),
                'expenses' => $common('Pharmacy rent', 140000),
                'unit' => 'pack',
            ],
            'electronics' => [
                'client' => 'TechZone Mobiles', 'business' => 'TechZone Mobiles', 'owner' => 'Adnan Siddiqui', 'cashier' => 'Hamza (Sales)',
                'email' => 'electronics@demo.test', 'phone' => '042-35717788', 'address' => 'Hafeez Centre, Gulberg III, Lahore', 'sku' => 'ELC',
                'items' => [
                    ['Samsung Galaxy A35 (8/256)', 'Mobile Phones', 115000, 104000, false],
                    ['Infinix Note 40 (8/256)', 'Mobile Phones', 58000, 52500, false],
                    ['Xiaomi Redmi 13 (8/128)', 'Mobile Phones', 46000, 41500, false],
                    ['Anker 20W Fast Charger', 'Accessories', 3200, 2400, false],
                    ['Audionic Bluetooth Earbuds', 'Accessories', 4500, 3300, false],
                    ['Tempered Glass Protector', 'Accessories', 500, 150, false],
                    ['Mobile Repair / Screen Fitting', 'Services', 2500, 0, true],
                ],
                'suppliers' => [['Mobile Wholesale Hub (Hall Road)', '0321-4445566', 'Hall Road, Lahore'], ['Accessories Importer Karachi', '0300-2009988', 'Saddar, Karachi']],
                'customers' => array_slice($people, 0, 6),
                'expenses' => $common('Shop rent', 180000),
            ],
            'clothing' => [
                'client' => 'Libas Boutique', 'business' => 'Libas Boutique', 'owner' => 'Nadia Hussain', 'cashier' => 'Rabia (Sales)',
                'email' => 'clothing@demo.test', 'phone' => '042-35870011', 'address' => 'Liberty Market, Gulberg, Lahore', 'sku' => 'CLT',
                'items' => [
                    ['Lawn 3-Piece Unstitched (Printed)', 'Lawn Collection', 6500, 4200, false],
                    ['Embroidered Chiffon Suit', 'Formal Wear', 14500, 9800, false],
                    ["Men's Kurta Shalwar (Wash & Wear)", "Men's Wear", 4800, 3100, false],
                    ['Ladies Khussa', 'Footwear', 2800, 1700, false],
                    ['Printed Silk Dupatta', 'Accessories', 2200, 1300, false],
                    ['Kids Frock', 'Kids', 2500, 1500, false],
                    ['Stitching Service', 'Services', 1500, 600, true],
                ],
                'suppliers' => [['Faisalabad Textile Mills Outlet', '041-8765432', 'Clock Tower, Faisalabad'], ['Anarkali Footwear Wholesale', '0322-4001122', 'Anarkali Bazar, Lahore']],
                'customers' => array_slice($people, 1, 5),
                'expenses' => array_merge($common('Boutique rent', 200000), [['Instagram ads', 'Marketing', 15000]]),
            ],
            'salon' => [
                'client' => 'Glow Beauty Salon', 'business' => 'Glow Beauty Salon', 'owner' => 'Hina Malik', 'cashier' => 'Mehwish (Front Desk)',
                'email' => 'salon@demo.test', 'phone' => '051-2654321', 'address' => 'Bahria Town Phase 4, Rawalpindi', 'sku' => 'SLN',
                'items' => [
                    ['Haircut & Blow Dry', 'Hair', 2500, 0, true],
                    ['Hair Color (Global)', 'Hair', 8000, 2500, true],
                    ['Gold Facial', 'Skin', 4500, 1200, true],
                    ['Manicure & Pedicure', 'Nails', 3500, 800, true],
                    ['Bridal Makeup', 'Makeup', 45000, 8000, true],
                    ['Threading (Eyebrows)', 'Skin', 300, 0, true],
                    ["L'Oreal Shampoo 250ml (Retail)", 'Retail Products', 1800, 1350, false],
                ],
                'suppliers' => [['Beauty Products Wholesale', '0300-5551234', 'Raja Bazar, Rawalpindi']],
                'customers' => array_slice($people, 1, 5),
                'expenses' => $common('Salon rent', 160000),
            ],
            'hardware' => [
                'client' => 'Punjab Hardware Store', 'business' => 'Punjab Hardware Store', 'owner' => 'Chaudhry Aslam', 'cashier' => 'Sajid (Counter)',
                'email' => 'hardware@demo.test', 'phone' => '041-2612345', 'address' => 'Rail Bazar, Faisalabad', 'sku' => 'HDW',
                'items' => [
                    ['Brighto Weather Sheet Paint 4L', 'Paints', 6200, 5300, false],
                    ['PVC Pipe 1" (10ft)', 'Plumbing', 850, 680, false],
                    ['Water Tap (Faisal Sanitary)', 'Sanitary', 1450, 1100, false],
                    ['Fast Cable 7/29 (Coil 90m)', 'Electrical', 21500, 19000, false],
                    ['Tokai Drill Machine 13mm', 'Tools', 7800, 6500, false],
                    ['Wall Putty 20kg', 'Building Material', 1600, 1300, false],
                    ['Screws Assorted (Box)', 'Fasteners', 350, 240, false],
                ],
                'suppliers' => [['Faisalabad Hardware Wholesalers', '0300-6612233', 'Montgomery Bazar, Faisalabad'], ['Brighto Paints Distributor', '041-8711223', 'Susan Road, Faisalabad']],
                'customers' => array_slice($people, 0, 5),
                'expenses' => array_merge($common('Shop rent', 90000), [['Loader rickshaw (deliveries)', 'Transport', 12000]]),
            ],
            'services' => [
                'client' => 'FixIt Home Services', 'business' => 'FixIt Home Services', 'owner' => 'Zeeshan Haider', 'cashier' => 'Areeba (Billing)',
                'email' => 'services@demo.test', 'phone' => '021-35801122', 'address' => 'DHA Phase 6, Karachi', 'sku' => 'SRV',
                'items' => [
                    ['AC General Service', 'AC Services', 3500, 800, true],
                    ['AC Gas Refill', 'AC Services', 8000, 4500, true],
                    ['Plumber Visit (per hour)', 'Plumbing', 1500, 600, true],
                    ['Electrician Visit (per hour)', 'Electrical', 1500, 600, true],
                    ['Deep Cleaning (per room)', 'Cleaning', 4000, 1500, true],
                    ['Water Tank Cleaning', 'Cleaning', 5000, 1800, true],
                ],
                'suppliers' => [['Karachi AC Parts Supplier', '0333-2101010', 'Plaza Quarters, Karachi']],
                'customers' => array_slice($people, 0, 6),
                'expenses' => array_merge($common('Office rent', 75000), [['Bike fuel (technicians)', 'Transport', 18000]]),
            ],
            'wholesale' => [
                'client' => 'Hashmi Wholesale Traders', 'business' => 'Hashmi Wholesale Traders', 'owner' => 'Tahir Hashmi', 'cashier' => 'Naeem (Order Desk)',
                'email' => 'wholesale@demo.test', 'phone' => '042-37654321', 'address' => 'Shah Alam Market, Lahore', 'sku' => 'WHL',
                'items' => [
                    ['Sugar 50kg Bag', 'Commodities', 7400, 7000, false],
                    ['Flour (Atta) 20kg Bag', 'Commodities', 2600, 2400, false],
                    ['Cooking Oil Carton (12 x 1L)', 'Edible Oil', 6300, 5900, false],
                    ['Tea Carton (24 packs)', 'Beverages', 9800, 9100, false],
                    ['Detergent Carton (20 x 1kg)', 'Household', 12000, 11000, false],
                ],
                'suppliers' => [['Sugar Mills Agent', '0300-4100200', 'Akbari Mandi, Lahore'], ['FMCG Company Depot', '042-111-000-123', 'Kot Lakhpat, Lahore']],
                'customers' => [['Al-Madina Mart', '042-35761234', 'Johar Town, Lahore'], ['Bismillah Kiryana', '0301-4455667', 'Ichhra, Lahore'], ['Ghani General Store', '0322-1112233', 'Shadman, Lahore'], ['City Superstore', '042-35112233', 'Township, Lahore']],
                'expenses' => array_merge($common('Warehouse rent', 220000), [['Truck freight', 'Transport', 45000]]),
                'unit' => 'bag',
            ],
            'other' => [
                'client' => 'Rang Gift & Stationery', 'business' => 'Rang Gift & Stationery', 'owner' => 'Saad Rehman', 'cashier' => 'Iqra (Counter)',
                'email' => 'other@demo.test', 'phone' => '061-4512233', 'address' => 'Hussain Agahi, Multan', 'sku' => 'OTH',
                'items' => [
                    ['Dollar Ball Pen (Box of 10)', 'Stationery', 250, 180, false],
                    ['A4 Paper Rim (Double A)', 'Stationery', 1850, 1600, false],
                    ['School Bag', 'Bags', 2800, 1900, false],
                    ['Gift Hamper (Medium)', 'Gifts', 3500, 2300, false],
                    ['Photocopy (per page)', 'Services', 10, 4, true],
                    ['Gift Wrapping', 'Services', 200, 50, true],
                ],
                'suppliers' => [['Urdu Bazar Stationery Wholesale', '042-37321234', 'Urdu Bazar, Lahore']],
                'customers' => array_slice($people, 2, 4),
                'expenses' => $common('Shop rent', 60000),
            ],
            // Already have richer data via SmileCareDemoSeeder /
            // InstallmentStoreDemoSeeder; only used if no business exists.
            'dental-clinic' => [
                'client' => 'Smile Care Dental', 'business' => 'Smile Care Dental Clinic', 'owner' => 'Dr. Sara Ahmed', 'cashier' => 'Reception',
                'email' => 'dental@demo.test', 'phone' => '042-35881122', 'address' => 'DHA Phase 5, Lahore', 'sku' => 'DNT',
                'items' => [
                    ['Consultation', 'General', 1500, 0, true],
                    ['Scaling & Polishing', 'Preventive', 6000, 800, true],
                    ['Tooth Extraction', 'Surgery', 4000, 600, true],
                    ['Root Canal Treatment', 'Endodontics', 25000, 5000, true],
                ],
                'suppliers' => [['Dental Supplies Lahore', '0300-4007788', 'Jail Road, Lahore']],
                'customers' => array_slice($people, 0, 4),
                'expenses' => $common('Clinic rent', 150000),
            ],
            'installment' => [
                'client' => 'Easy Qist Store', 'business' => 'Easy Qist Store', 'owner' => 'Rana Imtiaz', 'cashier' => 'Recovery Desk',
                'email' => 'installment@demo.test', 'phone' => '042-37560011', 'address' => 'Main Bazar, Ichhra, Lahore', 'sku' => 'INS',
                'items' => [
                    ['Haier 43" LED TV', 'LED TVs', 85000, 71000, false],
                    ['Dawlance Refrigerator', 'Refrigerators', 145000, 124000, false],
                    ['Honda CD 70', 'Motorcycles', 157900, 149000, false],
                ],
                'suppliers' => [['Appliance Distributors (Hall Road)', '0321-4001122', 'Hall Road, Lahore']],
                'customers' => array_slice($people, 0, 4),
                'expenses' => $common('Showroom rent', 150000),
            ],
        ];
    }
}
