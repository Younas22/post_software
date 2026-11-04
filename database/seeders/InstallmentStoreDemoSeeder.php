<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InstallmentPlan;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InstallmentService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pakistani demo data for an installment (qist) store — owner
 * install@gmail.com — so the Installments module can be demoed with a
 * realistic book: appliances, mobiles and motorcycles sold on 6–18 month
 * plans, guarantors with CNICs, a payment history where most customers pay
 * on time, some are late (overdue), some plans are completed and one was
 * cancelled — plus a few cash sales, purchases and shop expenses.
 *
 *   php artisan db:seed --class=InstallmentStoreDemoSeeder
 *
 * Uses the real services (PurchaseService, InstallmentService,
 * SaleService) so stock, balances and reports add up. Refuses to run twice.
 */
class InstallmentStoreDemoSeeder extends Seeder
{
    private const OWNER_EMAIL = 'install@gmail.com';

    public function run(PurchaseService $purchases, InstallmentService $installments, SaleService $sales): void
    {
        mt_srand(1947);

        $owner = User::query()->where('email', self::OWNER_EMAIL)->firstOrFail();
        $business = Business::query()->findOrFail($owner->defaultBusinessId());

        if (Customer::query()->where('business_id', $business->id)->exists()) {
            $this->command?->warn('This store already has customers — demo data not added again.');

            return;
        }

        DB::transaction(function () use ($owner, $business, $purchases, $installments, $sales) {
            $this->setupBusiness($business);
            [$main, $township] = $this->branches($business);
            $staff = $this->staff($business, $main, $township);
            $products = $this->catalog($business);
            $suppliers = $this->suppliers($business);
            $this->purchases($purchases, $owner, $main, $township, $suppliers, $products);
            $customers = $this->customers($business);
            $this->plans($installments, $owner, $staff, $main, $township, $customers, $products);
            $this->cashSales($sales, $staff, $owner, $main, $township, $customers, $products);
            $this->expenses($business, $owner, $main, $township);
        });

        $this->command?->info('Installment store demo data added.');
    }

    private function setupBusiness(Business $business): void
    {
        $type = BusinessType::query()->where('slug', 'installment')->first();
        $business->forceFill([
            'business_type_id' => $type?->id ?? $business->business_type_id,
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'phone' => $business->phone ?: '0300-8456123',
            'email' => $business->email ?: self::OWNER_EMAIL,
            'address' => $business->address ?: 'Shop 12-14, Main Bazar, Ichhra, Lahore',
        ])->save();
    }

    /** @return array{0: Branch, 1: Branch} */
    private function branches(Business $business): array
    {
        $main = Branch::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => 'ICH'],
            ['name' => 'Ichhra Main Showroom', 'address' => 'Shop 12-14, Main Bazar, Ichhra, Lahore', 'phone' => '042-37560011', 'is_main' => true, 'status' => 'active'],
        );
        $township = Branch::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => 'TWP'],
            ['name' => 'Township Branch', 'address' => 'Plot 5, College Road, Township, Lahore', 'phone' => '042-35112233', 'is_main' => false, 'status' => 'active'],
        );

        return [$main, $township];
    }

    /** @return array<string, User> */
    private function staff(Business $business, Branch $main, Branch $township): array
    {
        $people = [
            'manager' => ['Rana Shahbaz', 'shahbaz.manager@qist.test', 'manager', null],
            'recovery' => ['Asif Iqbal (Recovery Officer)', 'asif.recovery@qist.test', 'cashier', $main->id],
            'township' => ['Naveed Akhtar', 'naveed.sales@qist.test', 'cashier', $township->id],
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

    /** @return array<string, Product> keyed by SKU */
    private function catalog(Business $business): array
    {
        $categories = [];
        foreach (['LED TVs', 'Refrigerators', 'Air Conditioners', 'Washing Machines', 'Mobile Phones', 'Motorcycles', 'Furniture', 'Kitchen Appliances', 'Fans & UPS'] as $name) {
            $c = new Category(['name' => $name, 'slug' => Str::slug($name), 'status' => 'active']);
            $c->business_id = $business->id;
            $c->save();
            $categories[$name] = $c;
        }

        $brands = [];
        foreach (['Haier', 'Dawlance', 'PEL', 'Orient', 'Gree', 'Samsung', 'Infinix', 'Tecno', 'Honda', 'Yamaha', 'Super Asia', 'Pak Fan', 'Homage', 'Interwood', 'Kenwood'] as $name) {
            $b = new Brand(['name' => $name, 'slug' => Str::slug($name), 'status' => 'active']);
            $b->business_id = $business->id;
            $b->save();
            $brands[$name] = $b;
        }

        // [name, sku, category, brand, cash price, cost, min stock]
        $rows = [
            ['Haier 43" Smart LED TV (H43K800FX)', 'TV-HR-43', 'LED TVs', 'Haier', 85000, 71000, 2],
            ['Samsung 55" Crystal 4K UHD TV', 'TV-SS-55', 'LED TVs', 'Samsung', 185000, 158000, 1],
            ['Dawlance 9178 Refrigerator (Inverter)', 'RF-DW-9178', 'Refrigerators', 'Dawlance', 145000, 124000, 2],
            ['Haier HRF-368 Refrigerator', 'RF-HR-368', 'Refrigerators', 'Haier', 165000, 140000, 1],
            ['PEL Desire Refrigerator (Medium)', 'RF-PEL-MD', 'Refrigerators', 'PEL', 98000, 82000, 1],
            ['Gree 1.5 Ton DC Inverter AC', 'AC-GR-15', 'Air Conditioners', 'Gree', 235000, 205000, 1],
            ['Orient 1 Ton Inverter AC', 'AC-OR-10', 'Air Conditioners', 'Orient', 165000, 142000, 1],
            ['Haier Fully Automatic Washing Machine 8kg', 'WM-HR-8', 'Washing Machines', 'Haier', 115000, 96000, 1],
            ['Super Asia Twin Tub Washing Machine', 'WM-SA-TT', 'Washing Machines', 'Super Asia', 38000, 31000, 2],
            ['Infinix Hot 40 (8GB/256GB)', 'MB-INF-H40', 'Mobile Phones', 'Infinix', 42000, 37000, 3],
            ['Samsung Galaxy A15 (6GB/128GB)', 'MB-SS-A15', 'Mobile Phones', 'Samsung', 55000, 49000, 3],
            ['Tecno Spark 20 (8GB/256GB)', 'MB-TC-S20', 'Mobile Phones', 'Tecno', 35000, 30500, 3],
            ['Honda CD 70 (2025 Model)', 'BK-HN-CD70', 'Motorcycles', 'Honda', 157900, 149000, 1],
            ['Honda CG 125 (2025 Model)', 'BK-HN-CG125', 'Motorcycles', 'Honda', 249900, 237000, 1],
            ['Yamaha YB125Z-DX', 'BK-YM-125', 'Motorcycles', 'Yamaha', 380000, 360000, 1],
            ['Interwood 7-Seater Sofa Set', 'FN-IW-SOFA', 'Furniture', 'Interwood', 120000, 92000, 1],
            ['King Size Bed Set with Side Tables', 'FN-BED-KS', 'Furniture', 'Interwood', 150000, 118000, 1],
            ['Dawlance Microwave Oven 30L', 'KT-DW-MW', 'Kitchen Appliances', 'Dawlance', 32000, 26500, 2],
            ['Kenwood Juicer Blender', 'KT-KW-JB', 'Kitchen Appliances', 'Kenwood', 12000, 9500, 3],
            ['Pak Fan 56" Ceiling Fan (Inverter)', 'FU-PF-56', 'Fans & UPS', 'Pak Fan', 14500, 11800, 5],
            ['Homage UPS 1000VA', 'FU-HM-1000', 'Fans & UPS', 'Homage', 38000, 32000, 2],
        ];

        $products = [];
        foreach ($rows as $i => [$name, $sku, $cat, $brand, $price, $cost, $min]) {
            $p = new Product([
                'name' => $name, 'sku' => $sku, 'barcode' => '89650'.str_pad((string) ($i + 1001), 8, '0', STR_PAD_LEFT),
                'type' => 'product', 'sale_price' => $price, 'purchase_price' => $cost, 'tax_rate' => 0,
                'unit' => 'pcs', 'minimum_stock' => $min, 'status' => 'active',
            ]);
            $p->business_id = $business->id;
            $p->category_id = $categories[$cat]->id;
            $p->brand_id = $brands[$brand]->id;
            $p->save();
            $products[$sku] = $p;
        }

        return $products;
    }

    /** @return list<Supplier> */
    private function suppliers(Business $business): array
    {
        $rows = [
            ['Haier & Dawlance Distributors (Hall Road)', '0321-4001122', 'orders@halldistributors.pk', 'Hall Road, Lahore', 'TVs, fridges, washing machines. 45-day credit.'],
            ['Atlas Honda Dealer — Ichhra Motors', '0300-4455667', 'ichhramotors@gmail.com', 'Ferozepur Road, Ichhra, Lahore', 'Honda motorcycles; registration via dealer.'],
            ['Hafeez Centre Mobile Wholesale', '0333-4123987', null, 'Hafeez Centre, Gulberg III, Lahore', 'Infinix, Tecno, Samsung — PTA approved stock.'],
            ['Gree & Orient AC Traders', '0345-6789123', 'sales@coolairtraders.pk', 'Brandreth Road, Lahore', 'Inverter ACs, seasonal stock.'],
            ['Interwood Furniture Outlet', '0311-2223344', null, 'Township, Lahore', 'Sofa sets & bed sets.'],
        ];

        $out = [];
        foreach ($rows as [$name, $phone, $email, $address, $notes]) {
            $s = new Supplier(['name' => $name, 'phone' => $phone, 'email' => $email, 'address' => $address, 'notes' => $notes, 'status' => 'active']);
            $s->business_id = $business->id;
            $s->save();
            $out[] = $s;
        }

        return $out;
    }

    /**
     * Enough opening stock for everything the plans and cash sales hand over.
     *
     * @param  list<Supplier>  $suppliers
     * @param  array<string, Product>  $p
     */
    private function purchases(PurchaseService $service, User $owner, Branch $main, Branch $township, array $suppliers, array $p): void
    {
        $l = fn (string $sku, int $qty) => ['product_id' => $p[$sku]->id, 'quantity' => $qty, 'purchase_price' => (float) $p[$sku]->purchase_price];
        $date = fn (int $daysAgo) => now('Asia/Karachi')->subDays($daysAgo)->toDateString();

        $service->create($main, $suppliers[0], $owner, [
            $l('TV-HR-43', 10), $l('TV-SS-55', 4), $l('RF-DW-9178', 8), $l('RF-HR-368', 5), $l('RF-PEL-MD', 4),
            $l('WM-HR-8', 5), $l('WM-SA-TT', 6), $l('KT-DW-MW', 6), $l('KT-KW-JB', 8),
        ], 'HDD-10231', $date(330), 'Opening appliance stock');
        $service->create($main, $suppliers[1], $owner, [$l('BK-HN-CD70', 8), $l('BK-HN-CG125', 5), $l('BK-YM-125', 3)], 'IM-4471', $date(320), 'Motorcycles — booked on installment');
        $service->create($main, $suppliers[2], $owner, [$l('MB-INF-H40', 12), $l('MB-SS-A15', 10), $l('MB-TC-S20', 12)], 'HCM-882', $date(300), 'Mobile phones (PTA approved)');
        $service->create($main, $suppliers[3], $owner, [$l('AC-GR-15', 6), $l('AC-OR-10', 6)], 'CAT-3310', $date(200), 'Summer AC stock');
        $service->create($main, $suppliers[4], $owner, [$l('FN-IW-SOFA', 3), $l('FN-BED-KS', 3)], 'IWF-119', $date(180), 'Furniture');
        $service->create($main, $suppliers[0], $owner, [$l('FU-PF-56', 45), $l('FU-HM-1000', 12)], 'HDD-10877', $date(60), 'Fans & UPS for cash counter');
        $service->create($township, $suppliers[0], $owner, [$l('TV-HR-43', 4), $l('RF-DW-9178', 3), $l('WM-SA-TT', 8), $l('FU-PF-56', 30), $l('FU-HM-1000', 8)], 'HDD-10902', $date(55), 'Township branch stock');
        $service->create($township, $suppliers[2], $owner, [$l('MB-INF-H40', 6), $l('MB-TC-S20', 6), $l('MB-SS-A15', 4)], 'HCM-951', $date(50), 'Township mobiles');
        $service->create($main, $suppliers[0], $owner, [$l('TV-HR-43', 6), $l('RF-DW-9178', 4)], 'HDD-11240', $date(3), 'Re-order — awaiting delivery', 'pending');
    }

    /** @return list<array{customer: Customer, cnic: string, job: string}> */
    private function customers(Business $business): array
    {
        $rows = [
            ['Muhammad Akram', '0300-4512367', 'Mohallah Rehmanpura, Ichhra, Lahore', 'Govt. school teacher'],
            ['Shazia Parveen', '0321-6634521', 'Street 4, Shadab Colony, Lahore', 'Lady health worker'],
            ['Ghulam Mustafa', '0333-7845120', 'Gulshan-e-Ravi, Lahore', 'Rickshaw driver'],
            ['Rizwan Ahmed', '0345-1239874', 'Samanabad, Lahore', 'Bank cashier'],
            ['Nasreen Akhtar', '0301-9988771', 'Township Sector B1, Lahore', 'Boutique owner'],
            ['Imran Ali', '0312-5566439', 'Ichhra, Lahore', 'Electrician'],
            ['Tariq Mehmood', '0322-1144556', 'Wahdat Road, Lahore', 'Wapda lineman'],
            ['Saima Kousar', '0334-7788112', 'Shah Jamal, Lahore', 'Tuition teacher'],
            ['Zeeshan Haider', '0300-2233445', 'Faisal Town, Lahore', 'Software house employee'],
            ['Muhammad Asif', '0346-9911223', 'Kot Lakhpat, Lahore', 'Factory supervisor'],
            ['Kiran Shahzadi', '0302-3344556', 'Green Town, Lahore', 'Nurse, Jinnah Hospital'],
            ['Abdul Rehman', '0323-6677884', 'Sabzazar, Lahore', 'Shopkeeper'],
            ['Naeem Abbas', '0335-1122339', 'Allama Iqbal Town, Lahore', 'Police constable'],
            ['Rubina Yasmeen', '0341-5566770', 'Mozang, Lahore', 'Housewife'],
            ['Waseem Akram Butt', '0300-8877665', 'Ichhra, Lahore', 'Tailor'],
            ['Faisal Shahzad', '0313-2244668', 'Model Town Extension, Lahore', 'Delivery rider'],
            ['Sadia Batool', '0324-9988112', 'Township Sector A1, Lahore', 'Beautician'],
            ['Kamran Yousaf', '0336-4455881', 'Johar Town, Lahore', 'Real estate agent'],
            ['Javed Iqbal', '0347-1239900', 'Kahna, Lahore', 'Farmer'],
            ['Mehreen Fatima', '0303-6655441', 'Garden Town, Lahore', 'College lecturer'],
            ['Shoaib Akhtar', '0314-7766553', 'Bund Road, Lahore', 'Mechanic'],
            ['Nadia Khan', '0325-3322119', 'Gulberg, Lahore', 'Call centre agent'],
            ['Usman Ghani', '0337-8899001', 'Raiwind Road, Lahore', 'Driver (Careem)'],
            ['Hafiz Abdullah', '0348-2211007', 'Ichhra, Lahore', 'Madrasa teacher'],
            ['Ali Raza Gondal', '0304-5544337', 'Township Sector C, Lahore', 'Govt. clerk'],
            ['Samina Riaz', '0315-6677002', 'Chungi Amar Sidhu, Lahore', 'Stitching work from home'],
            ['Adnan Siddique', '0326-1199228', 'DHA Rehbar, Lahore', 'Pharmacy salesman'],
            ['Bushra Nawaz', '0338-4400556', 'Ichhra, Lahore', 'School van owner'],
        ];

        $out = [];
        foreach ($rows as $i => [$name, $phone, $address, $job]) {
            $c = new Customer(['name' => $name, 'phone' => $phone, 'address' => $address, 'notes' => $job, 'status' => 'active']);
            $c->business_id = $business->id;
            $c->save();
            $out[] = ['customer' => $c, 'cnic' => sprintf('352%02d-%07d-%d', mt_rand(1, 2), mt_rand(1000000, 9999999), mt_rand(1, 9)), 'job' => $job];
        }

        return $out;
    }

    /**
     * Plans with a believable repayment history.
     *
     * Behaviour per plan: on-time payers, late payers (overdue now),
     * a partial payer, fully completed plans and one cancelled plan.
     *
     * @param  array<string, User>  $staff
     * @param  list<array{customer: Customer, cnic: string, job: string}>  $customers
     * @param  array<string, Product>  $p
     */
    private function plans(InstallmentService $service, User $owner, array $staff, Branch $main, Branch $township, array $customers, array $p): void
    {
        $guarantors = [
            'Muhammad Saleem', 'Haji Bashir Ahmed', 'Khalid Mehmood', 'Sajid Hussain', 'Rana Arshad', 'Malik Nadeem',
            'Chaudhry Aslam', 'Abid Ali', 'Mian Javed', 'Sheikh Waqar', 'Qamar Zaman', 'Pervaiz Iqbal',
        ];
        $cash = PaymentMethod::query()->where('slug', 'cash')->value('id');
        $bank = PaymentMethod::query()->where('slug', 'bank')->value('id');
        $today = now('Asia/Karachi')->startOfDay();

        // [customer#, sku(s), months ago started, installments, markup %, down %, behaviour, branch]
        $book = [
            [0, ['TV-HR-43'], 9, 12, 20, 20, 'good', 'main'],
            [1, ['RF-DW-9178'], 7, 12, 22, 25, 'good', 'main'],
            [2, ['BK-HN-CD70'], 10, 18, 25, 25, 'late', 'main'],
            [3, ['TV-SS-55'], 5, 12, 18, 30, 'good', 'main'],
            [4, ['WM-HR-8', 'KT-DW-MW'], 6, 10, 20, 20, 'good', 'main'],
            [5, ['MB-INF-H40'], 8, 6, 25, 20, 'completed', 'main'],
            [6, ['AC-GR-15'], 5, 12, 22, 25, 'late', 'main'],
            [7, ['MB-TC-S20'], 3, 6, 25, 20, 'partial', 'township'],
            [8, ['MB-SS-A15'], 4, 6, 20, 30, 'good', 'main'],
            [9, ['BK-HN-CG125'], 8, 18, 25, 30, 'good', 'main'],
            [10, ['RF-HR-368'], 2, 12, 20, 20, 'good', 'main'],
            [11, ['WM-SA-TT', 'FU-PF-56'], 11, 6, 20, 20, 'completed', 'township'],
            [12, ['BK-YM-125'], 6, 18, 25, 35, 'late', 'main'],
            [13, ['FN-IW-SOFA'], 4, 10, 20, 20, 'good', 'main'],
            [14, ['TV-HR-43'], 1, 12, 20, 20, 'good', 'township'],
            [15, ['MB-INF-H40'], 2, 6, 25, 20, 'late', 'main'],
            [16, ['FN-BED-KS'], 3, 12, 20, 25, 'good', 'main'],
            [17, ['AC-OR-10'], 4, 10, 22, 25, 'good', 'main'],
            [18, ['RF-PEL-MD'], 9, 12, 20, 20, 'late', 'main'],
            [19, ['MB-SS-A15', 'KT-KW-JB'], 0, 6, 20, 25, 'new', 'main'],
            [20, ['BK-HN-CD70'], 12, 12, 25, 25, 'completed', 'main'],
            [21, ['TV-HR-43'], 3, 12, 20, 15, 'cancelled', 'main'],
            [22, ['BK-HN-CD70'], 1, 18, 25, 25, 'good', 'main'],
            [23, ['WM-SA-TT'], 2, 6, 20, 20, 'good', 'township'],
            [24, ['RF-DW-9178'], 0, 12, 22, 25, 'new', 'township'],
            [25, ['MB-TC-S20'], 5, 6, 25, 20, 'good', 'township'],
            [26, ['AC-GR-15'], 3, 12, 22, 25, 'good', 'main'],
            [27, ['TV-SS-55'], 6, 12, 18, 25, 'late', 'main'],
        ];

        foreach ($book as $i => [$ci, $skus, $monthsAgo, $count, $markup, $downPct, $behaviour, $branchKey]) {
            $branch = $branchKey === 'main' ? $main : $township;
            $seller = $branchKey === 'main' ? $owner : $staff['township'];
            $collector = $branchKey === 'main' ? $staff['recovery'] : $staff['township'];
            $cust = $customers[$ci];

            $start = $today->clone()->subMonthsNoOverflow($monthsAgo)->subDays(mt_rand(0, 20));
            if ($start->greaterThan($today)) {
                $start = $today->clone();
            }
            // Every 5th plan is due on today's date of the month, so "Due today" has rows.
            $firstDue = $start->clone()->addMonthNoOverflow();
            if ($i % 5 === 0 && $monthsAgo > 0) {
                $firstDue = $firstDue->setDay(min($today->day, $firstDue->daysInMonth));
            }

            $items = array_map(fn ($sku) => ['product_id' => $p[$sku]->id, 'quantity' => 1], $skus);
            $subtotal = array_sum(array_map(fn ($sku) => (float) $p[$sku]->sale_price, $skus));
            $total = $subtotal * (1 + $markup / 100);
            $down = round($total * $downPct / 100 / 1000) * 1000;
            $isBike = str_starts_with($skus[0], 'BK-');
            $isPhone = str_starts_with($skus[0], 'MB-');

            $items[0]['serial_number'] = match (true) {
                $isBike => 'Chassis '.Str::upper(Str::random(3)).'-'.mt_rand(100000, 999999).' / LEB-'.mt_rand(1000, 9999),
                $isPhone => 'IMEI 35'.mt_rand(1000000, 9999999).mt_rand(100000, 999999),
                default => 'SN '.Str::upper(Str::random(2)).mt_rand(10000000, 99999999),
            };

            $g = $guarantors[$i % count($guarantors)];
            $plan = $service->create($branch, $seller, $cust['customer'], $items, [
                'markup_percent' => $markup,
                'down_payment' => $down,
                'payment_method_id' => $cash,
                'installments_count' => $count,
                'frequency' => 'monthly',
                'start_date' => $start->toDateString(),
                'first_due_date' => $firstDue->toDateString(),
                'customer_cnic' => $cust['cnic'],
                'guarantor_name' => $g,
                'guarantor_phone' => sprintf('03%02d-%07d', mt_rand(0, 49), mt_rand(1000000, 9999999)),
                'guarantor_cnic' => sprintf('352%02d-%07d-%d', mt_rand(1, 2), mt_rand(1000000, 9999999), mt_rand(1, 9)),
                'guarantor_address' => $cust['customer']->address,
                'notes' => $cust['job'].($isBike ? '. Original file & registration book kept at shop until last installment.' : '.'),
            ]);

            $this->repaymentHistory($service, $plan, $collector, $behaviour, $today, $cash, $bank);

            if ($behaviour === 'cancelled') {
                $plan->refresh();
                $service->cancel($plan, $owner);
                $plan->forceFill(['notes' => $plan->notes.' Customer returned the TV after 2 months (relocated to Karachi). Down payment adjusted as rent.'])->save();
            }
        }
    }

    private function repaymentHistory(InstallmentService $service, InstallmentPlan $plan, User $collector, string $behaviour, Carbon $today, int $cash, int $bank): void
    {
        $schedules = $plan->schedules()->get();
        $pastDue = $schedules->filter(fn ($s) => $s->due_date->lte($today))->values();

        if ($behaviour === 'completed') {
            // Paid everything — the last few together to close the account early.
            foreach ($schedules as $idx => $s) {
                $date = $s->due_date->lte($today) ? $s->due_date->clone()->addDays(mt_rand(-2, 3)) : $today->clone()->subDays(mt_rand(3, 20));
                $date = $date->greaterThan($today) ? $today->clone() : $date;
                $plan->refresh();
                $service->collect($plan, $collector, (float) $s->amount, $idx % 3 === 2 ? $bank : $cash, $date->toDateString(), $idx % 3 === 2 ? 'IBFT-'.mt_rand(100000, 999999) : null);
            }

            return;
        }

        if ($behaviour === 'new') {
            return;
        }

        $skip = match ($behaviour) {
            'late' => mt_rand(1, 3),
            'cancelled' => max(0, $pastDue->count() - 2),
            default => 0,
        };
        $payable = $pastDue->slice(0, max(0, $pastDue->count() - $skip));

        foreach ($payable as $idx => $s) {
            $date = $s->due_date->clone()->addDays(mt_rand(-3, $behaviour === 'late' ? 12 : 4));
            if ($date->greaterThan($today)) {
                $date = $today->clone();
            }
            $amount = (float) $s->amount;
            if ($behaviour === 'partial' && $idx === $payable->count() - 1) {
                $amount = round($amount * 0.5 / 100) * 100;
            }
            $plan->refresh();
            $service->collect($plan, $collector, $amount, mt_rand(1, 100) <= 80 ? $cash : $bank, $date->toDateString(), null, $behaviour === 'late' && $idx === $payable->count() - 1 ? 'Promised to clear arrears after salary.' : null);
        }

        // A good payer occasionally pays the next one early.
        if ($behaviour === 'good' && mt_rand(1, 100) <= 25) {
            $next = $schedules->first(fn ($s) => $s->due_date->greaterThan($today));
            if ($next) {
                $plan->refresh();
                $service->collect($plan, $collector, (float) $next->amount, $cash, $today->clone()->subDays(mt_rand(0, 3))->toDateString(), null, 'Paid in advance.');
            }
        }
    }

    /**
     * Walk-in cash sales of smaller items (fans, UPS, kitchen appliances).
     *
     * @param  array<string, User>  $staff
     * @param  list<array{customer: Customer, cnic: string, job: string}>  $customers
     * @param  array<string, Product>  $p
     */
    private function cashSales(SaleService $service, array $staff, User $owner, Branch $main, Branch $township, array $customers, array $p): void
    {
        $cash = PaymentMethod::query()->where('slug', 'cash')->value('id');
        $card = PaymentMethod::query()->where('slug', 'card')->value('id');
        $skus = ['FU-PF-56', 'FU-PF-56', 'FU-PF-56', 'FU-HM-1000', 'KT-KW-JB', 'KT-DW-MW', 'WM-SA-TT'];

        for ($daysAgo = 40; $daysAgo >= 0; $daysAgo -= mt_rand(1, 3)) {
            $atTownship = mt_rand(1, 100) <= 35;
            $branch = $atTownship ? $township : $main;
            $sku = $skus[mt_rand(0, count($skus) - 1)];
            if ($atTownship && ! in_array($sku, ['FU-PF-56', 'FU-HM-1000', 'WM-SA-TT'], true)) {
                $sku = 'FU-PF-56';
            }
            $qty = $sku === 'FU-PF-56' ? mt_rand(1, 3) : 1;
            $items = [['product_id' => $p[$sku]->id, 'quantity' => $qty]];
            $total = (float) $p[$sku]->sale_price * $qty;
            $payments = [mt_rand(1, 100) <= 75 ? ['payment_method_id' => $cash, 'amount' => $total] : ['payment_method_id' => $card, 'amount' => $total, 'reference' => 'POS-'.mt_rand(1000, 9999)]];
            $at = Carbon::parse(now('Asia/Karachi')->subDays($daysAgo)->toDateString().sprintf(' %02d:%02d:00', mt_rand(11, 20), mt_rand(0, 59)), 'Asia/Karachi');
            if ($at->isFuture()) {
                $at = now('Asia/Karachi')->subMinutes(mt_rand(10, 90));
            }

            $service->create(
                $branch,
                $atTownship ? $staff['township'] : $owner,
                mt_rand(1, 100) <= 40 ? $customers[mt_rand(0, count($customers) - 1)]['customer'] : null,
                $items,
                $payments,
                (string) Str::uuid(),
                ['items' => $items, 'payments' => $payments],
                null,
                $at->clone()->utc(),
            );
        }
    }

    private function expenses(Business $business, User $owner, Branch $main, Branch $township): void
    {
        $rows = [];
        foreach ([now('Asia/Karachi')->subMonthNoOverflow()->startOfMonth(), now('Asia/Karachi')->startOfMonth()] as $m) {
            $rows[] = [$main, 'Showroom rent — Ichhra', 'Rent', 150000, $m->clone()];
            $rows[] = [$township, 'Shop rent — Township', 'Rent', 85000, $m->clone()];
            $rows[] = [$main, 'Staff salaries (salesmen, recovery officer, guard)', 'Salaries', 185000, $m->clone()->addDays(1)];
            $rows[] = [$township, 'Staff salaries', 'Salaries', 70000, $m->clone()->addDays(1)];
            $rows[] = [$main, 'LESCO electricity bill', 'Utilities', 42000, $m->clone()->addDays(8)];
            $rows[] = [$township, 'LESCO electricity bill', 'Utilities', 18500, $m->clone()->addDays(8)];
            $rows[] = [$main, 'Recovery officer bike petrol', 'Transport', 12000, $m->clone()->addDays(2)];
            $rows[] = [$main, 'NADRA Verisys — customer & guarantor CNIC checks', 'Other', 4500, $m->clone()->addDays(2)];
            $rows[] = [$main, 'Facebook & OLX ads — "Easy Qist" campaign', 'Marketing', 20000, $m->clone()];
            $rows[] = [$main, 'Delivery loader rickshaw (TV/fridge delivery)', 'Transport', 9000, $m->clone()->addDays(1)];
            $rows[] = [$main, 'Jazz & PTCL bills', 'Utilities', 6500, $m->clone()->addDays(2)];
        }
        $rows[] = [$main, 'Stamp papers for installment agreements', 'Supplies', 3000, now('Asia/Karachi')->subDays(6)];
        $rows[] = [$main, 'Chai & refreshments for customers', 'Other', 2500, now('Asia/Karachi')->subDays(2)];

        foreach ($rows as [$branch, $title, $category, $amount, $date]) {
            if ($date->isFuture()) {
                continue;
            }
            $e = new Expense(['title' => $title, 'category' => $category, 'amount' => $amount, 'expense_date' => $date->toDateString()]);
            $e->business_id = $business->id;
            $e->branch_id = $branch->id;
            $e->user_id = $owner->id;
            $e->save();
        }
    }
}
