<?php

/*
|--------------------------------------------------------------------------
| Business type presets
|--------------------------------------------------------------------------
|
| Each business type carries three JSON blobs that turn the one Universal
| POS codebase into software that "feels" built for that trade:
|
|  - theme        colours, sidebar style and the branded login page copy
|  - terminology  what this trade calls a customer/product/sale/... (a
|                 dental clinic has "Patients" and "Treatments", a
|                 restaurant has "Guests" and a "Menu")
|  - modules      which standard screens to hide and which optional
|                 modules (e.g. installments) to switch on
|
| Everything here is only a DEFAULT: the Super Admin edits each type from
| Admin → Business Types and the database row wins. BusinessType::theme(),
| terms() and modules() merge the row over `defaults` below so a partially
| filled type never shows a blank label.
|
*/

return [

    'defaults' => [
        'theme' => [
            'primary_color' => '#00bf63',
            'sidebar_style' => 'light',
            'login_headline' => 'Run your business from one place',
            'login_tagline' => 'Sales, stock, customers and reports — all in one fast, simple POS.',
            'login_highlights' => [
                'Lightning-fast checkout',
                'Live stock across branches',
                'Reports that make sense',
            ],
            'login_image' => null,
        ],
        'terminology' => [
            'customer' => 'Customer',
            'customers' => 'Customers',
            'product' => 'Product',
            'products' => 'Products',
            'sale' => 'Sale',
            'sales' => 'Sales',
            'supplier' => 'Supplier',
            'suppliers' => 'Suppliers',
            'purchase' => 'Purchase',
            'purchases' => 'Purchases',
            'category' => 'Category',
            'categories' => 'Categories',
            'inventory' => 'Inventory',
            'pos' => 'POS',
        ],
        'modules' => [
            'hidden' => [],
            'enabled' => [],
        ],
    ],

    /*
     | Screens a type may hide from its users' sidebar (they still exist,
     | they're just not relevant to e.g. a pure services business).
     */
    'hideable_modules' => [
        'inventory' => 'Inventory',
        'purchases' => 'Purchases',
        'suppliers' => 'Suppliers',
        'returns' => 'Returns',
        'expenses' => 'Expenses',
    ],

    /*
     | Optional modules a type may switch on.
     */
    'optional_modules' => [
        'installments' => 'Installments (sell on easy monthly / weekly plans)',
    ],

    'sidebar_styles' => [
        'light' => 'Light',
        'dark' => 'Dark',
        'brand' => 'Brand colour',
    ],

    /*
     | Per-type presets — applied by BusinessTypeSeeder and back-filled onto
     | existing rows by the 2026_11_04 migration (only where empty, never
     | over something the Super Admin already set).
     */
    'presets' => [
        'grocery' => [
            'name' => 'Grocery',
            'icon' => 'shopping-basket',
            'description' => 'Supermarkets, kiryana and convenience stores.',
            'theme' => [
                'primary_color' => '#16a34a',
                'sidebar_style' => 'light',
                'login_headline' => 'Fresh stock. Fast checkout.',
                'login_tagline' => 'Barcode billing, daily stock and supplier purchases for your store.',
                'login_highlights' => ['Barcode-ready billing counter', 'Low-stock alerts before shelves run empty', 'Supplier purchases & credit'],
            ],
            'terminology' => ['pos' => 'Billing Counter', 'products' => 'Items', 'product' => 'Item'],
        ],
        'restaurant' => [
            'name' => 'Restaurant',
            'icon' => 'utensils-crossed',
            'description' => 'Restaurants, cafés, fast food and bakeries.',
            'theme' => [
                'primary_color' => '#ea580c',
                'sidebar_style' => 'dark',
                'login_headline' => 'Every order, served right.',
                'login_tagline' => 'Take orders, manage your menu and see what sells — from kitchen to counter.',
                'login_highlights' => ['Quick order taking', 'Menu sections & pricing', 'Daily sales & best sellers'],
            ],
            'terminology' => [
                'customer' => 'Guest', 'customers' => 'Guests',
                'product' => 'Menu Item', 'products' => 'Menu',
                'sale' => 'Order', 'sales' => 'Orders',
                'supplier' => 'Vendor', 'suppliers' => 'Vendors',
                'category' => 'Menu Section', 'categories' => 'Menu Sections',
                'inventory' => 'Kitchen Stock',
                'pos' => 'Take Order',
            ],
        ],
        'pharmacy' => [
            'name' => 'Pharmacy',
            'icon' => 'pill',
            'description' => 'Pharmacies, medical stores and chemists.',
            'theme' => [
                'primary_color' => '#0d9488',
                'sidebar_style' => 'light',
                'login_headline' => 'Care at the counter.',
                'login_tagline' => 'Dispense medicines, track batches of stock and serve patients faster.',
                'login_highlights' => ['Fast medicine search', 'Stock & re-order alerts', 'Patient purchase history'],
            ],
            'terminology' => [
                'customer' => 'Patient', 'customers' => 'Patients',
                'product' => 'Medicine', 'products' => 'Medicines',
                'category' => 'Formula / Group', 'categories' => 'Formulas / Groups',
                'pos' => 'Dispensary',
            ],
        ],
        'electronics' => [
            'name' => 'Electronics',
            'icon' => 'smartphone',
            'description' => 'Mobile, computer and electronics shops.',
            'theme' => [
                'primary_color' => '#2563eb',
                'sidebar_style' => 'dark',
                'login_headline' => 'Tech sales, simplified.',
                'login_tagline' => 'Sell devices and accessories, manage brands and keep every unit counted.',
                'login_highlights' => ['Brands & models in one catalog', 'Stock by branch', 'Returns & warranty handling'],
            ],
            'terminology' => ['product' => 'Device', 'products' => 'Devices'],
        ],
        'clothing' => [
            'name' => 'Clothing',
            'icon' => 'shirt',
            'description' => 'Garments, boutiques and footwear.',
            'theme' => [
                'primary_color' => '#db2777',
                'sidebar_style' => 'light',
                'login_headline' => 'Style that sells itself.',
                'login_tagline' => 'Collections, sizes and seasonal stock — all managed from one boutique POS.',
                'login_highlights' => ['Collections & brands', 'Exchange-friendly returns', 'Best-selling articles'],
            ],
            'terminology' => [
                'product' => 'Article', 'products' => 'Articles',
                'category' => 'Collection', 'categories' => 'Collections',
            ],
        ],
        'salon' => [
            'name' => 'Salon',
            'icon' => 'scissors',
            'description' => 'Salons, spas and beauty parlours.',
            'theme' => [
                'primary_color' => '#9333ea',
                'sidebar_style' => 'brand',
                'login_headline' => 'Beauty, beautifully managed.',
                'login_tagline' => 'Bill services and retail products, and keep every client coming back.',
                'login_highlights' => ['Services & retail in one bill', 'Client visit history', 'Staff & daily earnings'],
            ],
            'terminology' => [
                'customer' => 'Client', 'customers' => 'Clients',
                'product' => 'Service', 'products' => 'Services',
                'sale' => 'Bill', 'sales' => 'Bills',
                'pos' => 'Front Desk',
            ],
            'modules' => ['hidden' => ['purchases']],
        ],
        'hardware' => [
            'name' => 'Hardware',
            'icon' => 'hammer',
            'description' => 'Hardware, sanitary, paint and building material stores.',
            'theme' => [
                'primary_color' => '#d97706',
                'sidebar_style' => 'dark',
                'login_headline' => 'Built for the busy counter.',
                'login_tagline' => 'Thousands of parts, bulk purchases and contractor accounts — handled.',
                'login_highlights' => ['Huge catalogs, fast search', 'Bulk supplier purchases', 'Contractor & credit customers'],
            ],
            'terminology' => ['product' => 'Item', 'products' => 'Items', 'pos' => 'Counter'],
        ],
        'services' => [
            'name' => 'Services',
            'icon' => 'briefcase',
            'description' => 'Service businesses that bill time or work, not stock.',
            'theme' => [
                'primary_color' => '#4f46e5',
                'sidebar_style' => 'light',
                'login_headline' => 'Bill your work, not paperwork.',
                'login_tagline' => 'Invoices, clients and earnings for service businesses.',
                'login_highlights' => ['Service catalog & rates', 'Client billing history', 'Expense tracking'],
            ],
            'terminology' => [
                'customer' => 'Client', 'customers' => 'Clients',
                'product' => 'Service', 'products' => 'Services',
                'sale' => 'Invoice', 'sales' => 'Invoices',
                'pos' => 'New Invoice',
            ],
            'modules' => ['hidden' => ['inventory', 'purchases', 'suppliers']],
        ],
        'wholesale' => [
            'name' => 'Wholesale',
            'icon' => 'warehouse',
            'description' => 'Wholesalers and distributors.',
            'theme' => [
                'primary_color' => '#1e40af',
                'sidebar_style' => 'dark',
                'login_headline' => 'Move volume, keep control.',
                'login_tagline' => 'Bulk orders, trade buyers and warehouse stock — at distributor speed.',
                'login_highlights' => ['Bulk order entry', 'Buyer accounts', 'Warehouse stock & purchases'],
            ],
            'terminology' => [
                'customer' => 'Buyer', 'customers' => 'Buyers',
                'sale' => 'Order', 'sales' => 'Orders',
                'pos' => 'Order Desk',
            ],
        ],
        'dental-clinic' => [
            'name' => 'Dental Clinic',
            'icon' => 'stethoscope',
            'description' => 'Dentists, clinics and medical practices.',
            'theme' => [
                'primary_color' => '#0284c7',
                'sidebar_style' => 'light',
                'login_headline' => 'Healthy smiles, smooth billing.',
                'login_tagline' => 'Bill treatments, keep patient history and track clinic earnings.',
                'login_highlights' => ['Treatment price list', 'Patient billing history', 'Clinic expenses & profit'],
            ],
            'terminology' => [
                'customer' => 'Patient', 'customers' => 'Patients',
                'product' => 'Treatment', 'products' => 'Treatments',
                'sale' => 'Bill', 'sales' => 'Bills',
                'supplier' => 'Vendor', 'suppliers' => 'Vendors',
                'category' => 'Department', 'categories' => 'Departments',
                'inventory' => 'Clinic Supplies',
                'pos' => 'Billing Desk',
            ],
        ],
        'installment' => [
            'name' => 'Installment Store',
            'icon' => 'calendar-clock',
            'description' => 'Shops that sell electronics, furniture, bikes etc. on easy installments.',
            'theme' => [
                'primary_color' => '#7c3aed',
                'sidebar_style' => 'dark',
                'login_headline' => 'Sell more on easy installments.',
                'login_tagline' => 'Create plans, collect monthly payments and never miss an overdue account.',
                'login_highlights' => ['Installment plans in seconds', 'Due & overdue collection list', 'Guarantor and payment history'],
            ],
            'terminology' => ['customer' => 'Customer', 'customers' => 'Customers', 'pos' => 'Cash Sale'],
            'modules' => ['enabled' => ['installments']],
        ],
        'other' => [
            'name' => 'Other',
            'icon' => 'store',
            'description' => 'Any other retail business.',
            'theme' => [],
            'terminology' => [],
        ],
    ],

];
