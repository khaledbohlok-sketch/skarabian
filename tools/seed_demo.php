<?php
/**
 * DEMO DATA for local testing and the staging preview only. NEVER run on the live system.
 *   php tools/seed_demo.php --yes
 * Creates sample horses, shows, embryos, employees, suppliers, inventory, bills and one user per role.
 * All demo users get the password printed at the end; the Owner demo account has an authenticator secret printed too.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Crypto;
use App\Core\DB;
use App\Services\Passwords;
use App\Services\Sequence;
use App\Services\Settings;

if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "This inserts DEMO data. Run with --yes on a local or staging database only.\n");
    exit(1);
}
if (DB::value('SELECT COUNT(*) FROM horses') > 0) {
    fwrite(STDERR, "Horses already exist — demo data not loaded.\n");
    exit(1);
}

$lk = fn (string $type, string $en) => (int) DB::value('SELECT id FROM lookups WHERE type = ? AND value_en = ?', [$type, $en]);
$cat = fn (string $en) => (int) DB::value('SELECT id FROM finance_categories WHERE parent_id IS NULL AND name_en = ?', [$en]);

DB::transaction(function () use ($lk, $cat) {
    // ---- parties
    $parties = [];
    foreach ([['supplier', 'Al Bidda', 'البدع'], ['supplier', 'Al Maydan', 'الميدان'], ['supplier', 'EVMC', 'EVMC'], ['supplier', 'First Vet For Veterinary Services W.L.L', 'فيرست فيت للخدمات البيطرية ذ.م.م'],
              ['supplier', 'Qatar Racing And Equestrian Club', 'نادي قطر للسباق والفروسية'], ['supplier', 'Vet Zone', 'فت زون'], ['supplier', 'Maraba Al Asayel', 'مرابع الأصايل'],
              ['supplier', 'Buzwair Industrial Gases Factories', 'مصانع بوزوير للغازات الصناعية'], ['supplier', 'Ooredoo', 'Ooredoo'], ['client', 'Al Rayyan Stud', 'مربط الريان']] as [$type, $en, $ar]) {
        $parties[$en] = DB::insert('parties', ['type' => $type, 'name_en' => $en, 'name_ar' => $ar, 'phone' => '+97444' . random_int(100000, 999999)]);
    }

    // ---- horses
    $grey = $lk('color', 'Grey'); $bay = $lk('color', 'Bay'); $chest = $lk('color', 'Chestnut');
    $pa = $lk('breed', 'Purebred Arabian'); $s1 = $lk('location', 'Stable 1'); $s2 = $lk('location', 'Stable 2'); $s3 = $lk('location', 'Stable 3');
    $h = function (array $d) use ($pa) {
        $d += ['breed_id' => $pa, 'status' => 'active', 'owner_type' => 'sk', 'show_on_website' => 1];
        $d['slug'] = slugify($d['name_en']);
        $d['category'] = App\Services\HorseRules::category($d['sex'], $d['dob'] ?? null);
        return DB::insert('horses', $d);
    };
    $ext = fn (string $n, string $sex) => DB::insert('horses', ['name_en' => $n, 'slug' => slugify($n), 'sex' => $sex, 'category' => $sex === 'male' ? 'stallion' : 'mare', 'is_external' => 1, 'show_on_website' => 0, 'breed_id' => $pa]);
    $dominic = $ext('DOMINIC M (US)', 'male'); $majalina = $ext('MAJALINA (US)', 'female');
    $mn = $ext('MARWAN AL SHAQAB', 'male'); $sh = $ext('SHAHRAZADA', 'female');
    $tumooh = $h(['name_en' => 'D TUMOOH', 'name_ar' => 'د طموح', 'sex' => 'male', 'dob' => '2016-03-14', 'color_id' => $grey, 'sire_id' => $mn, 'dam_id' => $sh, 'location_id' => $s1, 'breeding_stallion' => 1, 'is_favorite' => 1, 'bloodline' => 'Marwan Al Shaqab',
        'story_en' => "D Tumooh is the cornerstone stallion of SK Arabians: correct, elegant and with a remarkable presence in the ring. His foals carry his type, neck and movement.", 'story_ar' => 'د طموح هو الفحل الأساسي في اس كي ارابيان: صحيح البنية وأنيق وذو حضور لافت في الحلبة، ومهوره تحمل نوعه وجمال رقبته وحركته.']);
    $samed = $h(['name_en' => 'D SAMED', 'name_ar' => 'د صامد', 'sex' => 'male', 'dob' => '2019-02-02', 'color_id' => $bay, 'sire_id' => $mn, 'location_id' => $s1, 'breeding_stallion' => 1]);
    $meera = $h(['name_en' => 'MEERA AL MASHRAB', 'name_ar' => 'ميرا المشرب', 'sex' => 'female', 'dob' => '2021-01-29', 'color_id' => $grey, 'sire_id' => $dominic, 'dam_id' => $majalina, 'location_id' => $s2, 'is_favorite' => 1,
        'story_en' => 'A beautiful grey mare by Dominic M (US) out of Majalina (US), multiple medal winner on the GCAT circuit.', 'story_ar' => 'فرس رمادية جميلة من دومينيك إم (أمريكا) وماجالينا (أمريكا)، حاصلة على عدة ميداليات في بطولات كأس الخليج.']);
    $razneh = $h(['name_en' => 'AJ RAZNEH', 'name_ar' => 'إي جي رزنة', 'sex' => 'female', 'dob' => '2015-04-20', 'color_id' => $grey, 'location_id' => $s2]);
    $shammah = $h(['name_en' => 'SG SHAMMAH', 'name_ar' => 'إس جي شمّة', 'sex' => 'female', 'dob' => '2014-05-11', 'color_id' => $chest, 'location_id' => $s2]);
    $bdoor = $h(['name_en' => 'BDOOR AL BAHIYA', 'name_ar' => 'بدور البهية', 'sex' => 'female', 'dob' => '2017-03-03', 'color_id' => $bay, 'location_id' => $s3]);
    $f1 = $h(['name_en' => 'SK NOOR', 'name_ar' => 'اس كي نور', 'sex' => 'female', 'dob' => date('Y-m-d', strtotime('-5 months')), 'color_id' => $grey, 'sire_id' => $tumooh, 'dam_id' => $razneh, 'location_id' => $s3, 'born_at_sk' => 1, 'website_approved_at' => date('Y-m-d H:i:s')]);
    $f2 = $h(['name_en' => 'SK FARIS', 'name_ar' => 'اس كي فارس', 'sex' => 'male', 'dob' => date('Y-m-d', strtotime('-3 months')), 'color_id' => $bay, 'sire_id' => $tumooh, 'dam_id' => $shammah, 'location_id' => $s3, 'born_at_sk' => 1, 'website_approved_at' => date('Y-m-d H:i:s')]);
    $c1 = $h(['name_en' => 'SK SAQR', 'name_ar' => 'اس كي صقر', 'sex' => 'male', 'dob' => '2024-02-10', 'color_id' => $grey, 'sire_id' => $tumooh, 'dam_id' => $bdoor, 'location_id' => $s1, 'born_at_sk' => 0]);
    $fi1 = $h(['name_en' => 'SK LULWA', 'name_ar' => 'اس كي لولوة', 'sex' => 'female', 'dob' => '2023-03-22', 'color_id' => $grey, 'sire_id' => $samed, 'dam_id' => $shammah, 'location_id' => $s2]);
    Settings::set('site.featured_horse_id', (string) $meera);
    foreach ([[$tumooh, 'purchase'], [$meera, 'purchase'], [$f1, 'birth'], [$f2, 'birth']] as [$hid, $ev]) {
        DB::insert('ownership_history', ['horse_id' => $hid, 'event_type' => $ev, 'event_date' => date('Y-m-d', strtotime('-1 year'))]);
    }

    // ---- shows & results
    $shows = [];
    foreach ([['GCAT Muscat', 'كأس الخليج — مسقط', 'Muscat', 'Oman', '2025-11-14'], ['GCAT Ajman', 'كأس الخليج — عجمان', 'Ajman', 'UAE', '2026-01-23'], ['Qatar National Show', 'بطولة قطر الوطنية', 'Doha', 'Qatar', '2026-03-05']] as [$en, $ar, $city, $country, $date]) {
        $shows[] = DB::insert('shows', ['name_en' => $en, 'name_ar' => $ar, 'organizer' => str_starts_with($en, 'GCAT') ? 'GCAT' : 'QREC', 'city' => $city, 'country' => $country, 'start_date' => $date]);
    }
    $res = [[$shows[0], $meera, 'Senior Mares', 1, 'Gold Champion Senior Mare', 'البطلة الذهبية للأفراس الكبار', 'gold'],
            [$shows[1], $meera, 'Senior Mares', 2, 'Silver Champion Senior Mare', 'البطلة الفضية للأفراس الكبار', 'silver'],
            [$shows[1], $tumooh, 'Senior Stallions', 1, 'Gold Champion Senior Stallion', 'البطل الذهبي للفحول الكبار', 'gold'],
            [$shows[2], $fi1, 'Junior Fillies', 3, 'Bronze Champion Junior Filly', 'البطلة البرونزية للمهرات', 'bronze'],
            [$shows[2], $c1, 'Yearling Colts', 2, null, null, 'none']];
    foreach ($res as [$sid, $hid, $cls, $pl, $ten, $tar, $medal]) {
        DB::insert('show_results', ['show_id' => $sid, 'horse_id' => $hid, 'class_name' => $cls, 'placing' => $pl, 'title_en' => $ten, 'title_ar' => $tar, 'medal' => $medal]);
    }

    // ---- breeding & embryos
    $loc = $lk('location', 'Nitrogen Tank');
    $e1 = DB::insert('embryos', ['code' => Sequence::embryo('2026-02-01'), 'donor_mare_id' => $razneh, 'sire_id' => $tumooh, 'flush_date' => '2026-02-01', 'grade' => 'Grade 1', 'stage' => 'Blastocyst', 'status' => 'pregnant', 'recipient_mare_id' => $bdoor, 'transfer_date' => '2026-02-01', 'expected_foaling_date' => '2027-01-07']);
    DB::insert('embryos', ['code' => Sequence::embryo('2026-03-01'), 'donor_mare_id' => $shammah, 'sire_id' => $samed, 'flush_date' => '2026-03-10', 'grade' => 'Grade 2', 'stage' => 'Morula', 'status' => 'frozen', 'location_id' => $loc]);
    $br = DB::insert('breeding_records', ['mare_id' => $bdoor, 'stallion_id' => $tumooh, 'embryo_id' => $e1, 'method' => 'embryo_transfer', 'start_date' => '2026-02-01', 'expected_foaling_date' => '2027-01-07', 'status' => 'pregnant']);
    DB::insert('pregnancy_checks', ['breeding_record_id' => $br, 'check_date' => '2026-02-16', 'result' => 'positive', 'days_pregnant' => 15]);
    DB::insert('breeding_records', ['mare_id' => $meera, 'stallion_id' => $tumooh, 'method' => 'natural', 'start_date' => date('Y-m-d', strtotime('-310 days')), 'expected_foaling_date' => date('Y-m-d', strtotime('+30 days')), 'status' => 'pregnant']);

    // ---- employees
    $emp = function (array $d) use ($lk) {
        $d += ['leave_balance' => 21];
        foreach (['qid_no', 'passport_no', 'bank_iban', 'bank_name'] as $k) {
            if (isset($d[$k])) { $d[$k] = Crypto::encrypt($d[$k]); }
        }
        return DB::insert('employees', $d);
    };
    $lb = $lk('nationality', 'Other'); $eg = $lk('nationality', 'Egypt'); $in = $lk('nationality', 'India'); $pk = $lk('nationality', 'Pakistan'); $uk = $lk('nationality', 'United Kingdom');
    $e = [];
    $e['rafeek'] = $emp(['emp_no' => 'SK-001', 'name_en' => 'Rafeek Hussein Bohlok', 'name_ar' => 'رفيق حسين بحلق', 'nationality_id' => $lb, 'position_id' => $lk('position', 'Administrator'), 'department_id' => $lk('department', 'Administration'), 'phone' => '+97455000001', 'hire_date' => '2026-02-01', 'basic_salary_qar' => 7000, 'qid_no' => '30542200506', 'qid_expiry' => '2027-09-08', 'passport_expiry' => '2029-01-01']);
    $e['trainer'] = $emp(['emp_no' => 'SK-002', 'name_en' => 'Fabricio Duarte', 'name_ar' => 'فابريسيو دوارتي', 'nationality_id' => $uk, 'position_id' => $lk('position', 'Horse Trainer'), 'department_id' => $lk('department', 'Training'), 'hire_date' => '2025-05-01', 'basic_salary_qar' => 12800, 'qid_expiry' => date('Y-m-d', strtotime('+40 days')), 'show_on_website' => 1, 'public_title_en' => 'Head Trainer', 'public_title_ar' => 'المدرب الرئيسي', 'specialties_en' => 'Show preparation · Handling', 'specialties_ar' => 'إعداد البطولات · التعامل مع الخيل']);
    $e['vet'] = $emp(['emp_no' => 'SK-003', 'name_en' => 'Dr. Ahmed Samir', 'name_ar' => 'د. أحمد سمير', 'nationality_id' => $eg, 'position_id' => $lk('position', 'Veterinarian'), 'department_id' => $lk('department', 'Veterinary'), 'hire_date' => '2025-03-01', 'basic_salary_qar' => 11000, 'qid_expiry' => date('Y-m-d', strtotime('-12 days')), 'show_on_website' => 1, 'public_title_en' => 'Stud Veterinarian', 'public_title_ar' => 'طبيب المربط البيطري', 'specialties_en' => 'Reproduction · Embryo transfer', 'specialties_ar' => 'التناسل · نقل الأجنة']);
    $e['nutri'] = $emp(['emp_no' => 'SK-004', 'name_en' => 'Jennifer Hale', 'name_ar' => 'جينيفر هيل', 'gender' => 'f', 'nationality_id' => $uk, 'position_id' => $lk('position', 'Nutritionist'), 'department_id' => $lk('department', 'Veterinary'), 'hire_date' => '2025-06-01', 'basic_salary_qar' => 9000, 'qid_expiry' => '2027-03-01', 'show_on_website' => 1, 'public_title_en' => 'Equine Nutritionist', 'public_title_ar' => 'أخصائية تغذية الخيل', 'specialties_en' => 'Diet plans · Growth', 'specialties_ar' => 'خطط التغذية · النمو']);
    foreach (['Mohammed Rafiq' => $pk, 'Suresh Kumar' => $in, 'Imran Ali' => $pk, 'Bahaa Saleh' => $eg] as $n => $nat) {
        $e[$n] = $emp(['emp_no' => App\Services\HrService::nextEmpNo(), 'name_en' => $n, 'nationality_id' => $nat, 'position_id' => $lk('position', 'Groom'), 'department_id' => $lk('department', 'Stable'), 'hire_date' => '2025-09-01', 'basic_salary_qar' => 1500, 'housing_allowance' => 300, 'qid_expiry' => $n === 'Imran Ali' ? date('Y-m-d', strtotime('-40 days')) : date('Y-m-d', strtotime('+200 days'))]);
    }
    foreach ([$tumooh, $samed, $meera] as $hid) { DB::insert('horse_assignments', ['horse_id' => $hid, 'employee_id' => $e['Mohammed Rafiq']]); }

    // ---- accounts
    $cash = DB::insert('accounts', ['name' => 'Cash', 'type' => 'cash', 'opening_balance_qar' => 20000, 'opening_date' => '2026-01-01']);
    $bank = DB::insert('accounts', ['name' => 'QNB Current', 'type' => 'bank', 'bank_name' => 'QNB', 'account_no' => Crypto::encrypt('0012345678'), 'opening_balance_qar' => 350000, 'opening_date' => '2026-01-01']);
    DB::insert('accounts', ['name' => 'Petty cash', 'type' => 'petty_cash', 'opening_balance_qar' => 3000, 'opening_date' => '2026-01-01']);

    // ---- inventory
    $inv = [];
    foreach ([['Clinic', 'العيادة', 'clinic'], ['Cosmetics', 'مستحضرات العناية', 'cosmetics'], ['Equipment', 'المعدات', 'equipment'], ['Feed', 'الأعلاف', 'feed'], ['Shaving & Grooming', 'النشارة والعناية', 'grooming'], ['Vitamins', 'الفيتامينات', 'vitamins']] as [$en, $ar, $c]) {
        $inv[$c] = DB::insert('inventories', ['name_en' => $en, 'name_ar' => $ar, 'category' => $c]);
    }
    foreach (['PROBREED MIX', 'FIBERFORCE', 'VITAMINO', 'WHOLEGAIN', 'MASH & MIX', 'SHINE & SHOW', 'CEN OIL', 'POWER BOOSTER', 'BROODMARE AND GROWING'] as $i => $n) {
        DB::insert('inventory_items', ['inventory_id' => $inv['feed'], 'name_en' => $n, 'unit' => 'bag', 'quantity' => [40, 22, 6, 30, 3, 12, 8, 10, 15][$i], 'unit_price_qar' => [95, 110, 140, 90, 85, 120, 160, 130, 105][$i], 'min_quantity' => 8, 'supplier_id' => $parties['Maraba Al Asayel']]);
    }
    DB::insert('inventory_items', ['inventory_id' => $inv['clinic'], 'name_en' => 'Equine influenza vaccine', 'unit' => 'dose', 'quantity' => 4, 'unit_price_qar' => 180, 'min_quantity' => 5, 'expiry_date' => date('Y-m-d', strtotime('+25 days')), 'supplier_id' => $parties['Vet Zone']]);
    DB::insert('inventory_items', ['inventory_id' => $inv['clinic'], 'name_en' => 'Ivermectin paste', 'unit' => 'tube', 'quantity' => 20, 'unit_price_qar' => 35, 'min_quantity' => 6, 'expiry_date' => '2027-06-01', 'supplier_id' => $parties['Vet Zone']]);
    DB::insert('inventory_items', ['inventory_id' => $inv['grooming'], 'name_en' => 'Wood shavings bedding', 'unit' => 'bale', 'quantity' => 60, 'unit_price_qar' => 80, 'min_quantity' => 30]);

    // ---- bills (last 6 months, mixed statuses and currencies)
    $mk = function (array $d) {
        $d += ['currency' => 'QAR', 'exchange_rate' => 1, 'status' => 'approved'];
        $d['amount_qar'] = round($d['amount_original'] * $d['exchange_rate'], 2);
        $d['number'] = Sequence::bill($d['bill_date']);
        if ($d['status'] === 'paid') { $d['paid_qar'] = $d['amount_qar']; }
        return DB::insert('bills', $d);
    };
    for ($m = 5; $m >= 0; $m--) {
        $d = date('Y-m-', strtotime("-$m months"));
        $mk(['type' => 'expense', 'category_id' => $cat('Feed & Food'), 'party_id' => $parties['Maraba Al Asayel'], 'description' => 'Monthly feed', 'amount_original' => 3963.90 + $m * 150, 'bill_date' => $d . '05', 'status' => $m ? 'paid' : 'pending', 'account_id' => $bank]);
        $mk(['type' => 'expense', 'category_id' => $cat('Medical/Vet'), 'party_id' => $parties['Vet Zone'], 'description' => 'Vet services', 'amount_original' => 5260, 'bill_date' => $d . '12', 'status' => $m > 1 ? 'paid' : 'approved', 'due_date' => $d . '28', 'horse_id' => $meera]);
        $mk(['type' => 'expense', 'category_id' => $cat('Sawdust/Bedding'), 'description' => 'Sawdust', 'amount_original' => 1800, 'bill_date' => $d . '15', 'status' => 'paid', 'account_id' => $cash]);
        $mk(['type' => 'expense', 'category_id' => $cat('Utilities'), 'party_id' => $parties['Ooredoo'], 'description' => 'Internet & phone', 'amount_original' => 140, 'bill_date' => $d . '20', 'status' => 'paid', 'account_id' => $bank]);
    }
    $mk(['type' => 'expense', 'category_id' => $cat('Equipment'), 'party_id' => $parties['EVMC'], 'description' => 'Ultrasound probe (USD)', 'currency' => 'USD', 'exchange_rate' => 3.64, 'amount_original' => 2200, 'bill_date' => date('Y-m-03'), 'status' => 'pending']);
    $mk(['type' => 'expense', 'category_id' => $cat('Show Fees'), 'party_id' => $parties['Qatar Racing And Equestrian Club'], 'description' => 'Show entries', 'amount_original' => 10000, 'bill_date' => date('Y-m-08'), 'status' => 'pending', 'horse_id' => $tumooh]);
    $mk(['type' => 'income', 'category_id' => $cat('Prize Money'), 'description' => 'GCAT Ajman prize', 'amount_original' => 15000, 'bill_date' => '2026-02-10', 'status' => 'paid', 'horse_id' => $tumooh, 'account_id' => $bank]);
    $mk(['type' => 'expense', 'category_id' => $cat('Medical/Vet'), 'party_id' => $parties['First Vet For Veterinary Services W.L.L'], 'description' => 'Embryo flush & transfer', 'amount_original' => 3568, 'bill_date' => '2026-02-01', 'status' => 'overdue', 'due_date' => '2026-03-01', 'embryo_id' => $e1]);

    // ---- website content
    DB::insert('news', ['slug' => 'gcat-ajman-2026', 'title_en' => 'Two titles at GCAT Ajman', 'title_ar' => 'لقبان في كأس الخليج — عجمان', 'summary_en' => 'D Tumooh and Meera Al Mashrab brought home gold and silver.', 'summary_ar' => 'عاد د طموح وميرا المشرب بالذهبية والفضية.', 'body_en' => "A proud weekend for the whole team in Ajman.\n\nThank you to everyone who supported us.", 'body_ar' => "عطلة نهاية أسبوع مشرفة للفريق في عجمان.\n\nشكراً لكل من دعمنا.", 'published' => 1, 'published_at' => '2026-01-25 10:00:00', 'show_id' => $shows[1]]);
    DB::insert('inquiries', ['type' => 'horse', 'name' => 'Visitor from Kuwait', 'email' => 'visitor@example.com', 'message' => 'Beautiful mare, can we visit the stud?', 'horse_id' => $meera]);
});

// ---- one demo user per role
$pw = 'Demo!Stud2026';
$ownerSecret = App\Services\Totp::newSecret();
$users = [['owner', 'Owner (demo)', 'owner'], ['general_manager', 'General Manager (demo)', 'manager'], ['accountant', 'Accountant (demo)', 'accountant'], ['hr_officer', 'HR Officer (demo)', 'hr'],
          ['veterinarian', 'Veterinarian (demo)', 'vet'], ['trainer', 'Trainer (demo)', 'trainer'], ['groom', 'Groom (demo)', 'groom'], ['website_editor', 'Website Editor (demo)', 'editor'], ['tech_support', 'Technical Support (demo)', 'support']];
foreach ($users as [$role, $name, $username]) {
    $roleRow = DB::row('SELECT id, require_2fa FROM roles WHERE slug = ?', [$role]);
    DB::insert('users', [
        'username' => $username, 'email' => $username . '@demo.skarabian.local', 'name' => $name, 'password_hash' => Passwords::hash($pw),
        'role_id' => $roleRow['id'], 'status' => 'active', 'must_change_password' => 0,
        'twofa_method' => $roleRow['require_2fa'] ? 'totp' : 'none', 'totp_secret' => $roleRow['require_2fa'] ? Crypto::encrypt($ownerSecret) : null,
        'employee_id' => $username === 'groom' ? DB::value("SELECT id FROM employees WHERE name_en = 'Mohammed Rafiq'") : null,
    ]);
}
echo "Demo data loaded.\nUsers: owner, manager, accountant, hr, vet, trainer, groom, editor, support\nPassword: $pw\n";
echo "Authenticator secret for the 2FA demo users (owner, manager, accountant, hr): $ownerSecret\n";
