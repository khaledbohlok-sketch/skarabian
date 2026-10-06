<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\HorseRules;
use App\Services\HorseService;

class Horses extends Resource
{
    public string $key = 'horses';
    public string $table = 'horses';
    public string $module = 'horses';
    public string $recordType = 'horse';
    public string $title = 'nav.horses';
    public string $singular = 'horses.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.registration_no', 't.microchip', 't.passport_no'];
    public string $sort = "t.is_favorite DESC, FIELD(t.category,'stallion','mare','colt','filly','foal','gelding'), t.name_en";
    public bool $archivable = true;

    public const SEXES = ['male' => 'horse.sex_male', 'female' => 'horse.sex_female', 'gelding' => 'horse.sex_gelding'];
    public const CATS = ['foal' => 'horse.cat_foal', 'colt' => 'horse.cat_colt', 'filly' => 'horse.cat_filly', 'stallion' => 'horse.cat_stallion', 'mare' => 'horse.cat_mare', 'gelding' => 'horse.cat_gelding'];
    public const STATUSES = ['active' => 'horse.status_active', 'in_shelter' => 'horse.status_in_shelter', 'sold' => 'horse.status_sold', 'transferred' => 'horse.status_transferred', 'deceased' => 'horse.status_deceased'];

    public function sections(): array
    {
        return ['main' => 'horses.sec_identity', 'reg' => 'horses.sec_registration', 'ped' => 'horses.sec_pedigree', 'own' => 'horses.sec_ownership', 'web' => 'horses.sec_website'];
    }

    public function fields(): array
    {
        return [
            'name_en'         => ['type' => 'text', 'label' => 'horses.name_en', 'required' => true, 'max' => 120, 'no_phone' => true, 'attrs' => ['autocapitalize' => 'characters']],
            'name_ar'         => ['type' => 'text', 'label' => 'horses.name_ar', 'max' => 120, 'attrs' => ['dir' => 'rtl']],
            'sex'             => ['type' => 'select', 'label' => 'horse.sex', 'required' => true, 'options' => self::SEXES, 'col' => 4],
            'dob'             => ['type' => 'date', 'label' => 'horses.dob', 'col' => 4, 'help' => 'horses.dob_help'],
            'category'        => ['type' => 'select', 'label' => 'horses.category', 'options' => self::CATS, 'col' => 4, 'help' => 'horses.category_help'],
            'category_locked' => ['type' => 'checkbox', 'label' => 'horses.category_locked', 'col' => 12],
            'color_id'        => ['type' => 'lookup', 'lookup' => 'color', 'label' => 'horse.color', 'col' => 4],
            'breed_id'        => ['type' => 'lookup', 'lookup' => 'breed', 'label' => 'horse.breed', 'col' => 4, 'default' => fn () => DB::value("SELECT id FROM lookups WHERE type = 'breed' AND value_en = 'Purebred Arabian'")],
            'bloodline'       => ['type' => 'text', 'label' => 'horse.bloodline', 'col' => 4, 'max' => 120],
            'breeder'         => ['type' => 'text', 'label' => 'horse.breeder', 'col' => 4, 'max' => 150],
            'origin_country'  => ['type' => 'text', 'label' => 'horses.origin_country', 'col' => 4, 'max' => 80],
            'height_cm'       => ['type' => 'int', 'label' => 'horses.height_cm', 'col' => 4, 'min' => 50],

            'registration_no' => ['type' => 'text', 'label' => 'horses.registration_no', 'section' => 'reg', 'col' => 4, 'max' => 60],
            'microchip'       => ['type' => 'text', 'label' => 'horses.microchip', 'section' => 'reg', 'col' => 4, 'max' => 60, 'pattern' => '/^[0-9A-Za-z\- ]{4,60}$/'],
            'passport_no'     => ['type' => 'text', 'label' => 'horses.passport_no', 'section' => 'reg', 'col' => 4, 'max' => 60],
            'passport_issue_date'  => ['type' => 'date', 'label' => 'horses.passport_issue_date', 'section' => 'reg', 'col' => 4],
            'passport_issue_place' => ['type' => 'text', 'label' => 'horses.passport_issue_place', 'section' => 'reg', 'col' => 8, 'max' => 120],

            'sire_id'         => ['type' => 'picker', 'source' => 'stallions', 'label' => 'horse.sire', 'section' => 'ped', 'help' => 'horses.parent_help'],
            'dam_id'          => ['type' => 'picker', 'source' => 'mares', 'label' => 'horse.dam', 'section' => 'ped'],
            'is_external'     => ['type' => 'checkbox', 'label' => 'horses.is_external', 'section' => 'ped', 'col' => 12, 'help' => 'horses.is_external_help'],

            'owner_type'      => ['type' => 'select', 'label' => 'horses.owner_type', 'section' => 'own', 'options' => ['sk' => 'horses.owner_sk', 'client' => 'horses.owner_client'], 'default' => 'sk', 'col' => 4, 'required' => true],
            'owner_party_id'  => ['type' => 'picker', 'source' => 'clients', 'label' => 'horses.owner_party', 'section' => 'own', 'col' => 8, 'sensitive' => true],
            'location_id'     => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'horses.location', 'section' => 'own', 'col' => 4],
            'status'          => ['type' => 'select', 'label' => 'common.status', 'section' => 'own', 'options' => self::STATUSES, 'default' => 'active', 'required' => true, 'col' => 4],
            'purchase_date'   => ['type' => 'date', 'label' => 'horses.purchase_date', 'section' => 'own', 'col' => 4],
            'purchase_price_qar' => ['type' => 'money', 'label' => 'horses.purchase_price', 'section' => 'own', 'col' => 4, 'sensitive' => true, 'min' => 0],
            'notes'           => ['type' => 'textarea', 'label' => 'common.notes', 'section' => 'own', 'rows' => 3],

            'show_on_website' => ['type' => 'checkbox', 'label' => 'horses.show_on_website', 'section' => 'web', 'col' => 4],
            'breeding_stallion' => ['type' => 'checkbox', 'label' => 'horses.breeding_stallion', 'section' => 'web', 'col' => 4],
            'is_favorite'     => ['type' => 'checkbox', 'label' => 'horses.is_favorite', 'section' => 'web', 'col' => 4],
            'story_en'        => ['type' => 'textarea', 'label' => 'horses.story_en', 'section' => 'web', 'col' => 6, 'rows' => 5],
            'story_ar'        => ['type' => 'textarea', 'label' => 'horses.story_ar', 'section' => 'web', 'col' => 6, 'rows' => 5, 'attrs' => ['dir' => 'rtl']],
            'video_url'       => ['type' => 'url', 'label' => 'horses.video_url', 'section' => 'web', 'col' => 12, 'help' => 'horses.video_help'],
        ];
    }

    public function from(): string
    {
        return '`horses` t LEFT JOIN lookups loc ON loc.id = t.location_id';
    }

    public function columns(): array
    {
        return [
            'photo'    => ['label' => 'horses.photo', 'sql' => 't.main_photo_id', 'fmt' => 'thumb'],
            'name_en'  => ['label' => 'common.name', 'sql' => 't.name_en', 'sort' => true, 'link' => true],
            'name_ar'  => ['label' => 'common.name_ar', 'sql' => 't.name_ar'],
            'category' => ['label' => 'horses.category', 'sql' => 't.category', 'fmt' => 'enum', 'prefix' => 'horse.cat', 'sort' => true],
            'dob'      => ['label' => 'horses.age', 'sql' => 't.dob', 'fmt' => 'age', 'sort' => true],
            'location' => ['label' => 'horses.location', 'sql' => (\App\Core\Lang::isRtl() ? 'COALESCE(loc.value_ar, loc.value_en)' : 'loc.value_en'), 'sort' => true],
            'status'   => ['label' => 'common.status', 'sql' => 't.status', 'fmt' => 'badge', 'prefix' => 'horse.status'],
            'web'      => ['label' => 'common.on_website', 'sql' => 't.show_on_website', 'fmt' => 'bool'],
        ];
    }

    public function filters(): array
    {
        return [
            'category'        => ['type' => 'select', 'label' => 'horses.category', 'sql' => 't.category', 'options' => self::CATS],
            'sex'             => ['type' => 'select', 'label' => 'horse.sex', 'sql' => 't.sex', 'options' => self::SEXES],
            'status'          => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::STATUSES],
            'location'        => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'horses.location', 'sql' => 't.location_id'],
            'show_on_website' => ['type' => 'bool', 'label' => 'common.on_website', 'sql' => 't.show_on_website'],
            'is_external'     => ['type' => 'bool', 'label' => 'horses.is_external_short', 'sql' => 't.is_external', 'default' => '0'],
            'archived'        => ['type' => 'archived', 'label' => 'common.show_archived'],
        ];
    }

    public function scope(array &$where, array &$params): void
    {
        if (Auth::horseScopeAssigned()) {
            $where[] = 't.id IN ' . DB::in(Auth::assignedHorseIds() ?: [0], 'asg', $params);
        }
    }

    public function canView(array $row): bool
    {
        return Auth::can('horses') && Auth::canSeeHorse((int) $row['id']);
    }

    public function label(array $row): string
    {
        return (string) $row['name_en'];
    }

    public function links(): array
    {
        return [
            ['health_records', 'horse_id', 'nav.health'], ['diet_logs', 'horse_id', 'nav.diet'], ['training_logs', 'horse_id', 'nav.training'],
            ['show_results', 'horse_id', 'nav.shows'], ['breeding_records', 'mare_id', 'nav.breeding'], ['breeding_records', 'stallion_id', 'nav.breeding'],
            ['embryos', 'donor_mare_id', 'nav.embryos'], ['embryos', 'recipient_mare_id', 'nav.embryos'], ['embryos', 'sire_id', 'nav.embryos'],
            ['horses', 'sire_id', 'horses.offspring'], ['horses', 'dam_id', 'horses.offspring'], ['bills', 'horse_id', 'nav.bills'],
            ['stock_movements', 'horse_id', 'nav.movements'], ['horse_notes', 'horse_id', 'horses.notes'],
        ];
    }

    public function prepare(array $data, ?array $old): array
    {
        $id = $old ? (int) $old['id'] : 0;
        $errors = [];
        foreach (['sire_id', 'dam_id'] as $p) {
            if (!empty($data[$p]) && $id && HorseService::isSelfOrDescendant($id, (int) $data[$p])) {
                $errors[$p] = __('horses.pedigree_loop');
            }
        }
        if (($data['owner_type'] ?? 'sk') === 'client' && array_key_exists('owner_party_id', $data) && empty($data['owner_party_id'])) {
            $errors['owner_party_id'] = __('validation.required');
        }
        if (!empty($data['dob']) && $data['dob'] > date('Y-m-d')) {
            $errors['dob'] = __('horses.dob_future');
        }
        if (!empty($data['breeding_stallion']) && ($data['sex'] ?? '') !== 'male') {
            $data['breeding_stallion'] = 0;
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        // Category is suggested from age and sex unless the user locked it
        if (empty($data['category_locked']) || empty($data['category'])) {
            $data['category'] = HorseRules::category($data['sex'], $data['dob'] ?? null);
        }
        if (!$old || $old['name_en'] !== $data['name_en']) {
            $data['slug'] = HorseService::uniqueSlug($data['name_en'], $id ?: null);
        }
        if (($data['owner_type'] ?? 'sk') === 'sk' && array_key_exists('owner_party_id', $data)) {
            $data['owner_party_id'] = null;
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        if ($created && empty($data['is_external'])) {
            DB::insert('ownership_history', [
                'horse_id' => $id, 'event_type' => !empty($data['purchase_date']) ? 'purchase' : 'import',
                'event_date' => $data['purchase_date'] ?? date('Y-m-d'), 'price_qar' => $data['purchase_price_qar'] ?? null, 'created_by' => Auth::id(),
            ]);
        }
        // A foal born at the stud only appears in "Latest Foals" after the Owner approves
        $h = DB::row('SELECT born_at_sk, show_on_website, website_approved_at FROM horses WHERE id = ?', [$id]);
        if ($h && $h['born_at_sk'] && $h['show_on_website'] && !$h['website_approved_at']) {
            DB::update('horses', ['show_on_website' => 0], 'id = :id', ['id' => $id]);
            HorseService::requestWebsite($id);
        }
    }

    public function beforeTabs(array $row): string
    {
        return View::partial('portal/horses/header', ['h' => $row]);
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/horses/pedigree', ['tree' => HorseService::pedigree((int) $row['id'], 4)])
             . View::partial('portal/horses/assigned', ['h' => $row]);
    }

    public function headerBadges(array $row): string
    {
        $b = parent::headerBadges($row);
        if ($row['is_favorite']) {
            $b .= ' <span class="chip gold">★</span>';
        }
        return $b;
    }

    public function actions(array $row): array
    {
        $a = [];
        if (Auth::can('horses', 'print')) {
            $a[] = ['label' => 'horses.qr_sticker', 'url' => '/portal/horses/' . $row['id'] . '/qr', 'blank' => true];
        }
        if ($row['sex'] === 'female' && Auth::can('horse_breeding', 'create')) {
            $a[] = ['label' => 'horses.record_foaling', 'url' => '/portal/horses/' . $row['id'] . '/foaling'];
        }
        if (Auth::can('horses', 'edit') && !$row['is_external'] && in_array($row['status'], ['active', 'in_shelter'], true)) {
            $a[] = ['label' => 'horses.transfer_ownership', 'url' => '/portal/horses/' . $row['id'] . '/sell'];
        }
        return $a;
    }

    public function tabs(array $row): array
    {
        $id = (int) $row['id'];
        $count = fn (string $sql) => (int) DB::value($sql, [$id]);
        $t = [];
        if (Auth::can('horse_health')) {
            $t['health-records'] = ['label' => 'nav.health', 'related' => 'health-records', 'filter' => ['horse_id' => $id], 'count' => $count('SELECT COUNT(*) FROM health_records WHERE horse_id = ? AND deleted_at IS NULL')];
        }
        if (Auth::can('horse_diet')) {
            $t['diet'] = ['label' => 'nav.diet', 'render' => fn ($r) => View::partial('portal/horses/tab_diet', ['h' => $r])];
        }
        if (Auth::can('horse_training')) {
            $t['training-logs'] = ['label' => 'nav.training', 'related' => 'training-logs', 'filter' => ['horse_id' => $id]];
        }
        if (Auth::can('horse_training') || Auth::can('horses')) {
            $t['show-results'] = ['label' => 'horses.shows_titles', 'related' => 'show-results', 'filter' => ['horse_id' => $id], 'count' => $count('SELECT COUNT(*) FROM show_results WHERE horse_id = ? AND deleted_at IS NULL')];
        }
        if (Auth::can('horse_breeding')) {
            $t['breeding-records'] = ['label' => 'nav.breeding', 'related' => 'breeding-records', 'filter' => [$row['sex'] === 'female' ? 'mare_id' : 'stallion_id' => $id]];
        }
        $t['offspring'] = ['label' => 'horses.offspring', 'render' => fn ($r) => View::partial('portal/horses/tab_offspring', ['h' => $r]), 'count' => (int) DB::value('SELECT COUNT(*) FROM horses WHERE (sire_id = ? OR dam_id = ?) AND deleted_at IS NULL', [$id, $id]) ?: null];
        if (Auth::can('horse_notes')) {
            $t['horse-notes'] = ['label' => 'horses.notes', 'related' => 'horse-notes', 'filter' => ['horse_id' => $id]];
        }
        if (Auth::can('finance')) {
            $t['finance'] = ['label' => 'horses.finance', 'render' => fn ($r) => View::partial('portal/horses/tab_finance', ['h' => $r])];
        }
        $t['ownership'] = ['label' => 'horses.ownership', 'render' => fn ($r) => View::partial('portal/horses/tab_ownership', ['h' => $r])];
        return $t + $this->standardTabs($row, ['studio' => ['profile', 'diet', 'vet', 'cover', 'transfer'], 'categories' => ['photo', 'video', 'document', 'certificate']]);
    }
}
