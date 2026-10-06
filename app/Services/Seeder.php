<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Reference data needed by a fresh install (safe to run more than once). */
final class Seeder
{
    public static function run(): array
    {
        $log = [];
        self::roles();
        $log[] = 'roles & permission matrix';
        self::lookups();
        $log[] = 'lookup lists';
        self::categories();
        $log[] = 'finance categories';
        self::currencies();
        $log[] = 'currencies (QAR base)';
        self::settings();
        $log[] = 'default settings';
        StudioTemplates::seed();
        $log[] = 'SK Arabian Studio templates';
        return $log;
    }

    public static function roles(bool $resetPerms = false): void
    {
        foreach (DefaultRoles::definitions() as $slug => $def) {
            $id = DB::value('SELECT id FROM roles WHERE slug = ?', [$slug]);
            if (!$id) {
                $id = DB::insert('roles', [
                    'slug' => $slug, 'name_en' => $def['name_en'], 'name_ar' => $def['name_ar'], 'is_system' => 1,
                    'require_2fa' => $def['require_2fa'], 'horse_scope' => $def['horse_scope'], 'edit_window' => $def['edit_window'],
                ]);
                $resetPermsFor = true;
            } else {
                $resetPermsFor = $resetPerms;
            }
            if ($resetPermsFor) {
                DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
                foreach ($def['perms'] as $module => $actions) {
                    foreach ($actions as $a) {
                        DB::run('INSERT IGNORE INTO role_permissions (role_id, module, action) VALUES (?, ?, ?)', [$id, $module, $a]);
                    }
                }
                DB::run('DELETE FROM studio_doc_permissions WHERE role_id = ?', [$id]);
                foreach (DefaultRoles::studioDocs()[$slug] ?? [] as $doc) {
                    DB::run('INSERT IGNORE INTO studio_doc_permissions (role_id, doc_type) VALUES (?, ?)', [$id, $doc]);
                }
            }
        }
    }

    public static function lookups(): void
    {
        $lists = [
            'breed' => [['Purebred Arabian', 'عربي أصيل'], ['Arabian (Part-bred)', 'عربي مهجن'], ['Thoroughbred', 'ثوروبريد'], ['Other', 'أخرى']],
            'color' => [['Grey', 'رمادي'], ['Bay', 'أحمر'], ['Chestnut', 'أشقر'], ['Black', 'أدهم'], ['Dark Bay', 'أحمر غامق'], ['White', 'أبيض'], ['Roan', 'أبرش']],
            'location' => [['Stable 1', 'إسطبل 1'], ['Stable 2', 'إسطبل 2'], ['Stable 3', 'إسطبل 3'], ['Clinic', 'العيادة'], ['Nitrogen Tank', 'خزان النيتروجين'], ['Shelter', 'المأوى'], ['Paddock', 'الحظيرة'], ['External', 'خارجي']],
            'position' => [['Stable Manager', 'مدير الإسطبل'], ['Groom', 'سائس'], ['Horse Trainer', 'مدرب خيل'], ['Veterinarian', 'طبيب بيطري'], ['Nutritionist', 'أخصائي تغذية'], ['Farrier', 'بيطار'], ['Driver', 'سائق'], ['Accountant', 'محاسب'], ['HR Officer', 'مسؤول موارد بشرية'], ['Administrator', 'إداري'], ['Security Guard', 'حارس أمن'], ['Cleaner', 'عامل نظافة'], ['General Manager', 'المدير العام']],
            'department' => [['Stable', 'الإسطبل'], ['Veterinary', 'البيطرة'], ['Training', 'التدريب'], ['Administration', 'الإدارة'], ['Finance', 'المالية'], ['Support Services', 'الخدمات المساندة']],
            'nationality' => [['Qatar', 'قطر'], ['Saudi Arabia', 'السعودية'], ['Egypt', 'مصر'], ['Sudan', 'السودان'], ['Syria', 'سوريا'], ['Jordan', 'الأردن'], ['India', 'الهند'], ['Pakistan', 'باكستان'], ['Bangladesh', 'بنغلاديش'], ['Nepal', 'نيبال'], ['Philippines', 'الفلبين'], ['Sri Lanka', 'سريلانكا'], ['United Kingdom', 'المملكة المتحدة'], ['Other', 'أخرى']],
            'payment_method' => [['Cash', 'نقداً'], ['Bank Transfer', 'تحويل بنكي'], ['Cheque', 'شيك'], ['Credit Card', 'بطاقة ائتمان'], ['Debit Card', 'بطاقة خصم']],
            'embryo_grade' => [['Grade 1', 'الدرجة 1'], ['Grade 2', 'الدرجة 2'], ['Grade 3', 'الدرجة 3'], ['Grade 4', 'الدرجة 4']],
            'embryo_stage' => [['Morula', 'التوتة'], ['Early Blastocyst', 'كيسة أريمية مبكرة'], ['Blastocyst', 'كيسة أريمية'], ['Expanded Blastocyst', 'كيسة أريمية متمددة']],
            'unit' => [['pcs', 'قطعة'], ['kg', 'كغ'], ['bag', 'كيس'], ['bale', 'بالة'], ['litre', 'لتر'], ['ml', 'مل'], ['box', 'علبة'], ['bottle', 'زجاجة'], ['dose', 'جرعة'], ['tube', 'أنبوب']],
        ];
        foreach ($lists as $type => $items) {
            foreach ($items as $i => [$en, $ar]) {
                DB::run('INSERT IGNORE INTO lookups (type, value_en, value_ar, sort) VALUES (?, ?, ?, ?)', [$type, $en, $ar, $i]);
            }
        }
    }

    public static function categories(): void
    {
        // Spell-checked list (fixes "Employees Expenese", etc. from the old system)
        $cats = [
            ['expense', 'Feed & Food', 'الأعلاف والغذاء', ['Hay', 'Grain', 'Supplements']],
            ['expense', 'Medical/Vet', 'الطبية/البيطرية', ['Vet Visit', 'Medicines', 'Vaccinations', 'Embryo Flush', 'Embryo Transfer']],
            ['expense', 'Farrier', 'البيطار', []],
            ['expense', 'Equestrian Club', 'النادي', []],
            ['expense', 'Equipment', 'المعدات', []],
            ['expense', 'Sawdust/Bedding', 'نشارة/فرشة', []],
            ['expense', 'Gas', 'الوقود', []],
            ['expense', 'Utilities', 'المرافق', ['Electricity', 'Water', 'Internet & Phone']],
            ['expense', 'Employee Expenses', 'مصاريف الموظفين', ['Accommodation', 'Tickets', 'Visa & Residency']],
            ['expense', 'Transport', 'النقل', []],
            ['expense', 'Show Fees', 'رسوم البطولات', []],
            ['expense', 'Salaries', 'الرواتب', []],
            ['expense', 'Rent', 'الإيجار', []],
            ['expense', 'Government Fees', 'الرسوم الحكومية', []],
            ['any', 'Other', 'أخرى', []],
            ['income', 'Boarding', 'الإيواء', []],
            ['income', 'Prize Money', 'جوائز البطولات', []],
            ['liability', 'Loans', 'القروض', []],
        ];
        foreach ($cats as $i => [$type, $en, $ar, $subs]) {
            $id = DB::value('SELECT id FROM finance_categories WHERE parent_id IS NULL AND name_en = ?', [$en]);
            if (!$id) {
                $id = DB::insert('finance_categories', ['type' => $type, 'name_en' => $en, 'name_ar' => $ar, 'sort' => $i]);
            }
            foreach ($subs as $j => $sub) {
                DB::run('INSERT IGNORE INTO finance_categories (parent_id, type, name_en, sort) VALUES (?, ?, ?, ?)', [$id, $type, $sub, $j]);
            }
        }
    }

    /**
     * Correct reference rates, "1 unit = X QAR". Pegged currencies are exact (QAR is pegged at 3.64 per USD).
     * Floating currencies are refreshed daily by cron when auto-update is on.
     */
    public const RATES = [
        'QAR' => ['Qatari Riyal', 'ريال قطري', 1.0],
        'USD' => ['US Dollar', 'دولار أمريكي', 3.64],
        'SAR' => ['Saudi Riyal', 'ريال سعودي', 0.970667],     // 3.64 / 3.75
        'AED' => ['UAE Dirham', 'درهم إماراتي', 0.991150],     // 3.64 / 3.6725
        'BHD' => ['Bahraini Dinar', 'دينار بحريني', 9.680851], // 3.64 / 0.376
        'OMR' => ['Omani Rial', 'ريال عماني', 9.466840],       // 3.64 / 0.3845
        'JOD' => ['Jordanian Dinar', 'دينار أردني', 5.133992], // 3.64 / 0.709
        'KWD' => ['Kuwaiti Dinar', 'دينار كويتي', 11.86],      // basket; auto-updated
        'EUR' => ['Euro', 'يورو', 4.23],                         // floating; auto-updated
        'GBP' => ['British Pound', 'جنيه إسترليني', 4.89],       // floating; auto-updated
        'EGP' => ['Egyptian Pound', 'جنيه مصري', 0.0750],        // floating; auto-updated
    ];

    public static function currencies(): void
    {
        foreach (self::RATES as $code => [$en, $ar, $rate]) {
            $exists = DB::value('SELECT code FROM currencies WHERE code = ?', [$code]);
            if (!$exists) {
                DB::insert('currencies', [
                    'code' => $code, 'name_en' => $en, 'name_ar' => $ar, 'rate_to_qar' => $rate,
                    'auto_update' => in_array($code, ['QAR', 'USD', 'SAR', 'AED', 'BHD', 'OMR', 'JOD'], true) ? 0 : 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                DB::insert('currency_rate_history', ['code' => $code, 'rate_to_qar' => $rate, 'source' => 'seed']);
            }
        }
    }

    public static function settings(): void
    {
        $defaults = [
            'approval.bill_limit_qar'        => '5000',
            'company.name_en'                => 'SK Arabians',
            'company.name_ar'                => 'اس كي ارابيان للتجارة',
            'company.cr'                     => '231961',
            'company.mobile'                 => '5536 6699',
            'company.phone_intl'             => '+97455366699',
            'company.whatsapp'               => '+97455366699',
            'company.email'                  => 'sk.qa@hotmail.com',
            'company.po_box'                 => '6657',
            'company.city_en'                => 'Doha - Qatar',
            'company.city_ar'                => 'الدوحة - قطر',
            'company.address_en'             => 'Doha, Qatar',
            'company.address_ar'             => 'الدوحة، قطر',
            'company.map_query'              => 'Doha, Qatar',
            'company.instagram'              => '',
            'company.x'                      => '',
            'company.youtube'                => '',
            'company.facebook'               => '',
            'company.tiktok'                 => '',
            'site.hero_title_en'             => 'Breeding Excellence in Arabian Horses',
            'site.hero_title_ar'             => 'التميّز في إكثار الخيل العربية',
            'site.hero_sub_en'               => 'A purebred Arabian stud in Qatar, established 2025.',
            'site.hero_sub_ar'               => 'مربط للخيل العربية الأصيلة في قطر، تأسس عام 2025.',
            'site.hero_video_url'            => '',
            'site.featured_horse_id'         => '',
            'site.established'               => '2025',
            'site.story_en'                  => 'SK Arabians was founded in Qatar in 2025 with one purpose: to breed purebred Arabian horses of exceptional type, movement and character, and to present them with pride in the show ring.',
            'site.story_ar'                  => 'تأسس مربط اس كي ارابيان في قطر عام 2025 بهدف واحد: إكثار خيل عربية أصيلة بجمال وحركة وشخصية استثنائية، وتقديمها بفخر في حلبات البطولات.',
            'site.pillar1_title_en'          => 'Bloodline Integrity',
            'site.pillar1_title_ar'          => 'نقاء السلالة',
            'site.pillar1_text_en'           => 'Every pairing is planned around proven, documented bloodlines.',
            'site.pillar1_text_ar'           => 'كل تزاوج مدروس على أساس سلالات موثقة ومجربة.',
            'site.pillar2_title_en'          => 'Uncompromised Equine Care',
            'site.pillar2_title_ar'          => 'رعاية بلا تنازلات',
            'site.pillar2_text_en'           => 'Veterinary, nutrition and daily care by dedicated specialists.',
            'site.pillar2_text_ar'           => 'رعاية بيطرية وتغذية ورعاية يومية على يد مختصين.',
            'site.pillar3_title_en'          => 'Competitive Distinction',
            'site.pillar3_title_ar'          => 'التميّز في المنافسات',
            'site.pillar3_text_en'           => 'Prepared and presented to win at GCAT and international shows.',
            'site.pillar3_text_ar'           => 'إعداد وتقديم للفوز في بطولات كأس الخليج والبطولات الدولية.',
            'site.instagram_embed'           => '',
            'site.sections'                  => 'featured,horses,achievements,foals,bloodlines,breeding,gallery,story,experts,contact',
            'notify.daily_summary_email'     => '1',
            'notify.daily_summary_whatsapp'  => '0',
            'security.business_hours'        => '06:00-22:00',
            'security.alert_new_device'      => '1',
        ];
        foreach ($defaults as $k => $v) {
            DB::run('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)', [$k, $v]);
        }
    }
}
