<?php

/**
 * Template Name: 女優一覧
 */

get_header();

// ==============================
// 50音表(あ行〜わ行、ひらがな・カタカナ対応)
// ==============================
function fanza_actress_gojuon(): array
{
    return [
        ['key' => 'a1', 'hira' => 'あ', 'kata' => 'ア'],
        ['key' => 'a2', 'hira' => 'い', 'kata' => 'イ'],
        ['key' => 'a3', 'hira' => 'う', 'kata' => 'ウ'],
        ['key' => 'a4', 'hira' => 'え', 'kata' => 'エ'],
        ['key' => 'a5', 'hira' => 'お', 'kata' => 'オ'],
        ['key' => 'ka1', 'hira' => 'か', 'kata' => 'カ'],
        ['key' => 'ka2', 'hira' => 'き', 'kata' => 'キ'],
        ['key' => 'ka3', 'hira' => 'く', 'kata' => 'ク'],
        ['key' => 'ka4', 'hira' => 'け', 'kata' => 'ケ'],
        ['key' => 'ka5', 'hira' => 'こ', 'kata' => 'コ'],
        ['key' => 'sa1', 'hira' => 'さ', 'kata' => 'サ'],
        ['key' => 'sa2', 'hira' => 'し', 'kata' => 'シ'],
        ['key' => 'sa3', 'hira' => 'す', 'kata' => 'ス'],
        ['key' => 'sa4', 'hira' => 'せ', 'kata' => 'セ'],
        ['key' => 'sa5', 'hira' => 'そ', 'kata' => 'ソ'],
        ['key' => 'ta1', 'hira' => 'た', 'kata' => 'タ'],
        ['key' => 'ta2', 'hira' => 'ち', 'kata' => 'チ'],
        ['key' => 'ta3', 'hira' => 'つ', 'kata' => 'ツ'],
        ['key' => 'ta4', 'hira' => 'て', 'kata' => 'テ'],
        ['key' => 'ta5', 'hira' => 'と', 'kata' => 'ト'],
        ['key' => 'na1', 'hira' => 'な', 'kata' => 'ナ'],
        ['key' => 'na2', 'hira' => 'に', 'kata' => 'ニ'],
        ['key' => 'na3', 'hira' => 'ぬ', 'kata' => 'ヌ'],
        ['key' => 'na4', 'hira' => 'ね', 'kata' => 'ネ'],
        ['key' => 'na5', 'hira' => 'の', 'kata' => 'ノ'],
        ['key' => 'ha1', 'hira' => 'は', 'kata' => 'ハ'],
        ['key' => 'ha2', 'hira' => 'ひ', 'kata' => 'ヒ'],
        ['key' => 'ha3', 'hira' => 'ふ', 'kata' => 'フ'],
        ['key' => 'ha4', 'hira' => 'へ', 'kata' => 'ヘ'],
        ['key' => 'ha5', 'hira' => 'ほ', 'kata' => 'ホ'],
        ['key' => 'ma1', 'hira' => 'ま', 'kata' => 'マ'],
        ['key' => 'ma2', 'hira' => 'み', 'kata' => 'ミ'],
        ['key' => 'ma3', 'hira' => 'む', 'kata' => 'ム'],
        ['key' => 'ma4', 'hira' => 'め', 'kata' => 'メ'],
        ['key' => 'ma5', 'hira' => 'も', 'kata' => 'モ'],
        ['key' => 'ya1', 'hira' => 'や', 'kata' => 'ヤ'],
        ['key' => 'ya2', 'hira' => 'ゆ', 'kata' => 'ユ'],
        ['key' => 'ya3', 'hira' => 'よ', 'kata' => 'ヨ'],
        ['key' => 'ra1', 'hira' => 'ら', 'kata' => 'ラ'],
        ['key' => 'ra2', 'hira' => 'り', 'kata' => 'リ'],
        ['key' => 'ra3', 'hira' => 'る', 'kata' => 'ル'],
        ['key' => 'ra4', 'hira' => 'れ', 'kata' => 'レ'],
        ['key' => 'ra5', 'hira' => 'ろ', 'kata' => 'ロ'],
        ['key' => 'wa1', 'hira' => 'わ', 'kata' => 'ワ'],
        ['key' => 'wa2', 'hira' => 'を', 'kata' => 'ヲ'],
        ['key' => 'wa3', 'hira' => 'ん', 'kata' => 'ン'],
    ];
}

$gojuon      = fanza_actress_gojuon();
$current_key = isset($_GET['kana']) ? sanitize_text_field($_GET['kana']) : '';
$per_page    = 40;
$paged       = max(1, get_query_var('paged') ? get_query_var('paged') : (isset($_GET['paged']) ? (int) $_GET['paged'] : 1));
$offset      = ($paged - 1) * $per_page;

$current_char = null;
foreach ($gojuon as $row) {
    if ($row['key'] === $current_key) {
        $current_char = $row;
        break;
    }
}

// ==============================
// 全女優を取得し、PHP側でフリガナ先頭一致フィルタ
// (MySQLのREGEXPはマルチバイト文字をバイト単位で評価するため誤ヒットする → mb_substrで確実に判定)
// ==============================
$all_actresses = get_terms([
    'taxonomy'   => 'actress',
    'hide_empty' => true,
]);

if (is_wp_error($all_actresses)) {
    $all_actresses = [];
}

// 各女優にrubyを付与
foreach ($all_actresses as $term) {
    $term->_ruby      = get_term_meta($term->term_id, 'actress_ruby', true);
    $term->_thumbnail = get_term_meta($term->term_id, 'actress_thumbnail', true);
    $term->_bust      = get_term_meta($term->term_id, 'actress_bust', true);
    $term->_waist     = get_term_meta($term->term_id, 'actress_waist', true);
    $term->_hip       = get_term_meta($term->term_id, 'actress_hip', true);
    $term->_cup       = get_term_meta($term->term_id, 'actress_cup', true);
    $term->_birthday  = get_term_meta($term->term_id, 'actress_birthday', true);
    $term->_age       = null;

    if (! empty($term->_birthday)) {
        $birth_date = DateTime::createFromFormat('Y-m-d', $term->_birthday);
        if ($birth_date) {
            $today = new DateTime('today');
            $term->_age = $today->diff($birth_date)->y;
        }
    }
}

if ($current_char) {
    $targets = [$current_char['hira'], $current_char['kata']];
    $all_actresses = array_values(array_filter($all_actresses, function ($term) use ($targets) {
        if (empty($term->_ruby)) {
            return false;
        }
        $first = mb_substr($term->_ruby, 0, 1);
        return in_array($first, $targets, true);
    }));
}

// フリガナ順に並び替え
usort($all_actresses, function ($a, $b) {
    return strcmp((string) $a->_ruby, (string) $b->_ruby);
});

$total_actresses = count($all_actresses);
$total_pages      = (int) ceil($total_actresses / $per_page);
$actresses        = array_slice($all_actresses, $offset, $per_page);
?>

<div class="content-wrap">
    <?php fanza_sync_render_switch_tabs('actress'); ?>
    <h1 class="entry-title"><?php the_title(); ?></h1>

    <div class="actress-kana-nav">
        <a href="<?php echo esc_url(remove_query_arg(['kana', 'paged'])); ?>" class="<?php echo $current_key === '' ? 'is-active' : ''; ?>">全</a>
        <?php foreach ($gojuon as $row): ?>
            <a href="<?php echo esc_url(add_query_arg(['kana' => $row['key'], 'paged' => false])); ?>" class="<?php echo $current_key === $row['key'] ? 'is-active' : ''; ?>">
                <?php echo esc_html($row['hira']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="actress-list-grid">
        <?php if (! empty($actresses)): ?>
            <?php foreach ($actresses as $actress): ?>
                <div class="actress-card">
                    <a href="<?php echo esc_url(get_term_link($actress)); ?>" class="actress-card-link">
                        <?php if (! empty($actress->_thumbnail)): ?>
                            <img src="<?php echo esc_url($actress->_thumbnail); ?>" alt="<?php echo esc_attr($actress->name); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="actress-noimage">No Image</div>
                        <?php endif; ?>

                        <p class="actress-name"><?php echo esc_html($actress->name); ?></p>
                        <?php if (! empty($actress->_ruby)): ?>
                            <p class="actress-ruby"><?php echo esc_html($actress->_ruby); ?></p>
                        <?php endif; ?>
                    </a>
                    <?php if (! empty($actress->_bust) || ! empty($actress->_waist) || ! empty($actress->_hip)): ?>
                        <p class="actress-bwh">
                            B <?php echo esc_html($actress->_bust ?: '-'); ?> /
                            W <?php echo esc_html($actress->_waist ?: '-'); ?> /
                            H <?php echo esc_html($actress->_hip ?: '-'); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (! empty($actress->_cup)): ?>
                        <p class="actress-cup">
                            <?php echo esc_html($actress->_cup); ?>&nbsp;cup
                        </p>
                    <?php endif; ?>
                    <?php if (! empty($actress->_birthday)): ?>
                        <p class="actress-birthday">
                            <?php echo esc_html($actress->_birthday); ?><?php echo $actress->_age !== null ? '(' . esc_html($actress->_age) . '歳)' : ''; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>該当する女優がいません。</p>
        <?php endif; ?>
    </div>

    <div class="actress-pagination">
        <?php
        echo paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'current'   => $paged,
            'total'     => $total_pages,
            'prev_text' => '« 前へ',
            'next_text' => '次へ »',
        ]);
        ?>
    </div>
</div>

<?php get_footer(); ?>