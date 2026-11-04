<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pakistani demo data for the "smillecare" dental clinic (owner
 * smillecare@gmail.com) so the dental-clinic business type can be tried
 * end to end: two clinic branches, staff, treatments + retail oral-care
 * stock, suppliers, purchases, ~45 days of patient bills, a return and
 * clinic expenses.
 *
 *   php artisan db:seed --class=SmileCareDemoSeeder
 *
 * Everything goes through the real services (PurchaseService,
 * SaleService, ReturnService) so stock, payments and reports add up.
 * Refuses to run twice (if the clinic already has patients).
 */
class SmileCareDemoSeeder extends Seeder
{
    private const OWNER_EMAIL = 'smillecare@gmail.com';

    public function run(PurchaseService $purchases, SaleService $sales, ReturnService $returns): void
    {
        mt_srand(2026);

        $owner = User::query()->where('email', self::OWNER_EMAIL)->firstOrFail();
        $business = Business::query()->findOrFail($owner->defaultBusinessId());

        if (Customer::query()->where('business_id', $business->id)->exists()) {
            $this->command?->warn('SmileCare already has patients — demo data not added again.');

            return;
        }

        DB::transaction(function () use ($owner, $business, $purchases, $sales, $returns) {
            $this->setupBusiness($business);
            [$gulberg, $dha] = $this->branches($business);
            $staff = $this->staff($business, $gulberg, $dha);
            $categories = $this->categories($business);
            $brands = $this->brands($business);
            $products = $this->products($business, $categories, $brands);
            $suppliers = $this->suppliers($business);
            $this->purchases($purchases, $owner, $gulberg, $dha, $suppliers, $products);
            $patients = $this->patients($business);
            $saleIds = $this->bills($sales, $gulberg, $dha, $staff, $owner, $patients, $products);
            $this->aReturn($returns, $owner, $saleIds);
            $this->expenses($business, $owner, $gulberg, $dha);
        });

        $this->command?->info('SmileCare dental clinic demo data added.');
    }

    private function setupBusiness(Business $business): void
    {
        $type = BusinessType::query()->where('slug', 'dental-clinic')->first();
        $business->forceFill([
            'business_type_id' => $type?->id ?? $business->business_type_id,
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'phone' => $business->phone ?: '042-35761234',
            'email' => $business->email ?: self::OWNER_EMAIL,
            'address' => $business->address ?: '45-B Main Boulevard, Gulberg III, Lahore',
        ])->save();
    }

    /** @return array{0: Branch, 1: Branch} */
    private function branches(Business $business): array
    {
        $gulberg = Branch::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => 'GLB'],
            ['name' => 'Gulberg Clinic', 'address' => '45-B Main Boulevard, Gulberg III, Lahore', 'phone' => '042-35761234', 'is_main' => true, 'status' => 'active'],
        );
        $dha = Branch::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => 'DHA'],
            ['name' => 'DHA Phase 5 Clinic', 'address' => '112 CCA, Phase 5, DHA, Lahore', 'phone' => '042-37189900', 'is_main' => false, 'status' => 'active'],
        );

        return [$gulberg, $dha];
    }

    /** @return array<string, User> */
    private function staff(Business $business, Branch $gulberg, Branch $dha): array
    {
        $people = [
            'manager' => ['Dr. Hina Tariq', 'hina.manager@smilecare.test', 'manager', null],
            'gulberg' => ['Ayesha Khalid', 'ayesha.reception@smilecare.test', 'cashier', $gulberg->id],
            'dha' => ['Usman Javed', 'usman.reception@smilecare.test', 'cashier', $dha->id],
        ];

        $users = [];
        foreach ($people as $key => [$name, $email, $role, $branchId]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make('password'),
                'status' => 'active',
                'client_id' => $business->client_id,
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'email_verified_at' => now(),
            ])->save();
            $roleId = Role::query()->where('slug', $role)->value('id');
            if ($roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, Category> */
    private function categories(Business $business): array
    {
        $names = [
            'general' => 'General Dentistry',
            'endo' => 'Root Canal & Endodontics',
            'ortho' => 'Orthodontics',
            'cosmetic' => 'Cosmetic Dentistry',
            'surgery' => 'Oral Surgery & Implants',
            'kids' => 'Pediatric Dentistry',
            'xray' => 'Radiology',
            'retail' => 'Oral Care Products',
            'supplies' => 'Clinic Supplies',
        ];

        $out = [];
        foreach ($names as $key => $name) {
            $category = new Category(['name' => $name, 'slug' => Str::slug($name), 'status' => 'active']);
            $category->business_id = $business->id;
            $category->save();
            $out[$key] = $category;
        }

        return $out;
    }

    /** @return array<string, Brand> */
    private function brands(Business $business): array
    {
        $out = [];
        foreach (['Sensodyne', 'Colgate', 'Oral-B', 'Listerine', 'Dentsply', '3M', 'Septodont'] as $name) {
            $brand = new Brand(['name' => $name, 'slug' => Str::slug($name), 'status' => 'active']);
            $brand->business_id = $business->id;
            $brand->save();
            $out[$name] = $brand;
        }

        return $out;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Brand>  $brands
     * @return array{services: list<Product>, retail: list<Product>, supplies: list<Product>}
     */
    private function products(Business $business, array $categories, array $brands): array
    {
        // [name, sku, category, sale price, cost]
        $treatments = [
            ['Consultation & Oral Checkup', 'TRT-001', 'general', 1500, 0],
            ['Scaling & Polishing', 'TRT-002', 'general', 4000, 600],
            ['Composite Filling (Tooth Coloured)', 'TRT-003', 'general', 5000, 900],
            ['Amalgam Filling', 'TRT-004', 'general', 3000, 500],
            ['Simple Tooth Extraction', 'TRT-005', 'surgery', 3000, 300],
            ['Wisdom Tooth Surgical Extraction', 'TRT-006', 'surgery', 15000, 2000],
            ['Root Canal Treatment (Front Tooth)', 'TRT-007', 'endo', 15000, 2500],
            ['Root Canal Treatment (Molar)', 'TRT-008', 'endo', 22000, 3500],
            ['PFM Crown (Porcelain Fused Metal)', 'TRT-009', 'cosmetic', 18000, 7000],
            ['Zirconia Crown', 'TRT-010', 'cosmetic', 35000, 14000],
            ['Teeth Whitening (In-Clinic)', 'TRT-011', 'cosmetic', 25000, 6000],
            ['Metal Braces (Full Treatment)', 'TRT-012', 'ortho', 150000, 35000],
            ['Ceramic Braces (Full Treatment)', 'TRT-013', 'ortho', 220000, 60000],
            ['Braces Monthly Adjustment', 'TRT-014', 'ortho', 3000, 200],
            ['Dental Implant (Single Tooth)', 'TRT-015', 'surgery', 120000, 55000],
            ['Complete Denture (Upper & Lower)', 'TRT-016', 'surgery', 45000, 18000],
            ['Fluoride Application (Kids)', 'TRT-017', 'kids', 2500, 300],
            ['Pit & Fissure Sealant (Per Tooth)', 'TRT-018', 'kids', 2000, 250],
            ['OPG (Full Mouth X-Ray)', 'XRY-001', 'xray', 2000, 300],
            ['Periapical X-Ray', 'XRY-002', 'xray', 800, 100],
        ];

        // [name, sku, barcode, category, brand, sale, cost, min stock, unit]
        $stocked = [
            ['Sensodyne Repair & Protect 100g', 'RTL-001', '8964000101011', 'retail', 'Sensodyne', 1150, 920, 10, 'pcs'],
            ['Sensodyne Rapid Relief 75g', 'RTL-002', '8964000101028', 'retail', 'Sensodyne', 980, 780, 10, 'pcs'],
            ['Colgate Total 150g', 'RTL-003', '8964000101035', 'retail', 'Colgate', 520, 410, 12, 'pcs'],
            ['Colgate Kids Strawberry 50g', 'RTL-004', '8964000101042', 'retail', 'Colgate', 280, 210, 8, 'pcs'],
            ['Oral-B Pro Toothbrush (Soft)', 'RTL-005', '8964000101059', 'retail', 'Oral-B', 350, 250, 15, 'pcs'],
            ['Oral-B Interdental Brush Pack', 'RTL-006', '8964000101066', 'retail', 'Oral-B', 650, 480, 6, 'pack'],
            ['Oral-B Dental Floss 50m', 'RTL-007', '8964000101073', 'retail', 'Oral-B', 450, 330, 8, 'pcs'],
            ['Listerine Cool Mint Mouthwash 250ml', 'RTL-008', '8964000101080', 'retail', 'Listerine', 780, 610, 8, 'bottle'],
            ['Orthodontic Wax', 'RTL-009', '8964000101097', 'retail', 'Dentsply', 300, 180, 10, 'pcs'],
            ['Kids Toothbrush (Cartoon)', 'RTL-010', '8964000101103', 'retail', 'Colgate', 250, 170, 10, 'pcs'],
            ['Lidocaine 2% Cartridges (Box of 50)', 'SUP-001', '8964000102018', 'supplies', 'Septodont', 6500, 5200, 3, 'box'],
            ['Latex Examination Gloves (Box of 100)', 'SUP-002', '8964000102025', 'supplies', '3M', 1800, 1400, 5, 'box'],
            ['Surgical Face Masks (Box of 50)', 'SUP-003', '8964000102032', 'supplies', '3M', 900, 650, 5, 'box'],
            ['Composite Resin Syringe A2', 'SUP-004', '8964000102049', 'supplies', '3M', 4200, 3300, 4, 'pcs'],
            ['Gutta Percha Points (Box)', 'SUP-005', '8964000102056', 'supplies', 'Dentsply', 2400, 1800, 3, 'box'],
        ];

        $out = ['services' => [], 'retail' => [], 'supplies' => []];

        foreach ($treatments as [$name, $sku, $cat, $price, $cost]) {
            $out['services'][] = $this->makeProduct($business, $categories[$cat], null, [
                'name' => $name, 'sku' => $sku, 'type' => 'service', 'sale_price' => $price, 'purchase_price' => $cost,
                'unit' => 'visit', 'minimum_stock' => 0,
            ]);
        }

        foreach ($stocked as [$name, $sku, $barcode, $cat, $brand, $price, $cost, $min, $unit]) {
            $out[$cat][] = $this->makeProduct($business, $categories[$cat], $brands[$brand], [
                'name' => $name, 'sku' => $sku, 'barcode' => $barcode, 'type' => 'product', 'sale_price' => $price,
                'purchase_price' => $cost, 'unit' => $unit, 'minimum_stock' => $min,
            ]);
        }

        return $out;
    }

    /** @param array<string, mixed> $attributes */
    private function makeProduct(Business $business, Category $category, ?Brand $brand, array $attributes): Product
    {
        $product = new Product($attributes + ['tax_rate' => 0, 'status' => 'active']);
        $product->business_id = $business->id;
        $product->category_id = $category->id;
        $product->brand_id = $brand?->id;
        $product->save();

        return $product;
    }

    /** @return list<Supplier> */
    private function suppliers(Business $business): array
    {
        $rows = [
            ['Dental Mart Pakistan', '0321-4567890', 'orders@dentalmart.pk', 'Shop 14, Hall Road, Lahore', 'Main supplier for consumables & anaesthesia.'],
            ['Medident Surgical Co.', '0300-8452211', 'sales@medident.pk', 'Plot 22, Saddar, Karachi', 'Crowns, implants & lab material. 30-day credit.'],
            ['Al-Shifa Pharma Distributors', '0333-4129876', null, 'Urdu Bazar, Lahore', 'Toothpastes, mouthwash & retail oral care.'],
            ['Precision Dental Lab', '0345-6677889', 'lab@precisiondental.pk', 'Model Town Link Road, Lahore', 'Dental lab — crowns, dentures, aligners.'],
        ];

        $out = [];
        foreach ($rows as [$name, $phone, $email, $address, $notes]) {
            $supplier = new Supplier(['name' => $name, 'phone' => $phone, 'email' => $email, 'address' => $address, 'notes' => $notes, 'status' => 'active']);
            $supplier->business_id = $business->id;
            $supplier->save();
            $out[] = $supplier;
        }

        return $out;
    }

    /**
     * @param  list<Supplier>  $suppliers
     * @param  array{services: list<Product>, retail: list<Product>, supplies: list<Product>}  $products
     */
    private function purchases(PurchaseService $service, User $owner, Branch $gulberg, Branch $dha, array $suppliers, array $products): void
    {
        $line = fn (Product $p, int $qty) => ['product_id' => $p->id, 'quantity' => $qty, 'purchase_price' => (float) $p->purchase_price];
        $retail = $products['retail'];
        $supplies = $products['supplies'];

        $service->create($gulberg, $suppliers[2], $owner, array_map(fn ($p) => $line($p, 40), $retail), 'ASPD-2209', now()->subDays(50)->toDateString(), 'Opening retail stock — Gulberg');
        $service->create($dha, $suppliers[2], $owner, array_map(fn ($p) => $line($p, 25), $retail), 'ASPD-2214', now()->subDays(48)->toDateString(), 'Opening retail stock — DHA');
        $service->create($gulberg, $suppliers[0], $owner, array_map(fn ($p) => $line($p, 12), $supplies), 'DMP-7781', now()->subDays(45)->toDateString(), 'Monthly consumables');
        $service->create($dha, $suppliers[0], $owner, array_map(fn ($p) => $line($p, 8), $supplies), 'DMP-7790', now()->subDays(44)->toDateString(), 'Monthly consumables');
        $service->create($gulberg, $suppliers[2], $owner, [$line($retail[0], 24), $line($retail[2], 24), $line($retail[7], 12)], 'ASPD-2301', now()->subDays(15)->toDateString(), 'Re-order fast movers');
        $service->create($gulberg, $suppliers[0], $owner, [$line($supplies[0], 6), $line($supplies[1], 10)], 'DMP-7902', now()->subDays(2)->toDateString(), 'Awaiting delivery', 'pending');
    }

    /** @return list<Customer> */
    private function patients(Business $business): array
    {
        $rows = [
            ['Muhammad Ali Raza', '0300-4123567', 'House 12, Block C, Model Town, Lahore'],
            ['Ayesha Siddiqui', '0321-7845123', 'Flat 4, Askari 11, Lahore'],
            ['Bilal Ahmed Khan', '0333-9087654', '23-D, Johar Town, Lahore'],
            ['Fatima Noor', '0345-2234567', 'Street 7, Garden Town, Lahore'],
            ['Hamza Iqbal', '0301-5566778', '88-E, DHA Phase 6, Lahore'],
            ['Zainab Bibi', '0312-4455667', 'Mohallah Islampura, Lahore'],
            ['Usman Tariq', '0322-1122334', '15-A, Faisal Town, Lahore'],
            ['Sana Mehmood', '0334-6677881', 'Bahria Town, Sector C, Lahore'],
            ['Ahmed Hassan Butt', '0300-9988776', 'Shadman Colony, Lahore'],
            ['Maryam Javed', '0346-3344556', 'Cavalry Ground, Lahore'],
            ['Imran Shahid', '0302-7788990', 'Township, Sector A2, Lahore'],
            ['Hira Aslam', '0323-5544332', 'Wapda Town, Lahore'],
            ['Kashif Mehmood Chaudhry', '0300-1239876', 'Valencia Town, Lahore'],
            ['Rabia Anwar', '0331-8765432', 'Samanabad, Lahore'],
            ['Faisal Qureshi', '0347-2345678', 'DHA Phase 5, Lahore'],
            ['Nimra Shafiq', '0315-6789012', 'Iqbal Town, Lahore'],
            ['Shahzad Akram', '0306-4567891', 'Allama Iqbal Town, Lahore'],
            ['Amna Rafiq', '0324-9876501', 'Gulberg II, Lahore'],
            ['Waqas Haider', '0335-1122998', 'Cantt, Lahore'],
            ['Iqra Nadeem', '0341-5566009', 'PCSIR Housing Society, Lahore'],
            ['Tahir Mahmood', '0300-8877112', 'Ichhra, Lahore'],
            ['Mehwish Saleem', '0320-6655443', 'Lake City, Lahore'],
            ['Abdullah Farooq', '0332-7766554', 'EME Society, Lahore'],
            ['Khadija Yousaf', '0348-3322110', 'Gulshan-e-Ravi, Lahore'],
            ['Saad Rehman', '0303-9900887', 'State Life Society, Lahore'],
            ['Anum Zafar', '0313-1100223', 'Defence Raya, Lahore'],
            ['Noman Sheikh', '0325-4433221', 'Mughalpura, Lahore'],
            ['Aqsa Malik', '0336-2211009', 'Canal View, Lahore'],
            ['Haris Abbasi', '0349-8899776', 'Paragon City, Lahore'],
            ['Saima Pervaiz', '0304-6677889', 'Wahdat Road, Lahore'],
            ['Arslan Nawaz', '0316-5544778', 'Sabzazar, Lahore'],
            ['Eman Fatima (Child)', '0300-4123567', 'House 12, Block C, Model Town, Lahore'],
        ];
        $notes = [
            'Allergic to penicillin.', 'Diabetic — check sugar before extraction.', 'Prefers evening appointments.',
            'Braces patient — monthly adjustment.', 'Sensitive teeth.', null, null, null, null, 'Referred by Dr. Hina.',
        ];

        $out = [];
        foreach ($rows as $i => [$name, $phone, $address]) {
            $patient = new Customer([
                'name' => $name,
                'phone' => $phone,
                'email' => $i % 4 === 0 ? Str::of($name)->lower()->replaceMatches('/[^a-z ]/', '')->squish()->replace(' ', '.').'@gmail.com' : null,
                'address' => $address,
                'notes' => $notes[$i % count($notes)],
                'status' => 'active',
            ]);
            $patient->business_id = $business->id;
            $patient->save();
            $out[] = $patient;
        }

        return $out;
    }

    /**
     * ~45 days of clinic bills across both branches: mostly treatments,
     * often with a retail item, paid by cash / card / bank transfer.
     *
     * @param  array<string, User>  $staff
     * @param  list<Customer>  $patients
     * @param  array{services: list<Product>, retail: list<Product>, supplies: list<Product>}  $products
     * @return list<int>
     */
    private function bills(SaleService $service, Branch $gulberg, Branch $dha, array $staff, User $owner, array $patients, array $products): array
    {
        $services = $products['services'];
        $retail = $products['retail'];
        // Common treatments weighted heavier than braces/implants.
        $weighted = [0, 0, 0, 1, 1, 2, 2, 3, 4, 4, 5, 6, 7, 8, 9, 10, 11, 13, 13, 13, 14, 16, 17, 18, 18, 19];
        $cash = PaymentMethod::query()->where('slug', 'cash')->value('id');
        $card = PaymentMethod::query()->where('slug', 'card')->value('id');
        $bank = PaymentMethod::query()->where('slug', 'bank')->value('id');

        $ids = [];
        for ($daysAgo = 44; $daysAgo >= 0; $daysAgo--) {
            $day = now('Asia/Karachi')->subDays($daysAgo);
            if ($day->isSunday()) {
                continue;
            }
            $visits = mt_rand(1, $daysAgo === 0 ? 4 : 3);

            for ($v = 0; $v < $visits; $v++) {
                $atDha = mt_rand(1, 100) <= 40;
                $branch = $atDha ? $dha : $gulberg;
                $cashier = $atDha ? $staff['dha'] : (mt_rand(0, 3) === 0 ? $owner : $staff['gulberg']);
                $patient = $patients[mt_rand(0, count($patients) - 1)];

                $items = [];
                $treatment = $services[$weighted[mt_rand(0, count($weighted) - 1)]];
                $items[$treatment->id] = ['product_id' => $treatment->id, 'quantity' => 1];
                if (mt_rand(1, 100) <= 35) {
                    $xray = $services[mt_rand(18, 19)];
                    $items[$xray->id] = ['product_id' => $xray->id, 'quantity' => 1];
                }
                if (mt_rand(1, 100) <= 45) {
                    $item = $retail[mt_rand(0, count($retail) - 1)];
                    $items[$item->id] = ['product_id' => $item->id, 'quantity' => mt_rand(1, 2)];
                }
                $items = array_values($items);

                // Occasional courtesy discount on the treatment line.
                if (mt_rand(1, 100) <= 15 && (float) $treatment->sale_price >= 5000) {
                    $items[0]['discount'] = round((float) $treatment->sale_price * 0.1, 2);
                }

                $total = 0.0;
                foreach ($items as $line) {
                    $price = (float) Product::query()->whereKey($line['product_id'])->value('sale_price');
                    $total += $price * $line['quantity'] - ($line['discount'] ?? 0);
                }
                $total = round($total, 2);

                $roll = mt_rand(1, 100);
                $payments = match (true) {
                    $total >= 50000 && $roll <= 60 => [['payment_method_id' => $bank, 'amount' => $total, 'reference' => 'IBFT-'.mt_rand(100000, 999999)]],
                    $roll <= 55 => [['payment_method_id' => $cash, 'amount' => $total]],
                    $roll <= 85 => [['payment_method_id' => $card, 'amount' => $total, 'reference' => 'POS-'.mt_rand(1000, 9999)]],
                    default => [['payment_method_id' => $bank, 'amount' => $total, 'reference' => 'JazzCash '.mt_rand(10000000, 99999999)]],
                };

                $at = Carbon::parse($day->toDateString().' '.sprintf('%02d:%02d:00', mt_rand(10, 20), mt_rand(0, 59)), 'Asia/Karachi');
                if ($at->isFuture()) {
                    $at = now('Asia/Karachi')->subMinutes(mt_rand(5, 120));
                }

                $receipt = $service->create(
                    $branch,
                    $cashier,
                    mt_rand(1, 100) <= 92 ? $patient : null,
                    $items,
                    $payments,
                    (string) Str::uuid(),
                    ['items' => $items, 'payments' => $payments],
                    null,
                    $at->clone()->utc(),
                );
                $ids[] = (int) \App\Models\Sale::query()
                    ->where('business_id', $branch->business_id)
                    ->where('invoice_number', $receipt['invoice_number'])
                    ->value('id');
            }
        }

        return array_values(array_filter($ids));
    }

    /** @param list<int> $saleIds */
    private function aReturn(ReturnService $service, User $owner, array $saleIds): void
    {
        // A patient returning an unopened retail item from an older bill.
        $sale = \App\Models\Sale::query()
            ->whereIn('id', array_slice($saleIds, 0, 40))
            ->whereHas('items.product', fn ($q) => $q->where('type', 'product'))
            ->with('items.product')
            ->first();

        $item = $sale?->items->first(fn ($i) => $i->product?->type === 'product');
        if ($sale && $item) {
            $service->create($sale, $owner, [['sale_item_id' => $item->id, 'quantity' => 1]], 'Unopened — patient bought the wrong toothpaste');
        }
    }

    private function expenses(Business $business, User $owner, Branch $gulberg, Branch $dha): void
    {
        $months = [now('Asia/Karachi')->subMonthNoOverflow()->startOfMonth(), now('Asia/Karachi')->startOfMonth()];
        $rows = [];

        foreach ($months as $m) {
            $rows[] = [$gulberg, 'Clinic rent — Gulberg', 'Rent', 95000, $m->clone()->addDays(1)];
            $rows[] = [$dha, 'Clinic rent — DHA Phase 5', 'Rent', 120000, $m->clone()->addDays(1)];
            $rows[] = [$gulberg, 'Staff salaries (assistants & reception)', 'Salaries', 110000, $m->clone()->addDays(2)];
            $rows[] = [$dha, 'Staff salaries (assistants & reception)', 'Salaries', 85000, $m->clone()->addDays(2)];
            $rows[] = [$gulberg, 'LESCO electricity bill', 'Utilities', 28500, $m->clone()->addDays(9)];
            $rows[] = [$dha, 'LESCO electricity bill', 'Utilities', 31200, $m->clone()->addDays(9)];
            $rows[] = [$gulberg, 'SNGPL gas bill', 'Utilities', 6400, $m->clone()->addDays(10)];
            $rows[] = [$gulberg, 'PTCL Flash Fiber internet', 'Utilities', 5500, $m->clone()->addDays(5)];
            $rows[] = [$dha, 'Nayatel internet', 'Utilities', 4999, $m->clone()->addDays(5)];
            $rows[] = [$gulberg, 'Precision Dental Lab — crowns & dentures', 'Supplies', 38000, $m->clone()->addDays(14)];
            $rows[] = [$gulberg, 'Facebook & Instagram ads', 'Marketing', 25000, $m->clone()];
            $rows[] = [$dha, 'Autoclave & dental chair servicing', 'Maintenance', 15000, $m->clone()->addDays(18)];
            $rows[] = [$gulberg, 'Generator diesel', 'Utilities', 12000, $m->clone()->addDays(12)];
        }
        $rows[] = [$gulberg, 'Tea, water & refreshments', 'Other', 3500, now('Asia/Karachi')->subDays(1)];
        $rows[] = [$dha, 'Rickshaw — lab delivery', 'Transport', 800, now('Asia/Karachi')];

        foreach ($rows as [$branch, $title, $category, $amount, $date]) {
            if ($date->isFuture()) {
                continue;
            }
            $expense = new Expense(['title' => $title, 'category' => $category, 'amount' => $amount, 'expense_date' => $date->toDateString()]);
            $expense->business_id = $business->id;
            $expense->branch_id = $branch->id;
            $expense->user_id = $owner->id;
            $expense->save();
        }
    }
}
