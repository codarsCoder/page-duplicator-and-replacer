<?php
/*
Plugin Name: Page Duplicator and Replacer
Description: Sayfaları çoğaltıp belirli kelimeleri değiştiren bir eklenti.
Version: 1.6
Author: Your Name
*/

// Sayfayı çoğaltma işlevi
function duplicate_page($page_id, $new_page_name)
{
    $page = get_post($page_id);

    // Benzersiz bir post_name (slug) oluşturma
    $post_name = sanitize_title($new_page_name);
    $post_name = wp_unique_post_slug($post_name, $page->ID, $page->post_status, $page->post_type, $page->post_parent);

    // Yeni sayfa verilerini oluşturma
    $new_page = array(
        'post_title' => $new_page_name,
        'post_content' => $page->post_content,
        'post_status' => 'draft',
        'post_type' => 'page',
        'post_name' => $post_name
    );

    // Yeni sayfayı oluştur
    $new_page_id = wp_insert_post($new_page);

    return $new_page_id;
}

// Kelime değiştirme işlevi
// function replace_words_in_page($page_id, $replacements)
// {
//     $page = get_post($page_id);

//     $new_content = $page->post_content;

//     // Her bir değişim grubu için
//     foreach ($replacements as $search => $replace) {
//         // İçerikte kelime değiştirme
//         $new_content = str_replace($search, $replace, $new_content);
//     }

//     // Güncellenmiş sayfa verilerini oluşturma
//     $updated_page = array(
//         'ID' => $page_id,
//         'post_content' => $new_content
//     );

//     // Sayfayı güncelle
//     wp_update_post(wp_slash($updated_page));
// }
// Kelime değiştirme işlevi
function replace_words_in_page($page_id, $replacements)
{
    $page = get_post($page_id);

    $new_content = $page->post_content;

    // Her bir değişim grubu için
    foreach ($replacements as $search => $replace) {
        // İçerikte kelime değiştirme
        $new_content = str_replace($search, $replace, $new_content);
    }

    // HTML ve kısa kodları koruyarak kelime değiştirme
    $new_content = wp_kses_post($new_content);

    // Güncellenmiş sayfa verilerini oluşturma
    $updated_page = array(
        'ID' => $page_id,
        'post_content' => $new_content
    );

    // Sayfayı güncelle
    wp_update_post(wp_slash($updated_page));
}

// Yönetici menüsü ekleme
add_action('admin_menu', 'pdar_admin_menu');

function pdar_admin_menu()
{
    add_menu_page('Page Duplicator and Replacer', 'Page Duplicator', 'manage_options', 'pdar', 'pdar_page');
    add_submenu_page('pdar', 'Çoğaltılan Sayfalar', 'Çoğaltılan Sayfalar', 'manage_options', 'pdar_results', 'pdar_results_page');
    add_submenu_page('pdar', 'Tüm Sayfalar', 'Tüm Sayfalar', 'manage_options', 'pdar_all_pages', 'pdar_all_pages_page');
}

// Yönetici sayfası içeriği
function pdar_page()
{
    ?>
    <div class="wrap">
        <h2>Sayfa Çoğalt ve Kelime Değiştir</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Sayfa ID</th>
                    <td><input type="text" name="page_id" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Kaç Adet Çoğaltılacak?</th>
                    <td><input type="number" name="copy_count" min="1" /></td>
                </tr>
            </table>
            <input type="submit" name="pdar_generate_inputs" class="button-primary" value="Girdi Alanları Oluştur" />
        </form>
        <?php
        if (isset($_POST['pdar_generate_inputs'])) {
            $page_id = intval($_POST['page_id']);
            $copy_count = intval($_POST['copy_count']);
            ?>
            <form method="post" action="">
                <input type="hidden" name="page_id" value="<?php echo $page_id; ?>" />
                <input type="hidden" name="copy_count" value="<?php echo $copy_count; ?>" />
                <?php for ($i = 1; $i <= $copy_count; $i++) { ?>
                    <h3>Çoğaltma <?php echo $i; ?></h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Yeni Sayfa İsmi <?php echo $i; ?></th>
                            <td><input type="text" name="new_page_name_<?php echo $i; ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Değişim Grupları <?php echo $i; ?> (değişecek1,gelecek1;değişecek2,gelecek2)</th>
                            <td><textarea name="replacements_<?php echo $i; ?>" rows="4" cols="50"></textarea></td>
                        </tr>
                    </table>
                <?php } ?>
                <input type="submit" name="pdar_submit" class="button-primary" value="Çoğalt ve Değiştir" />
            </form>
            <?php
        }

        if (isset($_POST['pdar_submit'])) {
            $page_id = intval($_POST['page_id']);
            $copy_count = intval($_POST['copy_count']);
            $new_pages = array();
        
            for ($i = 1; $i <= $copy_count; $i++) {
                $new_page_name = sanitize_text_field($_POST['new_page_name_' . $i]);
                $replacements_input = sanitize_textarea_field($_POST['replacements_' . $i]);
        
                // Değişim gruplarını ayrıştırma
                $replacements = array();
                $pairs = explode(';', $replacements_input);
                foreach ($pairs as $pair) {
                    list($search, $replace) = explode(',', $pair);
                    $replacements[$search] = $replace;
                }
        
                $new_page_id = duplicate_page($page_id, $new_page_name);
                replace_words_in_page($new_page_id, $replacements);
        
                $slug = get_post_field('post_name', $new_page_id);
                $home_url = get_home_url();
                $permalink = $home_url . '/' . $slug;
                $new_pages[] = array(
                    'id' => $new_page_id,
                    'name' => $new_page_name,
                    'slug' => $slug,
                    'url' => $permalink
                    // 'url' => get_permalink($new_page_id)
                );
            }
        
            $existing_pages = get_option('pdar_new_pages', array());
            $merged_pages = array_merge($existing_pages, $new_pages);
            update_option('pdar_new_pages', $merged_pages);
        
            echo '<div class="updated"><p>Sayfalar çoğaltıldı ve kelimeler değiştirildi! Yeni sayfaların linkleri aşağıda listelenmiştir:</p><ul>';
            foreach ($new_pages as $new_page) {
                echo '<li><a href="' . $new_page['url'] . '" target="_blank">' . $new_page['name'] . '</a></li>';
            }
            echo '</ul></div>';
        
            echo '<div><a href="' . admin_url('admin.php?page=pdar_results') . '" class="button">Çoğaltılan Sayfaları Görüntüle</a></div>';
        }
        
        ?>
    </div>
    <?php
}

function pdar_results_page() {
    $new_pages = get_option('pdar_new_pages', array());

    // Sayfaları post_name ile sıralama
    usort($new_pages, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    ?>
    <div class="wrap">
        <h2>Çoğaltılan Sayfalar</h2>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>Sayfa İsmi</th>
                    <th>Sayfa Slug</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($new_pages)) {
                    foreach ($new_pages as $new_page) {
                        $home_url = get_home_url();
                        $permalink = $home_url . '/' . $new_page['slug'];
                        echo '<tr>';
                        echo '<td>' . esc_html($new_page['name']) . '</td>';
                        echo '<td>' . esc_html($new_page['slug']) . '</td>';
                        echo '<td><a href="' . esc_url($permalink) . '" target="_blank">' . esc_url($permalink) . '</a></td>';
                        // echo '<td><a href="' . esc_url($new_page['url']) . '" target="_blank">' . esc_url($new_page['url']) . '</a></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">Henüz çoğaltılmış sayfa yok.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}



function pdar_all_pages_page()
{
    $pages = get_pages();
    ?>
    <div class="wrap">
        <h2>Tüm Sayfalar</h2>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th>Sayfa İsmi</th>
                    <th>Sayfa ID</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($pages as $page) {
                    echo '<tr>';
                    echo '<td>' . esc_html($page->post_title) . '</td>';
                    echo '<td>' . esc_html($page->ID) . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>