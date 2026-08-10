<?php

/**
 * =========================================================
 * Gemini女優紹介文 自動生成機能
 * =========================================================
 */

/* ---------- 1. Gemini API呼び出し ---------- */

/**
 * Geminiで女優紹介文を生成する
 * 429（クォータ超過）時は翌日まで自動的に呼び出しを停止する
 * 403（プロジェクトのアクセス拒否）時は手動解除まで停止する
 *
 * @param array $actress_data 女優情報
 * @return string|WP_Error
 */
function generate_actress_intro_with_gemini(array $actress_data)
{
    // 停止期間中なら即座にエラーを返す（API自体を呼ばない）
    $pause_until = (int) get_option('gemini_pause_until', 0);
    if ($pause_until > time()) {
        return new WP_Error(
            'gemini_paused',
            sprintf('Gemini APIは %s まで停止中です（クォータ超過のため）。', date_i18n('Y-m-d H:i', $pause_until))
        );
    }

    // アクセス拒否フラグが立っている間は手動解除まで停止
    if (get_option('gemini_access_denied', 0)) {
        return new WP_Error('gemini_access_denied', 'Gemini APIへのアクセスが拒否されています。プロジェクト状態を確認し、解決後に手動で解除してください。');
    }

    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : get_option('gemini_api_key');
    if (empty($api_key)) {
        return new WP_Error('gemini_no_api_key', 'Gemini APIキーが設定されていません。');
    }

    $prompt = sprintf(
        "以下の女優情報を元に、アフィリエイトサイト向けの魅力的なオリジナル紹介文（200文字程度）を作成してください。\n" .
            "公式の文面を丸写しせず、要約とアピールポイントを中心に読者の興味を引く自然な文章にしてください。\n\n" .
            "【女優情報】\n" .
            "・女優名: %s\n" .
            "・スタイル: 身長 %scm B%s(%s) / W%s / H%s\n" .
            "・生年月日: %s (%s)\n" .
            "・血液型: %s型\n" .
            "・趣味: %s\n\n" .
            "【出力形式について（厳守）】\n" .
            "・紹介文の本文のみを出力してください。\n" .
            "・見出し、タイトル、箇条書き、区切り線（---など）、アピールポイントの解説、前置きや後書きの挨拶文は一切含めないでください。\n" .
            "・Markdown記法（**太字**など）や文字数の注記も付けないでください。\n" .
            "・出力は紹介文の地の文だけで完結させてください。\n",
        $actress_data['name']       ?? '',
        $actress_data['height']     ?? '',
        $actress_data['bust']       ?? '',
        $actress_data['cup']        ?? '',
        $actress_data['waist']      ?? '',
        $actress_data['hip']        ?? '',
        $actress_data['birthday']   ?? '',
        isset($actress_data['age']) ? $actress_data['age'] . '歳' : '',
        $actress_data['blood_type'] ?? '',
        $actress_data['hobby']      ?? ''
    );

    $model    = 'gemini-3.5-flash';
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;

    $body = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature'     => 0.9,
            'maxOutputTokens' => 2048,
            // 思考(thinking)トークンがmaxOutputTokensの枠を消費し本文が途中で切れる問題への対処
            'thinkingConfig'  => ['thinkingBudget' => 0],
        ],
    ];

    $max_attempts = 3;

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $result      = json_decode(wp_remote_retrieve_body($response), true);

    // gemini_debug_log(print_r([
    //     'status_code' => $status_code,
    //     'actress_data' => $actress_data,
    //     'result'      => $result,
    // ], true));


        // 403: プロジェクト単位のアクセス拒否 → 自動再開させず、手動解除が必要な停止状態にする
        if (403 === $status_code) {
            update_option('gemini_access_denied', 1, false);
            $message = $result['error']['message'] ?? 'Gemini APIプロジェクトへのアクセスが拒否されました。';
            return new WP_Error('gemini_access_denied', $message, $result);
        }

        // 429: レート制限 or クォータ超過
        if (429 === $status_code) {
            $message = $result['error']['message'] ?? 'Gemini APIのレート制限/クォータに達しました。';

            $has_retry_delay = preg_match('/retry in ([0-9.]+)s/i', $message, $matches);

            // "PerDay"の文言がある、または秒単位のretry指定が無い場合は
            // 日次クォータ超過とみなし、リトライせず即座に翌日まで停止する
            $is_daily_limit = (false !== stripos($message, 'PerDay')) || ! $has_retry_delay;

            if ($is_daily_limit) {
                gemini_set_pause_until_tomorrow();
                return new WP_Error('gemini_rate_limited', $message, $result);
            }

            // "retry in 4.4s" のような秒数指定がある短時間の制限のみ、その場でリトライする
            $retry_seconds = (int) ceil((float) $matches[1]) + 2;

            if ($attempt < $max_attempts) {
                sleep($retry_seconds);
                continue; // このバッチ内でその場リトライ
            }

            update_option('gemini_pause_until', time() + $retry_seconds, false);
            return new WP_Error('gemini_rate_limited', $message, $result);
        }

        // 503: モデル側の一時的な高負荷 → 短時間待ってその場でリトライ
        if (503 === $status_code) {
            $message = $result['error']['message'] ?? 'Geminiモデルが混雑しています。';

            if ($attempt < $max_attempts) {
                sleep(10 * $attempt); // 10秒, 20秒と間隔を広げながらリトライ
                continue;
            }

            update_option('gemini_pause_until', time() + 60, false);
            return new WP_Error('gemini_rate_limited', $message, $result);
        }

        if ($status_code !== 200) {
            $message = $result['error']['message'] ?? 'Gemini APIエラー';
            return new WP_Error('gemini_api_error', $message, $result);
        }

        $finish_reason = $result['candidates'][0]['finishReason'] ?? '';
        $text          = trim($result['candidates'][0]['content']['parts'][0]['text'] ?? '');

        // MAX_TOKENSで途中打ち切りになった場合、maxOutputTokensを増やしてリトライ
        if ('MAX_TOKENS' === $finish_reason && $attempt < $max_attempts) {
            $body['generationConfig']['maxOutputTokens'] += 512;
            continue;
        }

        $text = gemini_clean_intro_text($text);

        if ('' === $text) {
            return new WP_Error('gemini_empty_response', 'Geminiから紹介文を取得できませんでした。');
        }

        return $text;
    }

    return new WP_Error('gemini_api_error', 'Gemini APIへのリクエストが規定回数失敗しました。');
}

/**
 * Geminiの出力から、見出し・区切り線・解説パートなどの余計な装飾を取り除く
 * （プロンプトで抑制済みだが、念のための後処理）
 *
 * @param string $text Geminiからの生テキスト
 * @return string
 */
function gemini_clean_intro_text(string $text): string
{
    // "---" 以降（解説パートなど）が付いていたら、最初のブロックだけを本文とみなす
    $parts = preg_split('/\n\s*-{3,}\s*\n/', $text);
    $text  = trim($parts[0]);

    // 見出し行（【】や##、**で始まる行）を除去
    $lines = preg_split('/\r?\n/', $text);
    $lines = array_filter($lines, function ($line) {
        $line = trim($line);
        if ('' === $line) {
            return false;
        }
        if (preg_match('/^[【#*]/u', $line)) {
            return false;
        }
        return true;
    });

    $text = implode("\n", $lines);

    // Markdownの太字記法(**text**)だけ除去し、中身は残す
    $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text);

    return trim($text);
}

/**
 * 「翌日の太平洋時間 0:00」を計算し、gemini_pause_until オプションに保存する
 */
function gemini_set_pause_until_tomorrow()
{
    $pacific_now      = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $pacific_tomorrow = (clone $pacific_now)->modify('+1 day')->setTime(0, 0, 0);
    update_option('gemini_pause_until', $pacific_tomorrow->getTimestamp(), false);
}


/* ---------- 2. 未処理女優の抽出 ---------- */

/**
 * 未処理（紹介文がまだ生成されていない）女優termを抽出する
 *
 * @param int $limit 取得件数（1回のバッチ処理量。クォータ対策で制限する）
 * @return WP_Term[]
 */
function get_unprocessed_actresses(int $limit = 20): array
{
    $terms = get_terms([
        'taxonomy'   => 'actress',
        'hide_empty' => false,
        'number'     => $limit,
        'meta_query' => [
            [
                'key'     => '_ai_intro_generated',
                'compare' => 'NOT EXISTS', // このメタキーが無い＝未処理
            ],
        ],
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return $terms;
}


/* ---------- 3. バッチ処理本体 ---------- */

/**
 * 女優データのプロフィール項目（身長・バスト・カップ・ウエスト・ヒップ・生年月日）が
 * すべて空かどうかを判定する
 *
 * @param array $actress_data 女優情報
 * @return bool true: すべて空（データ未入力）
 */
function is_actress_profile_empty(array $actress_data): bool
{
    $keys = ['height', 'bust', 'cup', 'waist', 'hip', 'birthday'];

    foreach ($keys as $key) {
        if ('' !== trim((string) ($actress_data[$key] ?? ''))) {
            return false; // 1つでも値があれば「空ではない」
        }
    }

    return true;
}

/**
 * 未処理女優をまとめて処理する（抽出→生成→保存→フラグ更新）
 *
 * @param int $limit        1回のバッチ処理量
 * @param int $sleep_seconds 各リクエストの間に空ける秒数（無料枠のRPM対策。デフォルト20秒）
 * @return array 処理結果ログ
 */
function process_unprocessed_actresses(int $limit = 20, int $sleep_seconds = 20): array
{
    $terms         = get_unprocessed_actresses($limit);
    $log           = [];
    $api_called    = false; // 実際にAPIを呼んだ場合のみ、次回にsleepを入れる

    foreach ($terms as $term) {
        // フィールド名は実際の登録名に合わせて調整してください
        if (! empty(get_term_meta($term->term_id, 'actress_birthday', true))) {
            $birth_date = DateTime::createFromFormat('Y-m-d', get_term_meta($term->term_id, 'actress_birthday', true));
            if ($birth_date) {
                $today = new DateTime('today');
                $term->_age = $today->diff($birth_date)->y;
            }
        }

        $actress_data = [
            'name'       => $term->name,
            'height'     => get_term_meta($term->term_id, 'actress_height', true),
            'bust'       => get_term_meta($term->term_id, 'actress_bust', true),
            'cup'        => get_term_meta($term->term_id, 'actress_cup', true),
            'waist'      => get_term_meta($term->term_id, 'actress_waist', true),
            'hip'        => get_term_meta($term->term_id, 'actress_hip', true),
            'birthday'   => get_term_meta($term->term_id, 'actress_birthday', true),
            'age'        => $term->_age,
            'blood_type' => get_term_meta($term->term_id, 'actress_blood_type', true),
            'hobby'      => get_term_meta($term->term_id, 'actress_hobby', true),
        ];

        // プロフィール項目がすべて空の場合はAPIを呼ばずスキップ（データが入るまで未処理のまま残す）
        if (is_actress_profile_empty($actress_data)) {
            $log[] = [
                'term_id' => $term->term_id,
                'status'  => 'skipped',
                'message' => 'プロフィール情報がすべて未入力のためスキップしました。',
            ];
            continue;
        }

        // 2件目以降、実際にAPIを呼ぶ直前でのみ待機時間を入れる（RPM制限対策）
        if ($api_called && $sleep_seconds > 0) {
            sleep($sleep_seconds);
        }

        $intro      = generate_actress_intro_with_gemini($actress_data);
        $api_called = true;

        if (is_wp_error($intro)) {
            $log[] = [
                'term_id' => $term->term_id,
                'slug'    => $term->slug,
                'dmm_id'  => get_term_meta($term->term_id, 'actress_dmm_id', true),
                'status'  => 'error',
                'message' => $intro->get_error_message(),
            ];

            // レート制限/停止中/アクセス拒否なら、このバッチはここで中断
            if (in_array($intro->get_error_code(), ['gemini_rate_limited', 'gemini_paused', 'gemini_access_denied'], true)) {
                break;
            }
            continue;
        }

        update_term_meta($term->term_id, 'the_tag_content', $intro); // Cocoonの「本文」フィールド
        update_term_meta($term->term_id, '_ai_intro_generated', 1); // 処理済みフラグ

        $log[] = [
            'term_id' => $term->term_id,
            'slug'    => $term->slug,
            'dmm_id'  => get_term_meta($term->term_id, 'actress_dmm_id', true),
            'status'  => 'success',
        ];
    }

    // print_r($log); // デバッグ用。不要なら削除してください。

    return $log;
}


/* ---------- 4. Action Scheduler 設定 ---------- */

const GEMINI_ACTRESS_INTRO_HOOK = 'gemini_actress_intro_batch';

/**
 * 定期実行アクションの登録（未登録の場合のみ）
 * FANZA Syncと同様、init または plugins_loaded で呼び出す
 */
function schedule_gemini_actress_intro_action()
{
    if (! function_exists('as_next_scheduled_action')) {
        return; // Action Schedulerがまだ読み込まれていない場合は何もしない
    }

    if (false === as_next_scheduled_action(GEMINI_ACTRESS_INTRO_HOOK)) {
        $jst_tomorrow_3am = new DateTime('tomorrow 03:00', new DateTimeZone('Asia/Tokyo'));

        as_schedule_recurring_action(
            $jst_tomorrow_3am->getTimestamp(),
            HOUR_IN_SECONDS,
            GEMINI_ACTRESS_INTRO_HOOK,
            [],
            'gemini-actress-intro'
        );
    }
}
add_action('init', 'schedule_gemini_actress_intro_action');

/**
 * デバッグログをwp-content/logs/配下に日本語がそのまま読める形式で書き出す
 *
 * @param mixed  $message 文字列 または 配列・オブジェクト（print_rで整形出力される）
 * @param string $label   配列・オブジェクトを出力する際、先頭に付ける見出し文言（任意）
 */
function gemini_debug_log($message, string $label = '')
{
    $log_dir = WP_CONTENT_DIR . '/logs';

    if (! is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    $log_file = sprintf(
        '%s/gemini_debug_%s.log',
        $log_dir,
        date('Ymd')
    );

    $body = is_scalar($message)
        ? $message
        : print_r($message, true); // 配列・オブジェクトはprint_rで整形

    $line = sprintf(
        "[%s] %s%s\n",
        current_time('Y-m-d H:i:s'),
        '' !== $label ? $label . "\n" : '',
        $body
    );

    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * フック実行時の処理本体
 * gemini_pause_until / gemini_access_denied が有効な間は process_unprocessed_actresses 内で
 * 即座にWP_Errorが返り、API呼び出しは発生しないため空振り運転でも問題ない
 */
add_action(GEMINI_ACTRESS_INTRO_HOOK, function () {
    // sleepを挟む分、実行時間が延びるためPHPのタイムアウトを解除しておく
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }

    $log = process_unprocessed_actresses(20);

    if (! empty($log)) {
        gemini_debug_log($log, 'gemini_actress_intro_batch result:');
    }
});


/* ---------- 5. 管理画面から手動実行 ---------- */

add_action('admin_menu', function () {
    add_management_page(
        'Gemini 女優紹介文 手動実行',
        'Gemini 女優紹介文',
        'manage_options',
        'gemini-actress-intro-run',
        'gemini_actress_intro_admin_page'
    );
});

function gemini_actress_intro_admin_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $log = null;

    if (
        isset($_POST['gemini_actress_intro_run'])
        && check_admin_referer('gemini_actress_intro_run_action', 'gemini_actress_intro_nonce')
    ) {
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 20;
        $log   = process_unprocessed_actresses($limit);
    }

?>
    <div class="wrap">
        <h1>Gemini 女優紹介文 手動実行</h1>

        <?php
        $pause_until = (int) get_option('gemini_pause_until', 0);
        if ($pause_until > time()) {
            echo '<div class="notice notice-warning"><p>現在 ' . esc_html(date_i18n('Y-m-d H:i', $pause_until)) . ' まで停止中です（クォータ超過等）。</p></div>';
        }
        if (get_option('gemini_access_denied', 0)) {
            echo '<div class="notice notice-error"><p>アクセス拒否フラグが立っています。原因解消後、下のボタンで解除してください。</p></div>';
        }
        ?>

        <form method="post">
            <?php wp_nonce_field('gemini_actress_intro_run_action', 'gemini_actress_intro_nonce'); ?>
            <p>
                <label>処理件数上限:
                    <input type="number" name="limit" value="20" min="1" max="200">
                </label>
            </p>
            <p>
                <button type="submit" name="gemini_actress_intro_run" value="1" class="button button-primary">
                    未処理女優を処理する
                </button>
            </p>
        </form>

        <form method="post" onsubmit="return confirm('停止フラグを解除しますか？');">
            <?php wp_nonce_field('gemini_actress_intro_clear_action', 'gemini_actress_intro_clear_nonce'); ?>
            <button type="submit" name="gemini_actress_intro_clear" value="1" class="button">
                停止フラグを解除する
            </button>
        </form>

        <?php if (null !== $log): ?>
            <h2>実行結果</h2>
            <?php
            $success_count = count(array_filter($log, fn($row) => $row['status'] === 'success'));
            $error_count   = count($log) - $success_count;
            ?>
            <p>成功: <?php echo (int) $success_count; ?> / エラー: <?php echo (int) $error_count; ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Term ID</th>
                        <th>結果</th>
                        <th>メッセージ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['term_id']); ?></td>
                            <td><?php echo esc_html($row['status']); ?></td>
                            <td><?php echo esc_html($row['message'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php
}

// 停止フラグ解除処理
add_action('admin_init', function () {
    if (
        isset($_POST['gemini_actress_intro_clear'])
        && check_admin_referer('gemini_actress_intro_clear_action', 'gemini_actress_intro_clear_nonce')
        && current_user_can('manage_options')
    ) {
        delete_option('gemini_pause_until');
        delete_option('gemini_access_denied');
        wp_safe_redirect(admin_url('tools.php?page=gemini-actress-intro-run'));
        exit;
    }
});


/* ---------- 6. WP-CLI コマンド ---------- */

if (defined('WP_CLI') && WP_CLI) {

    /**
     * 未処理女優の紹介文をGeminiで一括生成する
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : 処理件数の上限
     * ---
     * default: 20
     * ---
     *
     * [--sleep=<seconds>]
     * : リクエスト間のスリープ秒数（無料枠のRPM対策）
     * ---
     * default: 20
     * ---
     *
     * [--clear-pause]
     * : 実行前に停止フラグ（レート制限/アクセス拒否）を解除する
     *
     * ## EXAMPLES
     *
     *     wp gemini-actress-intro run
     *     wp gemini-actress-intro run --limit=50
     *     wp gemini-actress-intro run --sleep=15
     *     wp gemini-actress-intro run --clear-pause
     *
     * @when after_wp_load
     */
    WP_CLI::add_command('gemini-actress-intro run', function ($args, $assoc_args) {
        if (! empty($assoc_args['clear-pause'])) {
            delete_option('gemini_pause_until');
            delete_option('gemini_access_denied');
            WP_CLI::log('停止フラグを解除しました。');
        }

        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 20;
        $sleep = isset($assoc_args['sleep']) ? (int) $assoc_args['sleep'] : 20;
        $log   = process_unprocessed_actresses($limit, $sleep);

        $success = count(array_filter($log, fn($row) => $row['status'] === 'success'));
        $error   = count($log) - $success;

        WP_CLI::log(sprintf('処理件数: %d件（成功: %d / エラー: %d）', count($log), $success, $error));

        foreach ($log as $row) {
            if ($row['status'] === 'error') {
                WP_CLI::warning(sprintf('term_id=%d: %s', $row['term_id'], $row['message']));
            }
        }

        if (empty($log)) {
            WP_CLI::success('未処理の女優はありませんでした。');
        }
    });
}