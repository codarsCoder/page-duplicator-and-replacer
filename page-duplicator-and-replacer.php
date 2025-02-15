<?php
/*
Plugin Name: Seo Page Duplicator
Description: Sayfaları çogaltip belirli kelimeleri degistiren bir eklenti.
Version: 1.1
Author: Nurullah
*/

// Sayfayı çoğaltma işlevi
function duplicate_page($page_id, $new_page_name, $capitalize_first = false)
{
    $page = get_post($page_id);

    // Benzersiz bir post_name (slug) oluşturma
    $post_name = sanitize_title($new_page_name);
    $post_name = wp_unique_post_slug($post_name, $page->ID, $page->post_status, $page->post_type, $page->post_parent);

    // Başlığı büyük harf yapma kontrolü
    $page_title = $capitalize_first ? mb_convert_case($new_page_name, MB_CASE_TITLE, "UTF-8") : $new_page_name;

    // Yeni sayfa verilerini oluşturma
    $new_page = array(
        'post_title' => $page_title,
        'post_content' => $page->post_content,
        // 'post_status' => 'draft',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_name' => $post_name,
         'post_parent' => $page->post_parent 
    );

    // Yeni sayfayı oluştur
    $new_page_id = wp_insert_post($new_page);

    return $new_page_id;
}

// Kelime değiştirme işlevi
function replace_words_in_page($page_id, $search, $replace, $capitalize_first = false, $is_elementor = false)
{
    $page = get_post($page_id);
    
    $new_content = $page->post_content;
    $new_title = $page->post_title;

    if ($is_elementor) {
        // Elementor içeriğini al
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (!empty($elementor_data)) {
            if (is_string($elementor_data)) {
                $elementor_data = json_decode($elementor_data, true);
            }
            
            // Elementor içeriğinde kelime değiştirme
            $elementor_data = replace_words_in_elementor($elementor_data, $search, $replace, $capitalize_first);
            
            // Elementor meta verilerini güncelle
            update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
        }
    } else {
        // Normal içerikte kelime değiştirme
        $new_content = replace_words_preserve_html($new_content, $search, $replace, $capitalize_first);
    }

    // Başlıkta kelime değiştirme
    if (strpos($new_title, $search) !== false) {
        $replace_for_title = $capitalize_first ? mb_convert_case($replace, MB_CASE_TITLE, "UTF-8") : $replace;
        $new_title = str_replace($search, $replace_for_title, $new_title);
    }

    // Güncellenmiş sayfa verilerini oluşturma
    $updated_page = array(
        'ID' => $page_id,
        'post_content' => $new_content,
        'post_title' => $new_title
    );

    // Sayfayı güncelle
    wp_update_post($updated_page);
}

// HTML ve kısa kodları koruyarak kelime değiştirme işlevi
function replace_words_preserve_html($content, $search, $replace, $capitalize_first = false)
{
    if ($capitalize_first) {
        $replace = mb_convert_case($replace, MB_CASE_TITLE, "UTF-8");
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $text_nodes = $xpath->query('//text()');

    foreach ($text_nodes as $node) {
        if (strpos($node->nodeValue, $search) !== false) {
            $node->nodeValue = str_replace($search, $replace, $node->nodeValue);
        }
    }

    // HTML içeriğini tekrar döndürme
    $html = $dom->saveHTML();
    $html = str_replace(array('<html>', '</html>', '<body>', '</body>'), '', $html); // HTML, BODY etiketlerini temizle

    return $html;
}

// Elementor içeriğinde kelime değiştirme işlevi
function replace_words_in_elementor($data, $search, $replace, $capitalize_first = false)
{
    if (!is_array($data)) {
        return $data;
    }

    foreach ($data as $key => &$item) {
        if (is_array($item)) {
            $item = replace_words_in_elementor($item, $search, $replace, $capitalize_first);
        } else if (is_string($item) && $key === 'text' || $key === 'title' || $key === 'editor') {
            // HTML içeriği varsa HTML koruyarak değiştir
            if (strpos($item, '<') !== false && strpos($item, '>') !== false) {
                $item = replace_words_preserve_html($item, $search, $replace, $capitalize_first);
            } 
            // Düz metin ise direkt değiştir
            else if (strpos($item, $search) !== false) {
                $replace_text = $capitalize_first ? mb_convert_case($replace, MB_CASE_TITLE, "UTF-8") : $replace;
                $item = str_replace($search, $replace_text, $item);
            }
        }
    }

    return $data;
}

// Yönetici menüsü ekleme
add_action('admin_menu', 'pdar_admin_menu');

function pdar_admin_menu()
{
    add_menu_page('Seo Page Duplicator', 'Page Duplicator2', 'manage_options', 'pdar', 'pdar_page');
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
                    <th scope="row">Yeni Sayfa İsimleri (Her satıra bir isim)</th>
                    <td><textarea name="new_page_names" rows="10" cols="50"></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Değişecek Kelime</th>
                    <td><input type="text" name="search_word" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Baş Harfleri Büyük Yap</th>
                    <td><input type="checkbox" name="capitalize_first" value="1" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Elementor Sayfası</th>
                    <td>
                        <input type="checkbox" name="is_elementor" value="1" />
                        <span class="description">Kaynak sayfa Elementor ile oluşturulduysa bu seçeneği işaretleyin</span>
                    </td>
                </tr>
            </table>
            <input type="submit" name="pdar_submit" class="button-primary" value="Çoğalt ve Değiştir" />
        </form>
        <?php
        if (isset($_POST['pdar_submit'])) {
            $page_id = intval($_POST['page_id']);
            $new_page_names = explode("\n", stripslashes(trim($_POST['new_page_names'])));
            $search_word = sanitize_text_field($_POST['search_word']);
            $capitalize_first = isset($_POST['capitalize_first']) ? true : false;
            $is_elementor = isset($_POST['is_elementor']) ? true : false;
            $new_pages = array();

            foreach ($new_page_names as $new_page_name) {
                // Sadece boşlukları temizle
                $new_page_name = trim($new_page_name);
                if (!empty($new_page_name)) {
                    $new_page_id = duplicate_page($page_id, $new_page_name, $capitalize_first);
                    
                    if ($is_elementor) {
                        // Elementor meta verilerini kopyala
                        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
                        if (!empty($elementor_data)) {
                            update_post_meta($new_page_id, '_elementor_data', $elementor_data);
                            update_post_meta($new_page_id, '_elementor_edit_mode', 'builder');
                            update_post_meta($new_page_id, '_elementor_template_type', 'wp-page');
                            update_post_meta($new_page_id, '_elementor_version', ELEMENTOR_VERSION);
                        }
                    }
                    
                    replace_words_in_page($new_page_id, $search_word, $new_page_name, $capitalize_first, $is_elementor);

                    $slug = get_post_field('post_name', $new_page_id);
                    $home_url = get_home_url();
                    $permalink = $home_url . '/' . $slug;
                    $new_pages[] = array(
                        'id' => $new_page_id,
                        'name' => $new_page_name,
                        'slug' => $slug,
                        'url' => $permalink
                    );
                }
            }

            $existing_pages = get_option('pdar_new_pages', array());
            $merged_pages = array_merge($existing_pages, $new_pages);
            update_option('pdar_new_pages', $merged_pages);

            echo '<div class="updated"><p>Sayfalar başarıyla çoğaltıldı ve kelimeler değiştirildi!</p></div>';
            
            // Yeni oluşturulan sayfaları tablo halinde göster
            echo '<div class="wrap" style="margin-top: 20px;">';
            echo '<h3>Son Oluşturulan Sayfalar</h3>';
            echo '<table class="widefat fixed" cellspacing="0">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>GİRİŞ ANAHTAR KELİME</th>';
            echo '<th>GOOGLE ANAHTAR KELİME</th>';
            echo '<th>YAZININ BAŞLIK</th>';
            echo '<th>YAZI LİNKİ</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($new_pages as $new_page) {
                $title_case_name = mb_convert_case($new_page['name'], MB_CASE_TITLE, "UTF-8");
                echo '<tr>';
                echo '<td>' . esc_html($new_page['name']) . '</td>';
                echo '<td>[' . esc_html($new_page['name']) . ']</td>';
                echo '<td>' . esc_html($title_case_name) . '</td>';
                echo '<td><a href="' . esc_url($new_page['url']) . '" target="_blank">' . esc_url($new_page['url']) . '</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            echo '<div style="margin-top: 20px;"><a href="' . admin_url('admin.php?page=pdar_results') . '" class="button">Tüm Çoğaltılan Sayfaları Görüntüle</a></div>';
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
                    <th>GİRİŞ ANAHTAR KELİME</th>
                    <th>GOOGLE ANAHTAR KELİME</th>
                    <th>YAZININ BAŞLIK</th>
                    <th>YAZI LİNKİ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($new_pages)) {
                    foreach ($new_pages as $new_page) {
                        $title_case_name = mb_convert_case($new_page['name'], MB_CASE_TITLE, "UTF-8");
                        echo '<tr>';
                        echo '<td>' . esc_html($new_page['name']) . '</td>';
                        echo '<td>[' . esc_html($new_page['name']) . ']</td>';
                        echo '<td>' . esc_html($title_case_name) . '</td>';
                        echo '<td><a href="' . esc_url($new_page['url']) . '" target="_blank">' . esc_url($new_page['url']) . '</a></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">Henüz çoğaltılmış sayfa yok.</td></tr>';
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
