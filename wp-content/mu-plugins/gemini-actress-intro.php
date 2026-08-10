<?php

/**
 * =========================================================
 * Gemini女優紹介文 自動生成機能
 * =========================================================
 *
 * レート制限対策として、1件ずつではなく5件まとめて1回のAPIコールで
 * 紹介文を生成する（バッチ生成）。
 * ・API呼び出し部分は GeminiClient クラスに共通化
 *   （generateSingle / generateBatch / retry / parseJson）
 * ・5件中1〜4件だけレスポンスに含まれなかった場合は、その分だけ
 *   従来方式（1件ずつ）で個別に再生成するフォールバックを行う
 * ・5件全部失敗した場合のみエラー扱いとする
 */

/* ---------- 1. Gemini APIクライアント ---------- */

/**
 * Gemini APIへのリクエストをまとめて担当するクライアント
 * ・単体生成 generateSingle() … バッチで取得できなかった分のフォールバック用（従来方式）
 * ・一括生成 generateBatch() … 5件分をまとめて1回のAPIコールで生成
 * ・retry()  … 429/403/503やMAX_TOKENSのリトライ制御を共通化
 * ・parseJson() … Geminiの応答からコードフェンスや前後の文章を除いてJSONだけを抽出する
 */
class GeminiClient
{
    /** 使用するGeminiモデル（レート制限対策のため軽量モデルを使用） */
    private const MODEL = 'gemini-3.5-flash-lite';

    /** 1回のバッチ生成で処理する人数 */
    public const BATCH_SIZE = 5;

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * 女優1人分の紹介文を生成する（バッチで取得できなかった分のフォールバック＝従来方式）
     *
     * @param array $actress_data 女優情報
     * @return string|WP_Error
     */
    public function generateSingle(array $actress_data)
    {
        $prompt = $this->buildSinglePrompt($actress_data);

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

        $result = $this->retry($body);

        if (is_wp_error($result)) {
            return $result;
        }

        $text = trim($result['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $text = gemini_clean_intro_text($text);

        if ('' === $text) {
            return new WP_Error('gemini_empty_response', 'Geminiから紹介文を取得できませんでした。');
        }

        return $text;
    }

    /**
     * 女優複数人分（最大 BATCH_SIZE 人）の紹介文を1回のAPIコールでまとめて生成する
     *
     * @param array $items [ ['term_id' => int, 'data' => array], ... ]
     * @return array|WP_Error 成功時: [term_id => intro, ...]
     *                        （レスポンスに含まれなかった/空だったterm_idはキーごと含まれない）
     */
    public function generateBatch(array $items)
    {
        $prompt = $this->buildBatchPrompt($items);

        $body = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => 0.9,
                'maxOutputTokens'  => 4096,
                // 思考(thinking)トークンがmaxOutputTokensの枠を消費し本文が途中で切れる問題への対処
                'thinkingConfig'   => ['thinkingBudget' => 0],
                // JSON配列で返させることで、複数人分の紹介文をterm_idと対応付けて一括取得する
                'responseMimeType' => 'application/json',
            ],
        ];

        $result = $this->retry($body);

        if (is_wp_error($result)) {
            return $result;
        }

        $text         = trim($result['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $items_parsed = self::parseJson($text);

        if (null === $items_parsed) {
            return new WP_Error('gemini_invalid_json', 'Geminiの応答からJSONを抽出できませんでした。', $text);
        }

        // term_idをキーにして [term_id => intro] の連想配列に整形する
        $results = [];
        foreach ($items_parsed as $row) {
            $term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
            $intro   = gemini_clean_intro_text(trim((string) ($row['intro'] ?? '')));

            if ($term_id > 0 && '' !== $intro) {
                $results[$term_id] = $intro;
            }
        }

        return $results;
    }

    /**
     * Gemini APIへのリクエストをリトライ制御付きで実行する（単体/バッチ共通）
     * 429（クォータ超過）時は翌日まで自動的に呼び出しを停止する
     * 403（プロジェクトのアクセス拒否）時は手動解除まで停止する
     * 503（一時的な高負荷）は間隔を空けてその場でリトライする
     * MAX_TOKENSで打ち切られた場合はmaxOutputTokensを増やしてリトライする
     *
     * @param array $body         リクエストボディ（generationConfigを含む）
     * @param int   $max_attempts 最大試行回数
     * @return array|WP_Error 成功時はデコード済みレスポンス配列
     */
    private function retry(array $body, int $max_attempts = 3)
    {
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            self::MODEL,
            $this->api_key
        );

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
            //     'body'        => $body,
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

            // MAX_TOKENSで途中打ち切りになった場合、maxOutputTokensを増やしてリトライ
            if ('MAX_TOKENS' === $finish_reason && $attempt < $max_attempts) {
                $body['generationConfig']['maxOutputTokens'] += 512;
                continue;
            }

            return $result;
        }

        return new WP_Error('gemini_api_error', 'Gemini APIへのリクエストが規定回数失敗しました。');
    }

    /**
     * Geminiの応答テキストからJSONだけを抽出してデコードする
     * ```json ... ``` のコードフェンスや、前後に説明文が付与されている場合でも対応する
     *
     * @param string $text Geminiからの生テキスト
     * @return array|null 抽出・デコードに失敗した場合はnull
     */
    private static function parseJson(string $text): ?array
    {
        $text = trim($text);

        // ```json ... ``` / ``` ... ``` のコードフェンスが付いていれば中身だけを取り出す
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $matches)) {
            $text = trim($matches[1]);
        }

        // まずはそのままデコードを試す
        $decoded = json_decode($text, true);
        if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
            return $decoded;
        }

        // 前後に説明文が付いている場合に備え、最初の "[" または "{" から
        // 対応する最後の "]" または "}" までを抜き出して再デコードする
        $start = null;
        foreach (['[', '{'] as $char) {
            $pos = strpos($text, $char);
            if (false !== $pos && (null === $start || $pos < $start)) {
                $start = $pos;
            }
        }

        if (null === $start) {
            return null;
        }

        $close = ('[' === $text[$start]) ? ']' : '}';
        $end   = strrpos($text, $close);

        if (false === $end || $end < $start) {
            return null;
        }

        $candidate = substr($text, $start, $end - $start + 1);
        $decoded   = json_decode($candidate, true);

        return (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) ? $decoded : null;
    }

    /**
     * 単一女優用プロンプトを生成する（フォールバック時に使用）
     */
    private function buildSinglePrompt(array $actress_data): string
    {
        return sprintf(
            "以下の女優情報を元に、アフィリエイトサイト向けの魅力的なオリジナル紹介文（200文字程度）を作成してください。\n" .
                "公式の文面を丸写しせず、要約とアピールポイントを中心に読者の興味を引く自然な文章にしてください。\n\n" .
                "%s\n\n" .
                "【出力形式について（厳守）】\n" .
                "・紹介文の本文のみを出力してください。\n" .
                "・見出し、タイトル、箇条書き、区切り線（---など）、アピールポイントの解説、前置きや後書きの挨拶文は一切含めないでください。\n" .
                "・Markdown記法（**太字**など）や文字数の注記も付けないでください。\n" .
                "・出力は紹介文の地の文だけで完結させてください。\n",
            self::formatActressInfo($actress_data)
        );
    }

    /**
     * バッチ（複数人まとめて）用プロンプトを生成する
     * term_idごとに紹介文をJSON配列で対応付けさせる
     *
     * @param array $items [ ['term_id' => int, 'data' => array], ... ]
     */
    private function buildBatchPrompt(array $items): string
    {
        $blocks = [];
        foreach ($items as $item) {
            $blocks[] = sprintf("term_id: %d\n%s", $item['term_id'], self::formatActressInfo($item['data']));
        }

        return sprintf(
            "以下は複数の女優情報です。それぞれについて、アフィリエイトサイト向けの魅力的なオリジナル紹介文（200文字程度）を作成してください。\n" .
                "公式の文面を丸写しせず、要約とアピールポイントを中心に読者の興味を引く自然な文章にしてください。\n\n" .
                "%s\n\n" .
                "【出力形式について（厳守）】\n" .
                "・必ず下記の例と同じ形式のJSON配列のみを出力してください。前後に説明文やコードフェンス（```）は付けないでください。\n" .
                "・各要素のterm_idは入力の値をそのまま使ってください。introには紹介文の本文のみを入れ、見出し・箇条書き・区切り線・Markdown記法・前置きや後書きの挨拶文は一切含めないでください。\n" .
                "・入力された女優は全員分、漏れなく出力してください。\n\n" .
                "[\n" .
                "  {\"term_id\": 123, \"intro\": \"...\"},\n" .
                "  {\"term_id\": 456, \"intro\": \"...\"}\n" .
                "]\n",
            implode("\n\n", $blocks)
        );
    }

    /**
     * プロンプトに埋め込む女優情報のテキストブロックを作成する（単体/バッチ共通）
     */
    private static function formatActressInfo(array $actress_data): string
    {
        return sprintf(
            "【女優情報】\n" .
                "・女優名: %s\n" .
                "・スタイル: 身長 %scm B%s(%s) / W%s / H%s\n" .
                "・生年月日: %s (%s)\n" .
                "・血液型: %s型\n" .
                "・趣味: %s",
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
    }
}

/**
 * 停止中/アクセス拒否中/APIキー未設定であればWP_Errorを返し、
 * そうでなければ呼び出し可能なGeminiClientインスタンスを返す
 *
 * @return GeminiClient|WP_Error
 */
function gemini_get_client()
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

    return new GeminiClient($api_key);
}

/**
 * Geminiで女優紹介文を生成する（後方互換のためのラッパー関数）
 * 主にバッチ生成で取得できなかった分の、個別フォールバック生成に使用する
 *
 * @param array $actress_data 女優情報
 * @return string|WP_Error
 */
function generate_actress_intro_with_gemini(array $actress_data)
{
    $client = gemini_get_client();

    if (is_wp_error($client)) {
        return $client;
    }

    return $client->generateSingle($actress_data);
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
 * プロフィール項目（身長・バスト・カップ・ウエスト・ヒップ・生年月日・血液型・趣味）が
 * すべて空のtermはここで除外する。先頭から$limit件を取得したときに全件データなしで
 * スキップされてしまい、次回以降も同じ（処理不可能な）termばかり取得され続ける事態を防ぐ。
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
            'relation' => 'AND',
            [
                'key'     => '_ai_intro_generated',
                'compare' => 'NOT EXISTS', // このメタキーが無い＝未処理
            ],
            [
                // プロフィール項目のいずれかに値が入っているtermだけに絞り込む
                // （フィールド名は実際の登録名に合わせて調整してください）
                'relation' => 'OR',
                ['key' => 'actress_height',     'value' => '', 'compare' => '!='],
                ['key' => 'actress_bust',       'value' => '', 'compare' => '!='],
                ['key' => 'actress_cup',        'value' => '', 'compare' => '!='],
                ['key' => 'actress_waist',      'value' => '', 'compare' => '!='],
                ['key' => 'actress_hip',        'value' => '', 'compare' => '!='],
                ['key' => 'actress_birthday',   'value' => '', 'compare' => '!='],
                ['key' => 'actress_blood_type', 'value' => '', 'compare' => '!='],
                ['key' => 'actress_hobby',      'value' => '', 'compare' => '!='],
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
 * 未処理女優をまとめて処理する（抽出→バッチ生成→保存→フラグ更新）
 *
 * $limit 件を取得したうえで GeminiClient::BATCH_SIZE（5件）ずつに分割し、
 * チャンクごとに1回のAPIコールでまとめて紹介文を生成する。
 * バッチ応答に含まれなかった分（5件中1〜4件の失敗）だけ、従来方式で個別に再生成する。
 * 5件全部失敗した場合のみエラー扱いとする。
 *
 * @param int $limit         1回のバッチ処理量（取得件数）
 * @param int $sleep_seconds 各APIリクエストの間に空ける秒数（無料枠のRPM対策。デフォルト20秒）
 * @return array 処理結果ログ
 */
function process_unprocessed_actresses(int $limit = 20, int $sleep_seconds = 20): array
{
    $terms      = get_unprocessed_actresses($limit);
    $log        = [];
    $api_called = false; // 実際にAPIを呼んだ場合のみ、次回にsleepを入れる

    // ---- 女優データの組み立て＆プロフィール空チェック（スキップ分はAPIを呼ばない） ----
    $pending = []; // API呼び出し対象 [ ['term_id'=>.., 'term'=>WP_Term, 'data'=>array], ... ]

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
        // ※ get_unprocessed_actresses() 側のmeta_queryで既に除外している想定だが、念のための保険チェック
        if (is_actress_profile_empty($actress_data)) {
            $log[] = [
                'term_id' => $term->term_id,
                'status'  => 'skipped',
                'message' => 'プロフィール情報がすべて未入力のためスキップしました。',
            ];
            continue;
        }

        $pending[] = [
            'term_id' => $term->term_id,
            'term'    => $term,
            'data'    => $actress_data,
        ];
    }

    if (empty($pending)) {
        return $log;
    }

    $client = gemini_get_client();

    // 停止中/キー未設定などで最初からAPIを呼べない場合は、対象全件にエラーを記録して終了
    if (is_wp_error($client)) {
        foreach ($pending as $item) {
            $log[] = [
                'term_id' => $item['term_id'],
                'slug'    => $item['term']->slug,
                'dmm_id'  => get_term_meta($item['term_id'], 'actress_dmm_id', true),
                'status'  => 'error',
                'message' => $client->get_error_message(),
            ];
        }
        return $log;
    }

    // ---- GeminiClient::BATCH_SIZE 件ずつのチャンクに分割してバッチ生成 ----
    foreach (array_chunk($pending, GeminiClient::BATCH_SIZE) as $chunk) {
        // 2回目以降、実際にAPIを呼ぶ直前でのみ待機時間を入れる（RPM制限対策）
        if ($api_called && $sleep_seconds > 0) {
            sleep($sleep_seconds);
        }

        $batch_items  = array_map(
            fn($item) => ['term_id' => $item['term_id'], 'data' => $item['data']],
            $chunk
        );
        $batch_result = $client->generateBatch($batch_items);
        $api_called   = true;

        if (is_wp_error($batch_result)) {
            // バッチ呼び出し自体が失敗＝このチャンクの全件が失敗扱い
            foreach ($chunk as $item) {
                $log[] = [
                    'term_id' => $item['term_id'],
                    'slug'    => $item['term']->slug,
                    'dmm_id'  => get_term_meta($item['term_id'], 'actress_dmm_id', true),
                    'status'  => 'error',
                    'message' => $batch_result->get_error_message(),
                ];
            }

            // レート制限/停止中/アクセス拒否なら、以降のチャンクも処理せず中断
            if (in_array($batch_result->get_error_code(), ['gemini_rate_limited', 'gemini_paused', 'gemini_access_denied'], true)) {
                break;
            }
            continue;
        }

        // ---- チャンク内で結果を保存し、レスポンスに含まれなかった分を洗い出す ----
        $failed_items = [];

        foreach ($chunk as $item) {
            $term_id = $item['term_id'];

            if (isset($batch_result[$term_id])) {
                update_term_meta($term_id, 'the_tag_content', $batch_result[$term_id]); // Cocoonの「本文」フィールド
                update_term_meta($term_id, '_ai_intro_generated', 1); // 処理済みフラグ

                $log[] = [
                    'term_id' => $term_id,
                    'slug'    => $item['term']->slug,
                    'dmm_id'  => get_term_meta($term_id, 'actress_dmm_id', true),
                    'status'  => 'success',
                ];
            } else {
                $failed_items[] = $item;
            }
        }

        // このチャンクが全件失敗した場合のみエラー扱い（個別フォールバックは行わない）
        if (count($failed_items) === count($chunk)) {
            foreach ($failed_items as $item) {
                $log[] = [
                    'term_id' => $item['term_id'],
                    'slug'    => $item['term']->slug,
                    'dmm_id'  => get_term_meta($item['term_id'], 'actress_dmm_id', true),
                    'status'  => 'error',
                    'message' => 'バッチ応答に紹介文が含まれていませんでした（このチャンクは全件失敗）。',
                ];
            }
            continue;
        }

        // ---- 一部だけレスポンスに含まれなかった分は、従来方式で1件ずつ個別に再生成 ----
        foreach ($failed_items as $item) {
            if ($api_called && $sleep_seconds > 0) {
                sleep($sleep_seconds);
            }

            $intro      = $client->generateSingle($item['data']);
            $api_called = true;

            if (is_wp_error($intro)) {
                $log[] = [
                    'term_id' => $item['term_id'],
                    'slug'    => $item['term']->slug,
                    'dmm_id'  => get_term_meta($item['term_id'], 'actress_dmm_id', true),
                    'status'  => 'error',
                    'message' => sprintf('バッチ応答に含まれず、個別再生成も失敗: %s', $intro->get_error_message()),
                ];

                // レート制限/停止中/アクセス拒否なら、以降の処理もすべて中断
                if (in_array($intro->get_error_code(), ['gemini_rate_limited', 'gemini_paused', 'gemini_access_denied'], true)) {
                    break 2; // チャンクのループも含めて中断
                }
                continue;
            }

            update_term_meta($item['term_id'], 'the_tag_content', $intro); // Cocoonの「本文」フィールド
            update_term_meta($item['term_id'], '_ai_intro_generated', 1); // 処理済みフラグ

            $log[] = [
                'term_id' => $item['term_id'],
                'slug'    => $item['term']->slug,
                'dmm_id'  => get_term_meta($item['term_id'], 'actress_dmm_id', true),
                'status'  => 'success',
                'message' => 'バッチ応答に含まれなかったため個別再生成で成功。',
            ];
        }
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