<?php
declare(strict_types=1);

namespace App\Resources;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\HorseRules;
use App\Services\HorseService;
use App\Services\RecurringBillService;

class Horses extends Resource
{
    public string $key = 'horses';
    public string $table = 'horses';
    public string $module = 'horses';
    public string $recordType = 'horse';
    public string $title = 'nav.horses';
    public string $singular = 'horses.singular';
    public array $search = ['t.name_en', 't.name_ar', 't.registration_no', 't.microchip', 't.passport_no'];
    public string $sort = "t.is_favorite DESC, FIELD(t.category,'stallion','mare','colt','filly','foal'), t.name_en";
    public bool $archivable = true;

    public const SEXES = ['male' => 'horse.sex_male', 'female' => 'horse.sex_female'];
    public const CATS = ['foal' => 'horse.cat_foal', 'colt' => 'horse.cat_colt', 'filly' => 'horse.cat_filly', 'stallion' => 'horse.cat_stallion', 'mare' => 'horse.cat_mare'];
    public const STATUSES = ['active' => 'horse.status_active', 'in_shelter' => 'horse.status_in_shelter', 'deceased' => 'horse.status_deceased'];
    /** Horses that left the stud through a transfer of ownership (database update 022). */
    public const LEFT_STATUSES = ['sold' => 'horse.status_sold', 'exported' => 'horse.status_exported', 'leased_out' => 'horse.status_leased_out'];

    /** Every status the database allows: the transfer statuses only once database update 022 is applied. */
    public static function statuses(): array
    {
        return HorseService::ownershipAvailable() ? self::STATUSES + self::LEFT_STATUSES : self::STATUSES;
    }

    public function sections(): array
    {
        return [
            'main' => 'horses.sec_identity', 'reg' => 'horses.sec_registration', 'ped' => 'horses.sec_pedigree',
            'own' => ['label' => 'horses.sec_ownership', 'collapsed' => true], 'web' => ['label' => 'horses.sec_website', 'collapsed' => true],
        ];
    }

    /** Long form → step wizard: essentials, pedigree, registration, then everything less-used as a review step. */
    public function wizard(): ?array
    {
        return [
            ['label' => 'horses.step_identity', 'sections' => ['main']],
            ['label' => 'horses.step_pedigree', 'sections' => ['ped']],
            ['label' => 'horses.step_registration', 'sections' => ['reg']],
            ['label' => 'horses.step_review', 'sections' => ['own', 'web']],
        ];
    }

    /** "Quick add" popup: just enough to get a horse into the system fast — full pedigree entry happens later. */
    public function quickFields(): array
    {
        return ['name_en', 'name_ar', 'sex', 'dob', 'color_id'];
    }

    /** The pedigree step: the full 5-generation (62-ancestor) tree, inline in the same form (works before the horse has an id — see afterSave()). */
    public function sectionBody(string $sec, ?array $row): ?string
    {
        if ($sec === 'ped') {
            $tree = $row ? HorseService::pedigree((int) $row['id'], HorseService::PEDIGREE_GENERATIONS) : [];
            return View::partial('portal/horses/pedigree_chart', ['h' => $row ?? ['id' => null, 'name_en' => null], 'tree' => $tree, 'mode' => 'embedded']);
        }
        return null;
    }

    /** Review step: a live-updating plain-text mirror of the pedigree tree filled in on the earlier step. */
    public function sectionExtra(string $sec, ?array $row): ?string
    {
        if ($sec === 'own') {
            return View::partial('portal/horses/pedigree_review', []);
        }
        return null;
    }

    public function formScripts(): array
    {
        return ['js/pedigree.js'];
    }

    public function __construct()
    {
        if (DB::hasColumn('horses', 'ueln')) {
            $this->search[] = 't.ueln';
        }
    }

    public function fields(): array
    {
        $f = [
            'name_en'         => ['type' => 'text', 'label' => 'horses.name_en', 'required' => true, 'max' => 120, 'no_phone' => true, 'attrs' => ['autocapitalize' => 'characters']],
            'name_ar'         => ['type' => 'text', 'label' => 'horses.name_ar', 'max' => 120, 'attrs' => ['dir' => 'rtl']],
            'sex'             => ['type' => 'segment', 'label' => 'horse.sex', 'required' => true, 'options' => self::SEXES, 'col' => 4],
            'dob'             => ['type' => 'date', 'label' => 'horses.dob', 'col' => 4, 'help' => 'horses.dob_help', 'attrs' => ['max' => date('Y-m-d')]],
            'category'        => ['type' => 'segment', 'label' => 'horses.category', 'options' => self::CATS, 'col' => 12, 'help' => 'horses.category_help'],
            'category_locked' => ['type' => 'checkbox', 'label' => 'horses.category_locked', 'col' => 12],
            'color_id'        => ['type' => 'lookup', 'lookup' => 'color', 'label' => 'horse.color', 'col' => 4],
            'breed_id'        => ['type' => 'lookup', 'lookup' => 'breed', 'label' => 'horse.breed', 'col' => 4, 'default' => fn () => DB::value("SELECT id FROM lookups WHERE type = 'breed' AND value_en = 'Purebred Arabian'")],
            'bloodline'       => ['type' => 'text', 'label' => 'horse.bloodline', 'col' => 4, 'max' => 120],
            'breeder'         => ['type' => 'text', 'label' => 'horse.breeder', 'col' => 4, 'max' => 150],
            'origin_country'  => ['type' => 'country', 'label' => 'horses.origin_country', 'col' => 4],
            'height_cm'       => ['type' => 'int', 'label' => 'horses.height_cm', 'col' => 4, 'min' => 50, 'unit' => 'cm'],
            'is_external'     => ['type' => 'checkbox', 'label' => 'horses.is_external', 'col' => 12, 'help' => 'horses.is_external_help'],

            'registration_no' => ['type' => 'text', 'label' => 'horses.registration_no', 'section' => 'reg', 'col' => 4, 'max' => 60, 'unique' => true],
            'microchip'       => ['type' => 'text', 'label' => 'horses.microchip', 'section' => 'reg', 'col' => 4, 'max' => 60, 'pattern' => '/^[0-9A-Za-z\- ]{4,60}$/', 'unique' => true],
            'passport_no'     => ['type' => 'text', 'label' => 'horses.passport_no', 'section' => 'reg', 'col' => 4, 'max' => 60, 'unique' => true],
            'passport_issue_date'  => ['type' => 'date', 'label' => 'horses.passport_issue_date', 'section' => 'reg', 'col' => 4],
            'passport_issue_place' => ['type' => 'text', 'label' => 'horses.passport_issue_place', 'section' => 'reg', 'col' => 8, 'max' => 120],

            'owner_type'      => ['type' => 'segment', 'label' => 'horses.owner_type', 'section' => 'own', 'options' => ['sk' => 'horses.owner_sk', 'client' => 'horses.owner_client'], 'default' => 'sk', 'col' => 4, 'required' => true],
            'owner_party_id'  => ['type' => 'picker', 'source' => 'clients', 'label' => 'horses.owner_party', 'section' => 'own', 'col' => 8, 'sensitive' => true],
            'location_id'     => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'horses.location', 'section' => 'own', 'col' => 4, 'remember' => true],
            'status'          => ['type' => 'segment', 'label' => 'common.status', 'section' => 'own', 'options' => self::statuses(), 'default' => 'active', 'required' => true, 'col' => 8],
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
        if (HorseService::ownershipAvailable()) { // an owner who is not a client, typed by name (database update 022)
            $f['owner_type']['options']['other'] = 'horses.owner_other';
            $at = array_search('location_id', array_keys($f), true);
            $f = array_slice($f, 0, $at, true)
               + ['owner_name' => ['type' => 'text', 'label' => 'horses.owner_name', 'section' => 'own', 'col' => 12, 'max' => 150, 'sensitive' => true, 'help' => 'horses.owner_name_help']]
               + array_slice($f, $at, null, true);
        }
        if (DB::hasColumn('horses', 'ueln')) { // Universal Equine Life Number, after the microchip (database update 018)
            $at = array_search('passport_no', array_keys($f), true);
            $f = array_slice($f, 0, $at, true)
               + ['ueln' => ['type' => 'text', 'label' => 'horses.ueln', 'section' => 'reg', 'col' => 4, 'max' => 40, 'unique' => true, 'help' => 'horses.ueln_help']]
               + array_slice($f, $at, null, true);
        }
        return $f;
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
            'name_ar'  => ['label' => 'common.name_ar', 'sql' => 't.name_ar', 'list_hidden' => true], // shown under the English name (afterCell); its own column in exports
            'category' => ['label' => 'horses.category', 'sql' => 't.category', 'fmt' => 'enum', 'prefix' => 'horse.cat', 'sort' => true, 'mobile' => true],
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
            'status'          => ['type' => 'select', 'label' => 'common.status', 'sql' => 't.status', 'options' => self::statuses()],
            'location'        => ['type' => 'lookup', 'lookup' => 'location', 'label' => 'horses.location', 'sql' => 't.location_id'],
            'is_favorite'     => ['type' => 'bool', 'label' => 'horses.favorites', 'sql' => 't.is_favorite'],
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
            ...(RecurringBillService::available() ? [['recurring_bills', 'horse_id', 'nav.recurring_bills']] : []),
        ];
    }

    /**
     * Once horses have an ownership history (database update 022) the owner of an existing horse is changed only
     * with "Transfer ownership" (HorseService::transferOwnership), which keeps the history: on the edit form the
     * owner fields are shown read-only.
     */
    public function visibleFields(bool $forCreate = true): array
    {
        $out = parent::visibleFields($forCreate);
        if (!$forCreate && HorseService::ownershipAvailable()) {
            foreach (['owner_type', 'owner_party_id', 'owner_name'] as $k) {
                if (isset($out[$k])) {
                    $out[$k]['readonly'] = true;
                    $out[$k]['required'] = false;
                    $out[$k]['help'] = 'horses.owner_via_transfer';
                }
            }
        }
        return $out;
    }

    public function prepare(array $data, ?array $old): array
    {
        $id = $old ? (int) $old['id'] : 0;
        $errors = [];
        if (($data['owner_type'] ?? '') === 'other' && array_key_exists('owner_name', $data) && trim((string) $data['owner_name']) === '') {
            $errors['owner_name'] = __('validation.required');
        }
        // Pedigree loop detection for all 62 tree positions happens in HorseService::savePedigree() (afterSave()).
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
        if (array_key_exists('owner_type', $data) && $data['owner_type'] !== 'other' && array_key_exists('owner_name', $data)) {
            $data['owner_name'] = null;
        }
        if (($data['owner_type'] ?? '') === 'other' && array_key_exists('owner_party_id', $data)) {
            $data['owner_party_id'] = null;
        }
        return $data;
    }

    public function afterSave(int $id, array $data, ?array $old, bool $created): void
    {
        // A foal born at the stud only appears in "Latest Foals" after the Owner approves
        $h = DB::row('SELECT born_at_sk, show_on_website, website_approved_at FROM horses WHERE id = ?', [$id]);
        if ($h && $h['born_at_sk'] && $h['show_on_website'] && !$h['website_approved_at']) {
            DB::update('horses', ['show_on_website' => 0], 'id = :id', ['id' => $id]);
            HorseService::requestWebsite($id);
        }
        // The pedigree tree (sire/dam → … → the 5th generation) posts inline with the rest of
        // this form (see sectionBody('ped')). A box the user never opened submits "touched_<path>=0" (its
        // initial hidden-input value) — that path is left out of $tree entirely, so HorseService::
        // savePedigree() doesn't touch whatever that ancestor already has on file; it would otherwise read
        // as "cleared" and wipe a picked ancestor's own recorded parents just because this particular form
        // didn't show them pre-filled. A box the user DID interact with (pick, new-name, or the × clear
        // button all mark it touched) is always included, including as an explicit null (= clear). A box still
        // showing what was on file is no change (savePedigree's $fromEditor), so the old line behind a replaced
        // ancestor is never written to the new one.
        $tree = [];
        foreach (HorseService::pedigreePaths() as $path) {
            $newEn = trim((string) ($_POST['new_' . $path . '_en'] ?? ''));
            $pick = (string) ($_POST['pick_' . $path] ?? '');
            $touched = ($_POST['touched_' . $path] ?? '0') === '1';
            if ($newEn !== '') {
                $tree[$path] = ['name_en' => $newEn, 'name_ar' => (string) ($_POST['new_' . $path . '_ar'] ?? ''), 'country' => (string) ($_POST['new_' . $path . '_country'] ?? '')];
            } elseif ($pick !== '') {
                $tree[$path] = (int) $pick;
            } elseif ($touched) {
                $tree[$path] = null;
            }
        }
        HorseService::savePedigree($id, $tree, true);
        if ($created) {
            HorseService::ensureOwnership($id); // ownership history starts with the owner entered here
        }
    }

    public function beforeTabs(array $row): string
    {
        return View::partial('portal/horses/header', ['h' => $row]);
    }

    /** The horse's photo next to its name (opens the full photo in the lightbox). */
    public function headMedia(array $row): string
    {
        $alt = (string) ($row['name_en'] ?? '');
        if (empty($row['main_photo_id'])) {
            return '<span class="head-photo is-empty" aria-hidden="true"><img src="' . e(asset('img/horse-placeholder.svg')) . '" alt=""></span>';
        }
        $full = url('/portal/files/' . (int) $row['main_photo_id']);
        return '<a class="head-photo" href="' . e($full) . '" data-lightbox="1" data-title="' . e($alt) . '" target="_blank" rel="noopener">'
            . '<img src="' . e($full . '/thumb') . '" alt="' . e($alt) . '" width="96" height="96"></a>';
    }

    /** The name in the other language under the heading (Arabic under English, English under Arabic). */
    public function headSub(array $row): string
    {
        $other = \App\Core\Lang::current() === 'ar' ? (string) ($row['name_en'] ?? '') : (string) ($row['name_ar'] ?? '');
        return $other !== '' && $other !== $this->heading($row) ? '<p class="alt-name"><span dir="auto">' . e($other) . '</span></p>' : '';
    }

    public function afterDetails(array $row): string
    {
        return View::partial('portal/horses/pedigree', ['h' => $row, 'tree' => HorseService::pedigree((int) $row['id'], HorseService::PEDIGREE_GENERATIONS)])
             . View::partial('portal/horses/assigned', ['h' => $row]);
    }

    public function headerBadges(array $row): string
    {
        return parent::headerBadges($row) . ' ' . View::partial('portal/horses/fav_star', ['h' => $row]);
    }

    /** The favourite flag of each row, for the star next to the name. */
    public function listExtraSelect(): array
    {
        return ['_fav' => 't.is_favorite'];
    }

    public function afterCell(string $key, array $row): string
    {
        if ($key !== 'name_en') {
            return '';
        }
        return View::partial('portal/horses/fav_star', ['h' => ['id' => $row['id'], 'is_favorite' => $row['_fav'] ?? 0, 'name_en' => $row['name_en'] ?? '']])
            . (!empty($row['name_ar']) ? '<span class="cell-sub" dir="rtl" lang="ar">' . e((string) $row['name_ar']) . '</span>' : '');
    }

    /** "Import from Excel" for whoever may add horses; Owner: "Import horse cards" until the cards of 7 October 2026 have been imported. */
    public function listActions(): array
    {
        $a = [];
        if (Auth::can('horses', 'create') && !Auth::horseScopeAssigned()) {
            $a[] = ['himport.button', '/portal/horses/import', ''];
        }
        if (Auth::isOwner() && \App\Services\HorseCardsImport::hasFile() && !\App\Services\HorseCardsImport::done()) {
            $a[] = ['cards.title', '/portal/horses/cards-import', ''];
        }
        return $a;
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
        if (Auth::can('horses', 'edit') && !$row['is_external'] && $row['status'] !== 'deceased' && HorseService::ownershipAvailable()) {
            $a[] = ['label' => 'ownership.transfer', 'url' => '/portal/horses/' . $row['id'] . '/transfer'];
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
        // embryos from this mare (donor) or by this stallion — an embryo-transfer pregnancy is under the recipient mare
        $embCol = $row['sex'] === 'female' ? 'donor_mare_id' : 'sire_id';
        if (Auth::can('embryos') && ($embN = (int) DB::value("SELECT COUNT(*) FROM embryos WHERE $embCol = ? AND deleted_at IS NULL", [$id]))) {
            $t['embryos'] = ['label' => 'nav.embryos', 'related' => 'embryos', 'filter' => [$embCol => $id], 'count' => $embN];
        }
        $t['offspring'] = ['label' => 'horses.offspring', 'render' => fn ($r) => View::partial('portal/horses/tab_offspring', ['h' => $r]), 'count' => (int) DB::value('SELECT COUNT(*) FROM horses WHERE (sire_id = ? OR dam_id = ?) AND deleted_at IS NULL', [$id, $id]) ?: null];
        if (Auth::can('horse_notes')) {
            $t['horse-notes'] = ['label' => 'horses.notes', 'related' => 'horse-notes', 'filter' => ['horse_id' => $id]];
        }
        if (Auth::can('finance')) {
            $t['finance'] = ['label' => 'horses.finance', 'render' => fn ($r) => View::partial('portal/horses/tab_finance', ['h' => $r])];
        }
        if (HorseService::ownershipAvailable() && (!$row['is_external'] || DB::value('SELECT 1 FROM horse_ownership WHERE horse_id = ? AND deleted_at IS NULL AND transfer_type NOT IN (\'opening\',\'birth\') LIMIT 1', [$id]))) {
            $t['ownership'] = ['label' => 'ownership.tab', 'render' => fn ($r) => View::partial('portal/horses/tab_ownership', ['h' => $r, 'rows' => HorseService::ownership((int) $r['id'])]),
                'count' => (int) DB::value('SELECT COUNT(*) FROM horse_ownership WHERE horse_id = ? AND deleted_at IS NULL', [$id]) ?: null];
        }
        return $t + $this->standardTabs($row, ['studio' => ['profile', 'diet', 'vet', 'cover', 'board'], 'categories' => ['photo', 'video', 'document', 'certificate']]);
    }
}
