<?php
/**
 * TCC RRS – UI Component Library
 *
 * Uniform, reusable render functions for every page in the system.
 * Every component returns an HTML string; use `<?= ... ?>` in views.
 *
 * Components:
 *   ui_page_header()   Page title + subtitle + action buttons
 *   ui_status_tabs()   Quick-filter chip row (All / statuses / Archived)
 *   ui_filter_bar()    GET filter form with uniform fields
 *   ui_card_open/close Content card (header + body wrapper)
 *   ui_stat_card()     Dashboard metric tile
 *   ui_table()         Data table w/ built-in empty state
 *   ui_table_open/close For hand-built table rows
 *   ui_list_footer()   "Showing X–Y of Z" + pagination row
 *   ui_details()       Label/value detail grid (view pages)
 *   ui_empty()         Empty state block
 *   ui_code()          Monospace tracking-code chip
 *   ui_avatar()        Initials avatar
 *   ui_field()         Form field (label + control + help/error)
 */

require_once __DIR__ . '/helpers.php';

// ══════════════════════════════════════════════════════════════════════════════
// PAGE HEADER
// ══════════════════════════════════════════════════════════════════════════════

/**
 * @param string $title    Page heading
 * @param string $subtitle Small grey description under the title
 * @param string $icon     Bootstrap icon name (no `bi-` prefix) or ''
 * @param string $actions  Pre-rendered buttons/markup for the right side
 */
function ui_page_header(string $title, string $subtitle = '', string $icon = '', string $actions = ''): string
{
    $iconHtml = $icon ? '<i class="bi bi-' . e($icon) . ' me-2 text-primary"></i>' : '';
    $sub      = $subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '';
    $act      = $actions  !== '' ? '<div class="page-header-actions">' . $actions . '</div>' : '';
    return <<<HTML
    <div class="page-header">
      <div class="page-header-info"><h4>{$iconHtml}{$title}</h4>{$sub}</div>
      {$act}
    </div>
    HTML;
}

/**
 * Back-button used on detail pages.
 */
function ui_back(string $url, string $label = 'Back to List'): string
{
    return '<a href="' . e($url) . '" class="btn-back"><i class="bi bi-arrow-left"></i> ' . e($label) . '</a>';
}

/**
 * Uniform button / link-button.
 *
 * @param string $label Button text (escaped)
 * @param array  $opts  Options:
 *   'href'       string  renders <a> instead of <button>
 *   'type'       string  button type, default 'button' ('submit' in forms)
 *   'variant'    string  Bootstrap variant, default 'primary'
 *   'size'       string  '' | 'sm' | 'lg'
 *   'icon'       string  left icon (bootstrap name, no `bi-` prefix)
 *   'icon_right' string  right icon
 *   'class'      string  extra classes appended to .btn
 *   'attrs'      string  raw extra attributes, e.g. 'onclick="..." id="x"'
 */
function ui_btn(string $label, array $opts = []): string
{
    $href    = $opts['href']       ?? '';
    $type    = $opts['type']       ?? 'button';
    $variant = $opts['variant']    ?? 'primary';
    $size    = $opts['size']       ?? '';
    $class   = $opts['class']      ?? '';
    $attrs   = $opts['attrs']      ?? '';
    $icon    = isset($opts['icon'])       ? '<i class="bi bi-' . e($opts['icon']) . ' me-1"></i>'       : '';
    $iconR   = isset($opts['icon_right']) ? '<i class="bi bi-' . e($opts['icon_right']) . ' ms-1"></i>' : '';

    $cls = 'btn btn-' . $variant
         . ($size !== '' ? ' btn-' . $size : '')
         . ($class !== '' ? ' ' . $class : '');
    $inner = $icon . e($label) . $iconR;

    if ($href !== '') {
        return '<a href="' . e($href) . '" class="' . $cls . '"' . ($attrs ? ' ' . $attrs : '') . '>' . $inner . '</a>';
    }
    return '<button type="' . e($type) . '" class="' . $cls . '"' . ($attrs ? ' ' . $attrs : '') . '>' . $inner . '</button>';
}

/**
 * Portal-style page header used on student/guest-facing pages:
 * icon tile + title + subtitle on the left, back link pinned right.
 *
 * @param string $title    Page heading
 * @param string $subtitle Small grey description under the title
 * @param string $icon     Bootstrap icon name (no `bi-` prefix)
 * @param string $backUrl  URL for the back link ('' = no back link)
 * @param string $backLabel
 */
function ui_portal_header(string $title, string $subtitle = '', string $icon = '', string $backUrl = '', string $backLabel = 'Back'): string
{
    $tile = $icon !== ''
        ? '<div class="portal-card-icon portal-head-icon"><i class="bi bi-' . e($icon) . ' fs-5"></i></div>'
        : '';
    $sub  = $subtitle !== '' ? '<p class="text-muted mb-0 small">' . e($subtitle) . '</p>' : '';
    $back = $backUrl  !== '' ? ui_back($backUrl, $backLabel) : '';
    $ttl  = e($title);
    return <<<HTML
    <div class="portal-page-head">
      {$tile}
      <div class="portal-head-text"><h3 class="fw-bold mb-0">{$ttl}</h3>{$sub}</div>
      <div class="ms-auto">{$back}</div>
    </div>
    HTML;
}

// ══════════════════════════════════════════════════════════════════════════════
// STATUS QUICK TABS  (All · Pending · … · Archived)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Renders the chip row of quick status filters used on list pages.
 *
 * @param array $tabs each: ['label','url','count'?,'active'?,'variant'?,'icon'?]
 *   variant = bootstrap color name ('warning','success',...) or 'dark' / 'primary'
 */
function ui_status_tabs(array $tabs): string
{
    $html = '<div class="status-tabs">';
    foreach ($tabs as $t) {
        if (!empty($t['spacer'])) { $html .= '<div class="spacer"></div>'; continue; }
        $active  = !empty($t['active']);
        $variant = $t['variant'] ?? 'secondary';
        $cls     = $active
            ? 'btn-' . $variant . (in_array($variant, ['warning', 'light', 'gold'], true) ? '' : ' text-white')
            : 'btn-outline-secondary';
        $count = '';
        if (isset($t['count'])) {
            $cCls  = $active ? 'bg-white text-dark' : 'bg-secondary-subtle text-secondary-emphasis';
            $count = ' <span class="badge ' . $cCls . ' ms-1">' . number_format((int)$t['count']) . '</span>';
        }
        $icon  = isset($t['icon']) ? '<i class="bi bi-' . e($t['icon']) . ' me-1"></i>' : '';
        $html .= '<a href="' . e($t['url']) . '" class="btn btn-sm ' . $cls . '">'
               . $icon . e($t['label']) . $count . '</a>';

    }
    $html .= '</div>';
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// FILTER BAR
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Uniform GET filter form.
 *
 * @param string $action  Form action URL
 * @param array  $fields  Field descriptors:
 *   type:  'search' | 'text' | 'select' | 'date' | 'number'
 *   name, label, value, options ['v'=>'Label'], placeholder,
 *   col (default 'col-md-2'), icon (search boxes)
 * @param array  $hidden  name => value hidden inputs (preserved filters)
 * @param string $resetUrl URL for the clear/reset button ('' = none)
 */
function ui_filter_bar(string $action, array $fields, array $hidden = [], string $resetUrl = '', string $submitLabel = 'Filter'): string
{
    $html  = '<div class="filter-bar"><form method="GET" action="' . e($action) . '" class="filter-form">';
    foreach ($hidden as $n => $v) {
        if ($v === '' || $v === null) continue;
        $html .= '<input type="hidden" name="' . e($n) . '" value="' . e($v) . '">';
    }
    foreach ($fields as $f) {
        $type  = $f['type']  ?? 'text';
        $name  = $f['name']  ?? '';
        $label = $f['label'] ?? '';
        $value = $f['value'] ?? '';
        $col   = $f['col']   ?? 'col-md-2';
        $req   = !empty($f['required']) ? ' required' : '';
        // Bootstrap col-md-N hint → flex-basis N/12 so search can grow wider than selects
        $basis = '170px';
        if (preg_match('/col-md-(\d+)/', $col, $m)) $basis = round($m[1] / 12 * 100, 2) . '%';
        $html .= '<div class="filter-field" style="flex:1 1 ' . $basis . ';min-width:150px">';
        if ($label !== '') $html .= '<label class="form-label" for="ff-' . e($name) . '">' . e($label) . '</label>';
        if ($type === 'select') {
            $html .= '<select name="' . e($name) . '" id="ff-' . e($name) . '" class="form-select"' . $req . '>';
            foreach (($f['options'] ?? []) as $ov => $ol) {
                $sel = ((string)$value === (string)$ov) ? ' selected' : '';
                $html .= '<option value="' . e($ov) . '"' . $sel . '>' . e($ol) . '</option>';
            }
            $html .= '</select>';
        } else {
            $inputType = $type === 'search' ? 'text' : $type;
            $ph   = e($f['placeholder'] ?? '');
            $icon = $type === 'search' ? ($f['icon'] ?? 'search') : ($f['icon'] ?? '');
            if ($icon !== '') {
                $html .= '<div class="input-group">'
                       . '<span class="input-group-text"><i class="bi bi-' . e($icon) . '"></i></span>'
                       . '<input type="' . $inputType . '" name="' . e($name) . '" id="ff-' . e($name) . '" class="form-control" value="' . e($value) . '" placeholder="' . $ph . '"' . $req . '>'
                       . '</div>';
            } else {
                $html .= '<input type="' . $inputType . '" name="' . e($name) . '" id="ff-' . e($name) . '" class="form-control" value="' . e($value) . '" placeholder="' . $ph . '"' . $req . '>';
            }
        }
        $html .= '</div>';
    }
    $html .= '<div class="filter-actions">'
           . ui_btn($submitLabel, ['type'=>'submit','icon'=>'funnel']);
    if ($resetUrl !== '') {
        $html .= ui_btn('', ['variant'=>'outline-secondary','icon'=>'x-lg','href'=>$resetUrl,
                            'attrs'=>'title="Clear filters"']);
    }
    $html .= '</div></form></div>';
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// CONTENT CARD
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Open a content card. Call ui_card_close() after the body markup.
 *
 * @param string $title Header title ('' = no header)
 * @param string $icon  bi icon name for header
 * @param string $right Right-aligned header markup (count badges, links)
 * @param string $class Extra classes on the card wrapper (e.g. 'mb-3')
 */
function ui_card_open(string $title = '', string $icon = '', string $right = '', string $class = ''): string
{
    $html = '<div class="content-card' . ($class !== '' ? ' ' . e($class) : '') . '">';
    if ($title !== '' || $right !== '') {
        $ic = $icon !== '' ? '<i class="bi bi-' . e($icon) . ' me-2"></i>' : '';
        $html .= '<div class="content-card-header"><h5>' . $ic . $title . '</h5>'
               . '<div class="content-card-header-right">' . $right . '</div></div>';
    }
    return $html;
}

function ui_card_close(): string
{
    return '</div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// STAT CARD  (dashboard metric tile)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * @param string $icon  bi icon name
 * @param string $label Small uppercase label
 * @param int|string $value  Big number
 * @param string $color  green|gold|blue|teal|purple|red|orange|yellow
 * @param string $sub    Small caption under value
 * @param string|null $href  Wrap in a link when set
 */
function ui_stat_card(string $icon, string $label, int|string $value, string $color = 'green', string $sub = '', ?string $href = null): string
{
    $tag  = $href !== null ? 'a' : 'div';
    $attr = $href !== null ? ' href="' . e($href) . '"' : '';
    $v    = is_numeric($value) ? number_format((float)$value) : e($value);
    $subH = $sub !== '' ? '<div class="stat-sub">' . e($sub) . '</div>' : '';
    return <<<HTML
    <{$tag}{$attr} class="stat-card">
      <div class="stat-icon {$color}"><i class="bi bi-{$icon}"></i></div>
      <div class="stat-info">
        <div class="stat-label">{$label}</div>
        <div class="stat-value">{$v}</div>
        {$subH}
      </div>
    </{$tag}>
    HTML;
}

// ══════════════════════════════════════════════════════════════════════════════
// EMPTY STATE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * KPI metric card — label + delta chip, big value, trend caption.
 * $delta: signed % (null → no chip). $caption icon auto: trend arrow.
 */
function ui_kpi(string $label, int|string $value, ?float $delta, string $caption, string $href = ''): string
{
    $chip = '';
    if ($delta !== null) {
        $dir  = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        $icon = $delta > 0 ? 'arrow-up-right' : ($delta < 0 ? 'arrow-down-right' : 'dash');
        $chip = '<span class="kpi-delta ' . $dir . '"><i class="bi bi-' . $icon . '"></i>'
              . ($delta > 0 ? '+' : '') . rtrim(rtrim(number_format($delta, 1), '0'), '.') . '%</span>';
    }
    $trendIcon = $delta !== null && $delta > 0 ? 'graph-up-arrow' : ($delta !== null && $delta < 0 ? 'graph-down-arrow' : 'activity');
    $inner =
        '<div class="kpi-head"><span class="kpi-label">' . e($label) . '</span>' . $chip . '</div>'
      . '<div class="kpi-value">' . e((string)$value) . '</div>'
      . '<div class="kpi-trend"><i class="bi bi-' . $trendIcon . '"></i> ' . e($caption) . '</div>';
    return $href
        ? '<a href="' . e($href) . '" class="kpi-card">' . $inner . '</a>'
        : '<div class="kpi-card">' . $inner . '</div>';
}

function ui_empty(string $icon = 'inbox', string $title = 'Nothing here yet', string $msg = '', string $actionHtml = ''): string
{
    $m = $msg !== '' ? '<p>' . e($msg) . '</p>' : '';
    return '<div class="empty-state"><i class="bi bi-' . e($icon) . '"></i>'
         . '<h6>' . e($title) . '</h6>' . $m . $actionHtml . '</div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// DATA TABLE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Renders <div class="table-responsive"><table>…</table></div>.
 *
 * @param array $headers  'Label' or ['label'=>, 'style'=>, 'class'=>]
 * @param array $rows     Each row: array of cell HTML strings,
 *                        or ['cells'=>[...], 'class'=>'...']
 * @param array $opts     empty_icon, empty_title, empty_msg, class, id
 */
/**
 * @param array $opts  …plus 'pagination' => ['page','pages','total','per_page','base_url']
 *                     renders the range + pager footer inside the card.
 */
function ui_table(array $headers, array $rows, array $opts = []): string
{
    $html = ui_table_open($headers, $opts)
         . ui_table_rows($rows, count($headers), $opts)
         . ui_table_close();
    if (isset($opts['pagination'])) {
        $pg = $opts['pagination'];
        $html .= ui_list_footer(
            (int)$pg['page'], (int)$pg['pages'],
            (int)$pg['total'], (int)$pg['per_page'],
            (string)$pg['base_url']
        );
    }
    return $html;
}

function ui_table_open(array $headers, array $opts = []): string
{
    $id    = isset($opts['id']) ? ' id="' . e($opts['id']) . '"' : '';
    $cls   = 'table' . (isset($opts['class']) ? ' ' . e($opts['class']) : '');
    $html  = '<div class="table-responsive"><table' . $id . ' class="' . $cls . '"><thead><tr>';
    foreach ($headers as $h) {
        if (is_array($h)) {
            $html .= '<th'
                   . (isset($h['style']) ? ' style="' . e($h['style']) . '"' : '')
                   . (isset($h['class']) ? ' class="' . e($h['class']) . '"' : '')
                   . '>' . ($h['label'] ?? '') . '</th>';
        } else {
            $html .= '<th>' . e($h) . '</th>';
        }
    }
    return $html . '</tr></thead><tbody>';
}

/**
 * @param array $rows  array of cell arrays or ['cells'=>[...],'class'=>'']
 */
function ui_table_rows(array $rows, int $colspan, array $opts = []): string
{
    if (empty($rows)) {
        $icon = $opts['empty_icon']  ?? 'inbox';
        $ttl  = $opts['empty_title'] ?? 'No records found';
        $msg  = $opts['empty_msg']   ?? 'Try adjusting your filters.';
        return '<tr><td colspan="' . $colspan . '">' . ui_empty($icon, $ttl, $msg) . '</td></tr>';
    }
    $html = '';
    foreach ($rows as $row) {
        if (is_array($row) && array_key_exists('cells', $row)) {
            $cls   = isset($row['class']) ? ' class="' . e($row['class']) . '"' : '';
            $cells = $row['cells'];
        } else {
            $cls = ''; $cells = $row;
        }
        $html .= '<tr' . $cls . '>';
        foreach ($cells as $cell) {
            $html .= is_array($cell)
                ? '<td' . (isset($cell['class']) ? ' class="' . e($cell['class']) . '"' : '')
                  . (isset($cell['style']) ? ' style="' . e($cell['style']) . '"' : '')
                  . '>' . ($cell['html'] ?? '') . '</td>'
                : '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    return $html;
}

function ui_table_close(): string
{
    return '</tbody></table></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// LIST FOOTER  (range + pagination, sits at bottom of a card)
// ══════════════════════════════════════════════════════════════════════════════

function ui_list_footer(int $page, int $pages, int $total, int $perPage, string $baseUrl): string
{
    if ($total === 0) return '';
    $from = ($page - 1) * $perPage + 1;
    $to   = min($page * $perPage, $total);
    $html = '<div class="list-footer"><span class="list-footer-range">'
          . number_format($from) . '–' . number_format($to)
          . ' of ' . number_format($total) . '</span>';
    if ($pages > 1) $html .= paginationLinks($page, $pages, $baseUrl);
    return $html . '</div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// DETAIL GRID  (label/value rows on view pages)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * @param array $items [['label'=>'','value'=>'','full'=>bool]]
 *   value is treated as HTML (pass e() yourself if it holds user data)
 */
function ui_details(array $items): string
{
    $html = '<div class="detail-row">';
    foreach ($items as $i => $it) {
        $cls = 'detail-item' . (!empty($it['full']) ? ' full' : '');
        $val = $it['value'] ?? '';
        if ($val === '') $val = '—';
        $html .= '<div class="' . $cls . '"><div class="detail-label">' . e($it['label']) . '</div>'
               . '<div class="detail-value">' . $val . '</div></div>';
    }
    return $html . '</div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// PROGRESS BREAKDOWN ROWS  (label — bar — count, dashboards/reports)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * @param array $items  ['Label' => ['val'=>int, 'color'=>'#hex or css color']]
 * @param int   $labelWidth px width of the label column
 */
function ui_progress_rows(array $items, int $labelWidth = 140): string
{
    $total = max(1, array_sum(array_column($items, 'val')));
    $html  = '';
    foreach ($items as $label => $it) {
        $pct = round($it['val'] / $total * 100);
        $html .= '<div class="progress-row">'
               . '<div class="progress-row-label" style="width:' . $labelWidth . 'px;">' . e($label) . '</div>'
               . '<div class="progress-row-track"><div class="progress-row-bar" style="width:' . $pct . '%;background:' . e($it['color']) . ';"></div></div>'
               . '<div class="progress-row-val">' . (int)$it['val'] . '</div>'
               . '</div>';
    }
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// MISC
// ══════════════════════════════════════════════════════════════════════════════

/** Monospace tracking/verification code chip — click to copy. */
function ui_code(string $code): string
{
    return '<span class="ui-code" data-copy="' . e($code) . '" title="Click to copy" role="button" tabindex="0">'
         . e($code) . '<i class="bi bi-copy"></i></span>';
}

/** Initials avatar circle. */
function ui_avatar(string $name, int $size = 36, string $variant = 'primary'): string
{
    $parts    = preg_split('/\s+/', trim($name));
    $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr(end($parts) ?: '', 0, 1));
    return '<span class="avatar-circle bg-' . e($variant) . ' text-white" '
         . 'style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . max(9, round($size * .36)) . 'px;">'
         . e($initials) . '</span>';
}

/**
 * Uniform form field. $f keys:
 *   type ('text','email','tel','date','number','select','textarea','password','file'),
 *   name, label, value, options, placeholder, help, required, col, attrs (extra attributes)
 */
function ui_field(array $f): string
{
    $type  = $f['type']  ?? 'text';
    $name  = $f['name'];
    $id    = 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
    $col   = $f['col']   ?? 'col-md-6';
    $req   = !empty($f['required']);
    $attrs = $f['attrs'] ?? '';
    if ($req) $attrs .= ' required';
    $html  = '<div class="' . e($col) . '">';
    if (($f['label'] ?? '') !== '') {
        $html .= '<label class="form-label" for="' . $id . '">' . e($f['label'])
               . ($req ? ' <span class="required-mark">*</span>' : '') . '</label>';
    }
    if ($type === 'select') {
        $html .= '<select name="' . e($name) . '" id="' . $id . '" class="form-select"' . $attrs . '>';
        foreach (($f['options'] ?? []) as $ov => $ol) {
            $sel = ((string)($f['value'] ?? '') === (string)$ov) ? ' selected' : '';
            $html .= '<option value="' . e($ov) . '"' . $sel . '>' . e($ol) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'textarea') {
        $rows = $f['rows'] ?? 3;
        $html .= '<textarea name="' . e($name) . '" id="' . $id . '" class="form-control" rows="' . (int)$rows
               . '" placeholder="' . e($f['placeholder'] ?? '') . '"' . $attrs . '>'
               . e($f['value'] ?? '') . '</textarea>';
    } else {
        $html .= '<input type="' . e($type) . '" name="' . e($name) . '" id="' . $id . '" class="form-control"'
               . ' value="' . e($f['value'] ?? '') . '" placeholder="' . e($f['placeholder'] ?? '') . '"' . $attrs . '>';
    }
    if (!empty($f['help']))  $html .= '<div class="form-text">' . e($f['help']) . '</div>';
    if (!empty($f['error'])) $html .= '<div class="invalid-feedback d-block">' . e($f['error']) . '</div>';
    return $html . '</div>';
}
