<?php
/**
 * TCC RRS – Groq AI integration
 * Page-aware, database-aware assistant powered by Groq's
 * OpenAI-compatible chat completions API. No SDK needed.
 *
 * Configure in Registrar → System Settings:
 *   ai_enabled, groq_api_key, groq_model
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Assistant is on when enabled AND a key is stored.
 */
function aiEnabled(): bool
{
    return getSetting('ai_enabled', '1') === '1'
        && getSetting('groq_api_key', '') !== '';
}

/**
 * Send a chat request to Groq. Returns the assistant text or null.
 * $messages: [['role'=>'system|user|assistant','content'=>'...'], ...]
 */
function groqChat(array $messages, ?string $model = null): ?string
{
    $key = getSetting('groq_api_key', '');
    if ($key === '') return null;

    $model = $model ?: (getSetting('groq_model') ?: 'openai/gpt-oss-120b');

    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.4,
        'max_tokens'  => 600,
    ]);

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'timeout' => 30,
        'header'  => "Content-Type: application/json\r\n"
                   . "Authorization: Bearer {$key}\r\n",
        'content' => $payload,
    ]]);

    $resp = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $ctx);
    if ($resp === false) return null;

    $data = json_decode($resp, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

/**
 * Build the system prompt: who the user is, what page they're on,
 * and the live registrar data they're allowed to ask about.
 */
function aiSystemPrompt(string $pageHint = ''): string
{
    $school = getSetting('school_name', 'Tampakan Community College');
    $lines  = [];
    $lines[] = "You are TCC Assist, the built-in assistant of the {$school} Registration & Records System (TCC RRS).";
    $lines[] = 'Answer helpfully and briefly (2-5 sentences or a short list). Use plain language.';
    $lines[] = 'Format with light markdown: **bold** for key terms/codes, short bullet lists for steps, numbered lists for sequences. No headers, no tables, no horizontal rules.';
    $lines[] = 'Only discuss registrar/records topics: pre-registration, document requests, tracking, verification, accounts, office procedures. Politely decline anything else.';
    $lines[] = 'Never invent tracking codes, fees, or statuses — use only the data below.';

    // Live catalog
    $docs = getActiveDocumentTypes();
    if ($docs) {
        $cat = array_map(fn($d) => $d['name'] . ' (' . formatCurrency((float)$d['fee'])
                 . ', ~' . (int)$d['processing_days'] . 'd)', $docs);
        $lines[] = 'Documents offered: ' . implode('; ', $cat) . '.';
    }
    $progs = getActivePrograms();
    if ($progs) {
        $lines[] = 'Programs: ' . implode(', ', array_column($progs, 'code')) . '.';
    }
    $lines[] = 'Current school year: ' . getSetting('current_school_year', '—')
             . ', ' . getSetting('current_semester', '—') . '.';

    // Page awareness
    if ($pageHint !== '') {
        $lines[] = "The user is currently on: {$pageHint}.";
    }

    // Role + personal records
    if (isStudentLoggedIn() && ($stu = currentStudent())) {
        $lines[] = "User: a logged-in STUDENT, {$stu['first_name']} {$stu['last_name']}"
                 . ($stu['student_no'] ? " ({$stu['student_no']})" : '') . '.';
        $subs = db()->fetchAll(
            "SELECT 'registration' AS kind, tracking_code, status, submitted_at FROM pre_registrations WHERE email = ?
             UNION ALL
             SELECT 'document request', tracking_code, status, submitted_at FROM document_requests WHERE email = ?
             ORDER BY submitted_at DESC LIMIT 10",
            [$stu['email'], $stu['email']]
        );
        if ($subs) {
            $desc = array_map(fn($s) => "{$s['kind']} {$s['tracking_code']} — {$s['status']}", $subs);
            $lines[] = 'Their submissions: ' . implode('; ', $desc) . '.';
        } else {
            $lines[] = 'They have no submissions on record yet.';
        }
    } elseif (isLoggedIn()) {
        $u = currentUser();
        $lines[] = 'User: a logged-in ' . strtoupper($u['role']) . " member, {$u['full_name']}.";
        $counts = db()->fetchOne(
            "SELECT
               (SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 0) AS regs,
               (SELECT COUNT(*) FROM document_requests) AS docs,
               (SELECT COUNT(*) FROM pre_registrations WHERE status = 'Pending') AS pend_regs,
               (SELECT COUNT(*) FROM document_requests WHERE status = 'Pending') AS pend_docs"
        );
        if ($counts) {
            $lines[] = "System: {$counts['regs']} registrations ({$counts['pend_regs']} pending), "
                     . "{$counts['docs']} document requests ({$counts['pend_docs']} pending).";
        }
    } else {
        $lines[] = 'User: a public visitor (not signed in). Do not reveal any personal records.';
    }

    return implode("\n", $lines);
}
