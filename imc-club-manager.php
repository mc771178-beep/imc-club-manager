<?php
/**
 * Plugin Name: IMC 社務管理中心
 * Description: IMC 活動、行事曆、線上報名、候補、簽到、入社申請與資料匯出的一站式管理外掛。
 * Version: 2.0.0
 * Author: IMC
 * Text Domain: imc-club-manager
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('IMCCM_VERSION', '2.0.0');
define('IMCCM_FILE', __FILE__);
define('IMCCM_DIR', plugin_dir_path(__FILE__));
define('IMCCM_URL', plugin_dir_url(__FILE__));

require_once IMCCM_DIR . 'includes/class-imccm-members.php';
require_once IMCCM_DIR . 'includes/class-imccm-checkin.php';
require_once IMCCM_DIR . 'includes/class-imccm-toolbox.php';

final class IMC_Club_Manager {
    private static $instance;
    private $registrations_table;
    private $applications_table;

    public static function instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->registrations_table = $wpdb->prefix . 'imc_registrations';
        $this->applications_table  = $wpdb->prefix . 'imc_applications';

        add_action('init', [$this, 'register_content_types']);
        add_action('add_meta_boxes', [$this, 'add_event_meta_box']);
        add_action('save_post_imc_event', [$this, 'save_event_meta']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_imccm_export', [$this, 'export_csv']);
        add_action('admin_post_imccm_status', [$this, 'update_registration_status']);
        add_action('admin_post_imccm_ics', [$this, 'download_ics']);
        add_action('admin_post_nopriv_imccm_ics', [$this, 'download_ics']);
        add_action('wp_ajax_imccm_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_imccm_register', [$this, 'ajax_register']);
        add_action('wp_ajax_imccm_apply', [$this, 'ajax_apply']);
        add_action('wp_ajax_nopriv_imccm_apply', [$this, 'ajax_apply']);
        add_shortcode('imc_calendar', [$this, 'calendar_shortcode']);
        add_shortcode('imc_event_list', [$this, 'event_list_shortcode']);
        add_shortcode('imc_registration', [$this, 'registration_shortcode']);
        add_shortcode('imc_join_form', [$this, 'join_shortcode']);
        add_filter('manage_imc_event_posts_columns', [$this, 'event_columns']);
        add_action('manage_imc_event_posts_custom_column', [$this, 'event_column_value'], 10, 2);
        add_action('rest_api_init', [$this, 'rest_routes']);
        add_filter('the_content', [$this, 'event_content']);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $reg = $wpdb->prefix . 'imc_registrations';
        $app = $wpdb->prefix . 'imc_applications';
        dbDelta("CREATE TABLE $reg (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            registration_code varchar(30) NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(190) NOT NULL DEFAULT '',
            phone varchar(30) NOT NULL,
            member_no varchar(50) NOT NULL DEFAULT '',
            attendees smallint unsigned NOT NULL DEFAULT 1,
            dietary varchar(100) NOT NULL DEFAULT '',
            note text NULL,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY registration_code (registration_code),
            KEY event_id (event_id),
            KEY email (email),
            KEY status (status)
        ) $charset;");
        dbDelta("CREATE TABLE $app (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            phone varchar(30) NOT NULL,
            email varchar(190) NOT NULL DEFAULT '',
            company varchar(190) NOT NULL DEFAULT '',
            title varchar(100) NOT NULL DEFAULT '',
            industry varchar(100) NOT NULL DEFAULT '',
            referrer varchar(100) NOT NULL DEFAULT '',
            message text NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset;");
        if (!get_option('imccm_settings')) {
            update_option('imccm_settings', [
                'club_name' => '雲林縣國際工商經營研究社',
                'contact_email' => 'ylimcorg@gmail.com',
                'contact_phone' => '0911-966-647',
                'address' => '640雲林縣斗六市斗六二路247號',
                'primary_color' => '#133b67',
                'send_admin_email' => 1,
                'privacy_text' => '我同意主辦單位為本次活動聯繫、保險及社務目的蒐集與使用上述資料。',
            ]);
        }
        self::instance()->register_content_types();
        IMCCM_Members::instance()->register();
        IMCCM_Toolbox::instance()->register();
        self::seed_resources();
        flush_rewrite_rules();
    }

    private static function seed_resources() {
        if (get_option('imccm_seeded_100')) return;
        $items = [
            [
                'title' => '關於雲林 IMC',
                'type' => '關於 IMC',
                'content' => '<h2>本社成立緣起</h2><p>本社初期由 IMC 嘉義社第 29 屆社長葉文生先生輔導成立，於民國 98 年與國立中正大學吳育仁教授接洽，經多次籌備會議後，於 98 年 10 月 10 日在劍湖山王子大飯店舉辦成立大會。</p><p><strong>正式名稱：</strong>雲林縣國際工商經營研究社<br><strong>英文名稱：</strong>The Yunlin International Management Council<br><strong>簡稱：</strong>IMC</p>',
            ],
            [
                'title' => 'IMC 緣起與使命',
                'type' => '關於 IMC',
                'content' => '<p>IMC 起源於美國，早期聚焦工商經營與管理研究。台灣第一個 IMC 於民國 50 年 5 月 5 日成立。</p><h2>本社宗旨</h2><p>增進工商界人士之友誼，建立互助合作之精神，研究與進修工商經營管理上之各項問題，培養健全人格與領導才能，推展社會福利及職業技能訓練事業。</p>',
            ],
            [
                'title' => '雲林 IMC 聯絡資訊',
                'type' => '聯絡資料',
                'content' => '<p><strong>社名：</strong>雲林縣國際工商經營研究社<br><strong>統一編號：</strong>25375519<br><strong>社址：</strong>640 雲林縣斗六市斗六二路 247 號<br><strong>電話：</strong>0911-966-647<br><strong>Email：</strong>ylimcorg@gmail.com</p><p><em>資料來源：雲林 IMC 公開網站；正式使用前請由秘書處複核。</em></p>',
            ],
        ];
        foreach ($items as $item) {
            $id = wp_insert_post(['post_type'=>'imc_resource','post_status'=>'draft','post_title'=>$item['title'],'post_content'=>$item['content']]);
            if (!is_wp_error($id)) wp_set_object_terms($id, $item['type'], 'imc_resource_type');
        }
        update_option('imccm_seeded_100', 1);
    }

    public static function deactivate() { flush_rewrite_rules(); }

    public function register_content_types() {
        register_post_type('imc_event', [
            'labels' => ['name'=>'IMC 活動', 'singular_name'=>'活動', 'add_new'=>'新增活動', 'add_new_item'=>'新增 IMC 活動', 'edit_item'=>'編輯活動', 'menu_name'=>'IMC 活動'],
            'public'=>true, 'show_in_rest'=>true, 'has_archive'=>true, 'rewrite'=>['slug'=>'imc-events'],
            'menu_icon'=>'dashicons-calendar-alt', 'supports'=>['title','editor','excerpt','thumbnail'],
        ]);
        register_taxonomy('imc_event_type', 'imc_event', [
            'labels'=>['name'=>'活動類型','singular_name'=>'活動類型'], 'public'=>true, 'show_in_rest'=>true,
            'hierarchical'=>true, 'rewrite'=>['slug'=>'imc-event-type'],
        ]);
        register_post_type('imc_resource', [
            'labels'=>['name'=>'IMC 資料庫','singular_name'=>'資料','add_new'=>'新增資料','add_new_item'=>'新增 IMC 資料','menu_name'=>'IMC 資料庫'],
            'public'=>true, 'show_in_rest'=>true, 'has_archive'=>true, 'rewrite'=>['slug'=>'imc-resources'],
            'menu_icon'=>'dashicons-database', 'supports'=>['title','editor','excerpt','thumbnail','revisions'],
        ]);
        register_taxonomy('imc_resource_type', 'imc_resource', [
            'labels'=>['name'=>'資料分類','singular_name'=>'資料分類'], 'public'=>true, 'show_in_rest'=>true, 'hierarchical'=>true,
        ]);
    }

    public function add_event_meta_box() {
        add_meta_box('imccm-event-details', '活動與報名設定', [$this, 'render_event_meta_box'], 'imc_event', 'normal', 'high');
    }

    public function render_event_meta_box($post) {
        wp_nonce_field('imccm_save_event', 'imccm_event_nonce');
        $fields = ['start'=>'開始時間','end'=>'結束時間','venue'=>'地點','capacity'=>'人數上限','deadline'=>'報名截止時間','fee'=>'費用說明','contact'=>'聯絡窗口'];
        echo '<div class="imccm-admin-grid">';
        foreach ($fields as $key=>$label) {
            $value = get_post_meta($post->ID, '_imccm_'.$key, true);
            $type = in_array($key, ['start','end','deadline'], true) ? 'datetime-local' : ($key === 'capacity' ? 'number' : 'text');
            printf('<p><label><strong>%s</strong><br><input style="width:100%%" type="%s" name="imccm_%s" value="%s" %s></label></p>', esc_html($label), $type, esc_attr($key), esc_attr($value), $key==='capacity'?'min="0"':'');
        }
        $enabled = get_post_meta($post->ID, '_imccm_registration_enabled', true);
        $waitlist = get_post_meta($post->ID, '_imccm_waitlist_enabled', true);
        echo '<p><label><input type="checkbox" name="imccm_registration_enabled" value="1" '.checked($enabled, '1', false).'> 開放線上報名</label></p>';
        echo '<p><label><input type="checkbox" name="imccm_waitlist_enabled" value="1" '.checked($waitlist, '1', false).'> 額滿後開放候補</label></p>';
        echo '</div><p class="description">前台可使用 <code>[imc_registration id="'.$post->ID.'"]</code> 顯示這場活動的報名表。</p>';
    }

    public function save_event_meta($post_id) {
        if (!isset($_POST['imccm_event_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['imccm_event_nonce'])), 'imccm_save_event')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $fields = ['start','end','venue','capacity','deadline','fee','contact'];
        foreach ($fields as $key) {
            $value = isset($_POST['imccm_'.$key]) ? sanitize_text_field(wp_unslash($_POST['imccm_'.$key])) : '';
            update_post_meta($post_id, '_imccm_'.$key, $value);
        }
        update_post_meta($post_id, '_imccm_registration_enabled', isset($_POST['imccm_registration_enabled']) ? '1' : '0');
        update_post_meta($post_id, '_imccm_waitlist_enabled', isset($_POST['imccm_waitlist_enabled']) ? '1' : '0');
    }

    public function register_assets() {
        wp_register_style('imccm', IMCCM_URL.'assets/imc.css', [], IMCCM_VERSION);
        wp_register_script('imccm', IMCCM_URL.'assets/imc.js', [], IMCCM_VERSION, true);
        wp_localize_script('imccm', 'IMCCM', ['ajaxUrl'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('imccm_frontend'), 'messages'=>['sending'=>'傳送中…','error'=>'送出失敗，請稍後再試。']]);
    }

    private function enqueue() { wp_enqueue_style('imccm'); wp_enqueue_script('imccm'); }

    private function event_query($args=[]) {
        return new WP_Query(array_merge(['post_type'=>'imc_event','posts_per_page'=>100,'post_status'=>'publish','meta_key'=>'_imccm_start','orderby'=>'meta_value','order'=>'ASC'], $args));
    }

    public function calendar_shortcode($atts) {
        $this->enqueue();
        $atts = shortcode_atts(['months'=>12,'type'=>''], $atts);
        $q = $this->event_query();
        $events = [];
        while ($q->have_posts()) { $q->the_post(); $id=get_the_ID(); $start=get_post_meta($id,'_imccm_start',true); if (!$start) continue;
            $events[]=['id'=>$id,'title'=>get_the_title(),'start'=>$start,'end'=>get_post_meta($id,'_imccm_end',true),'venue'=>get_post_meta($id,'_imccm_venue',true),'url'=>get_permalink()];
        }
        wp_reset_postdata();
        $json = wp_json_encode($events, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
        ob_start(); ?>
        <section class="imccm-shell imccm-calendar" data-events="<?php echo esc_attr($json); ?>">
            <header class="imccm-cal-head"><button type="button" data-cal-prev aria-label="上個月">‹</button><h2 data-cal-title></h2><button type="button" data-cal-next aria-label="下個月">›</button></header>
            <div class="imccm-week"><span>日</span><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span></div>
            <div class="imccm-days" data-cal-days></div>
            <div class="imccm-agenda" data-cal-agenda><h3>本月活動</h3><div></div></div>
        </section><?php return ob_get_clean();
    }

    public function event_list_shortcode($atts) {
        $this->enqueue();
        $atts=shortcode_atts(['limit'=>10,'show_past'=>0],$atts);
        $meta=[]; if (!$atts['show_past']) $meta[]=['key'=>'_imccm_start','value'=>current_time('Y-m-d\TH:i'),'compare'=>'>=','type'=>'CHAR'];
        $q=$this->event_query(['posts_per_page'=>absint($atts['limit']),'meta_query'=>$meta]);
        ob_start(); echo '<div class="imccm-event-list">';
        if (!$q->have_posts()) echo '<p class="imccm-empty">目前沒有即將舉行的活動。</p>';
        while ($q->have_posts()) { $q->the_post(); $id=get_the_ID(); $start=get_post_meta($id,'_imccm_start',true); $venue=get_post_meta($id,'_imccm_venue',true); ?>
            <article class="imccm-event-card"><div class="imccm-date"><b><?php echo esc_html(wp_date('d',strtotime($start))); ?></b><span><?php echo esc_html(wp_date('M',strtotime($start))); ?></span></div><div><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><p><?php echo esc_html(wp_date('Y/m/d H:i',strtotime($start))); ?><?php if($venue) echo ' · '.esc_html($venue); ?></p></div><a class="imccm-btn" href="<?php the_permalink(); ?>">查看活動</a></article>
        <?php } wp_reset_postdata(); echo '</div>'; return ob_get_clean();
    }

    public function event_content($content) {
        if (!is_singular('imc_event') || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID(); $start = get_post_meta($id, '_imccm_start', true); $end = get_post_meta($id, '_imccm_end', true);
        $venue = get_post_meta($id, '_imccm_venue', true); $fee = get_post_meta($id, '_imccm_fee', true); $contact = get_post_meta($id, '_imccm_contact', true);
        $details = '<section class="imccm-event-detail"><h2>活動資訊</h2><dl>';
        if ($start) $details .= '<dt>時間</dt><dd>'.esc_html(wp_date('Y/m/d H:i', strtotime($start))).($end?' ～ '.esc_html(wp_date('Y/m/d H:i', strtotime($end))):'').'</dd>';
        if ($venue) $details .= '<dt>地點</dt><dd>'.esc_html($venue).'</dd>';
        if ($fee) $details .= '<dt>費用</dt><dd>'.esc_html($fee).'</dd>';
        if ($contact) $details .= '<dt>聯絡窗口</dt><dd>'.esc_html($contact).'</dd>';
        $details .= '</dl><p><a class="imccm-btn" href="'.esc_url(admin_url('admin-post.php?action=imccm_ics&event_id='.$id)).'">加入我的行事曆</a></p></section>';
        return $content . $details . do_shortcode('[imc_registration id="'.$id.'"]');
    }

    public function registration_shortcode($atts) {
        $this->enqueue();
        $atts=shortcode_atts(['id'=>get_the_ID()],$atts); $id=absint($atts['id']);
        if (get_post_type($id)!=='imc_event') return '<p>找不到活動。</p>';
        if (get_post_meta($id,'_imccm_registration_enabled',true)!=='1') return '<p class="imccm-notice">此活動目前未開放線上報名。</p>';
        $deadline=get_post_meta($id,'_imccm_deadline',true);
        if ($deadline && strtotime($deadline)<current_time('timestamp')) return '<p class="imccm-notice">本活動報名已截止。</p>';
        $remaining=$this->remaining_capacity($id); $waitlist=get_post_meta($id,'_imccm_waitlist_enabled',true)==='1';
        if ($remaining===0 && !$waitlist) return '<p class="imccm-notice">本活動名額已滿。</p>';
        ob_start(); ?>
        <form class="imccm-form" data-imccm-form="register">
            <input type="hidden" name="action" value="imccm_register"><input type="hidden" name="event_id" value="<?php echo esc_attr($id); ?>">
            <h3><?php echo esc_html(get_the_title($id)); ?>－線上報名</h3>
            <?php if($remaining===0): ?><p class="imccm-wait">目前額滿，送出後將列入候補。</p><?php elseif(is_int($remaining)): ?><p>剩餘 <?php echo esc_html($remaining); ?> 個名額</p><?php endif; ?>
            <div class="imccm-grid"><label>姓名 <em>*</em><input name="name" required maxlength="100"></label><label>手機 <em>*</em><input name="phone" required inputmode="tel" maxlength="30"></label><label>Email<input name="email" type="email" maxlength="190"></label><label>社員編號<input name="member_no" maxlength="50"></label><label>參加人數 <em>*</em><input name="attendees" type="number" min="1" max="20" value="1" required></label><label>飲食需求<input name="dietary" placeholder="葷食、素食或過敏資訊" maxlength="100"></label></div>
            <label>備註<textarea name="note" rows="3"></textarea></label>
            <label class="imccm-check"><input type="checkbox" name="consent" value="1" required> <?php echo esc_html($this->setting('privacy_text')); ?></label>
            <button class="imccm-btn" type="submit">送出報名</button><div class="imccm-result" role="status" aria-live="polite"></div>
        </form><?php return ob_get_clean();
    }

    public function join_shortcode() {
        $this->enqueue(); ob_start(); ?>
        <form class="imccm-form" data-imccm-form="apply"><input type="hidden" name="action" value="imccm_apply"><h3>加入 IMC</h3>
        <div class="imccm-grid"><label>姓名 <em>*</em><input name="name" required></label><label>手機 <em>*</em><input name="phone" required></label><label>Email<input name="email" type="email"></label><label>公司名稱<input name="company"></label><label>職稱<input name="title"></label><label>產業別<input name="industry"></label><label>推薦人<input name="referrer"></label></div>
        <label>自我介紹／想加入的原因<textarea name="message" rows="4"></textarea></label><label class="imccm-check"><input type="checkbox" name="consent" value="1" required> <?php echo esc_html($this->setting('privacy_text')); ?></label>
        <button class="imccm-btn" type="submit">送出申請</button><div class="imccm-result" role="status" aria-live="polite"></div></form><?php return ob_get_clean();
    }

    public function ajax_register() {
        check_ajax_referer('imccm_frontend','nonce');
        global $wpdb; $event_id=absint($_POST['event_id']??0);
        if (get_post_type($event_id)!=='imc_event' || get_post_meta($event_id,'_imccm_registration_enabled',true)!=='1') wp_send_json_error(['message'=>'此活動目前無法報名。'],400);
        if (empty($_POST['consent'])) wp_send_json_error(['message'=>'請先同意個人資料使用說明。'],400);
        $name=sanitize_text_field(wp_unslash($_POST['name']??'')); $phone=sanitize_text_field(wp_unslash($_POST['phone']??''));
        $email=sanitize_email(wp_unslash($_POST['email']??'')); $attendees=max(1,min(20,absint($_POST['attendees']??1)));
        if (!$name || !$phone) wp_send_json_error(['message'=>'請填寫姓名與手機。'],400);
        $deadline=get_post_meta($event_id,'_imccm_deadline',true); if($deadline&&strtotime($deadline)<current_time('timestamp')) wp_send_json_error(['message'=>'本活動報名已截止。'],400);
        $duplicate=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->registrations_table} WHERE event_id=%d AND phone=%s AND status NOT IN ('cancelled')",$event_id,$phone));
        if($duplicate) wp_send_json_error(['message'=>'這支手機已報名本活動，如需修改請聯絡承辦人。'],409);
        $remaining=$this->remaining_capacity($event_id); $waitlist=get_post_meta($event_id,'_imccm_waitlist_enabled',true)==='1';
        $status='confirmed'; if(is_int($remaining)&&$remaining<$attendees){ if(!$waitlist) wp_send_json_error(['message'=>'剩餘名額不足。'],409); $status='waitlist'; }
        $code='IMC'.wp_date('ymd').strtoupper(wp_generate_password(5,false,false)); $now=current_time('mysql');
        $ok=$wpdb->insert($this->registrations_table,['event_id'=>$event_id,'registration_code'=>$code,'name'=>$name,'email'=>$email,'phone'=>$phone,'member_no'=>sanitize_text_field(wp_unslash($_POST['member_no']??'')),'attendees'=>$attendees,'dietary'=>sanitize_text_field(wp_unslash($_POST['dietary']??'')),'note'=>sanitize_textarea_field(wp_unslash($_POST['note']??'')),'status'=>$status,'created_at'=>$now,'updated_at'=>$now],['%d','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s']);
        if(!$ok) wp_send_json_error(['message'=>'資料儲存失敗，請稍後再試。'],500);
        $label=$status==='confirmed'?'報名成功':'已列入候補'; $subject='['.$this->setting('club_name').'] '.$label.'：'.get_the_title($event_id);
        $body=$name.'您好：\n\n'.$label.'。\n活動：'.get_the_title($event_id).'\n報名代碼：'.$code.'\n人數：'.$attendees.'\n';
        if($email) wp_mail($email,$subject,$body); if($this->setting('send_admin_email')) wp_mail($this->setting('contact_email'),$subject,$body.'\n手機：'.$phone);
        wp_send_json_success(['message'=>$label.'！您的報名代碼是 '.$code.'。','code'=>$code,'status'=>$status]);
    }

    public function ajax_apply() {
        check_ajax_referer('imccm_frontend','nonce'); if(empty($_POST['consent'])) wp_send_json_error(['message'=>'請先同意個人資料使用說明。'],400);
        global $wpdb; $name=sanitize_text_field(wp_unslash($_POST['name']??'')); $phone=sanitize_text_field(wp_unslash($_POST['phone']??'')); if(!$name||!$phone) wp_send_json_error(['message'=>'請填寫姓名與手機。'],400);
        $wpdb->insert($this->applications_table,['name'=>$name,'phone'=>$phone,'email'=>sanitize_email(wp_unslash($_POST['email']??'')),'company'=>sanitize_text_field(wp_unslash($_POST['company']??'')),'title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'industry'=>sanitize_text_field(wp_unslash($_POST['industry']??'')),'referrer'=>sanitize_text_field(wp_unslash($_POST['referrer']??'')),'message'=>sanitize_textarea_field(wp_unslash($_POST['message']??'')),'status'=>'new','created_at'=>current_time('mysql')]);
        if(!$wpdb->insert_id) wp_send_json_error(['message'=>'資料儲存失敗。'],500);
        if($this->setting('send_admin_email')) wp_mail($this->setting('contact_email'),'[IMC] 新的入社申請：'.$name,"姓名：$name\n手機：$phone");
        wp_send_json_success(['message'=>'申請已送出，秘書處將與您聯絡。']);
    }

    private function remaining_capacity($event_id) {
        global $wpdb; $cap=absint(get_post_meta($event_id,'_imccm_capacity',true)); if(!$cap) return null;
        $used=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(attendees),0) FROM {$this->registrations_table} WHERE event_id=%d AND status IN ('confirmed','checked_in')",$event_id)); return max(0,$cap-$used);
    }

    public function admin_menu() {
        add_menu_page('IMC 社務中心','IMC 社務中心','manage_options','imccm',[$this,'dashboard_page'],'dashicons-groups',25);
        add_submenu_page('imccm','報名名單','報名名單','edit_posts','imccm-registrations',[$this,'registrations_page']);
        add_submenu_page('imccm','入社申請','入社申請','edit_posts','imccm-applications',[$this,'applications_page']);
        add_submenu_page('imccm','設定','設定','manage_options','imccm-settings',[$this,'settings_page']);
    }

    public function dashboard_page() {
        global $wpdb; $events=wp_count_posts('imc_event'); $regs=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->registrations_table}"); $apps=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->applications_table} WHERE status='new'"); ?>
        <div class="wrap"><h1>IMC 社務管理中心</h1><p>集中管理活動、報名、行事曆、入社申請與社務資料。</p><div class="imccm-cards"><div><b><?php echo esc_html($events->publish??0); ?></b><span>已發布活動</span></div><div><b><?php echo esc_html($regs); ?></b><span>累計報名</span></div><div><b><?php echo esc_html($apps); ?></b><span>待處理入社申請</span></div></div>
        <h2>快速開始</h2><ol><li>到「IMC 活動」新增活動並設定時間、名額及報名截止日。</li><li>在頁面加入 <code>[imc_calendar]</code> 顯示行事曆。</li><li>加入 <code>[imc_event_list]</code> 顯示近期活動，或 <code>[imc_registration id="活動ID"]</code> 顯示指定報名表。</li><li>入社頁面可加入 <code>[imc_join_form]</code>。</li></ol></div><?php
    }

    public function registrations_page() {
        if(!current_user_can('edit_posts')) return; global $wpdb; $event=absint($_GET['event_id']??0);
        $where=$event?$wpdb->prepare('WHERE r.event_id=%d',$event):''; $rows=$wpdb->get_results("SELECT r.*,p.post_title FROM {$this->registrations_table} r LEFT JOIN {$wpdb->posts} p ON p.ID=r.event_id $where ORDER BY r.created_at DESC LIMIT 500"); ?>
        <div class="wrap"><h1>活動報名名單</h1><p><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=imccm_export&event_id='.$event),'imccm_export')); ?>">匯出 CSV</a></p><table class="widefat striped"><thead><tr><th>代碼</th><th>活動</th><th>姓名</th><th>手機／Email</th><th>人數</th><th>飲食</th><th>狀態</th><th>時間</th><th>操作</th></tr></thead><tbody>
        <?php foreach($rows as $r): ?><tr><td><?php echo esc_html($r->registration_code); ?></td><td><?php echo esc_html($r->post_title); ?></td><td><?php echo esc_html($r->name); ?></td><td><?php echo esc_html($r->phone); ?><br><?php echo esc_html($r->email); ?></td><td><?php echo esc_html($r->attendees); ?></td><td><?php echo esc_html($r->dietary); ?></td><td><?php echo esc_html($this->status_label($r->status)); ?></td><td><?php echo esc_html($r->created_at); ?></td><td><?php foreach(['confirmed'=>'確認','checked_in'=>'簽到','cancelled'=>'取消'] as $s=>$label) echo '<a href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=imccm_status&id='.$r->id.'&status='.$s),'imccm_status_'.$r->id)).'">'.$label.'</a> '; ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php
    }

    public function applications_page() {
        if(!current_user_can('edit_posts')) return; global $wpdb; $rows=$wpdb->get_results("SELECT * FROM {$this->applications_table} ORDER BY created_at DESC LIMIT 500"); ?>
        <div class="wrap"><h1>入社申請</h1><table class="widefat striped"><thead><tr><th>姓名</th><th>聯絡</th><th>公司／職稱</th><th>產業</th><th>推薦人</th><th>內容</th><th>時間</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo esc_html($r->name); ?></td><td><?php echo esc_html($r->phone); ?><br><?php echo esc_html($r->email); ?></td><td><?php echo esc_html($r->company.' '.$r->title); ?></td><td><?php echo esc_html($r->industry); ?></td><td><?php echo esc_html($r->referrer); ?></td><td><?php echo esc_html($r->message); ?></td><td><?php echo esc_html($r->created_at); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
    }

    public function register_settings() { register_setting('imccm','imccm_settings',['sanitize_callback'=>[$this,'sanitize_settings']]); }
    public function sanitize_settings($v) { return ['club_name'=>sanitize_text_field($v['club_name']??''),'contact_email'=>sanitize_email($v['contact_email']??''),'contact_phone'=>sanitize_text_field($v['contact_phone']??''),'address'=>sanitize_text_field($v['address']??''),'primary_color'=>sanitize_hex_color($v['primary_color']??'#133b67')?:'#133b67','send_admin_email'=>empty($v['send_admin_email'])?0:1,'privacy_text'=>sanitize_textarea_field($v['privacy_text']??'')]; }
    public function settings_page() { $s=get_option('imccm_settings',[]); ?>
        <div class="wrap"><h1>IMC 設定</h1><form method="post" action="options.php"><?php settings_fields('imccm'); ?><table class="form-table"><?php foreach(['club_name'=>'社名','contact_email'=>'聯絡 Email','contact_phone'=>'聯絡電話','address'=>'社址','primary_color'=>'主色'] as $k=>$label): ?><tr><th><?php echo esc_html($label); ?></th><td><input class="regular-text" name="imccm_settings[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($s[$k]??''); ?>"></td></tr><?php endforeach; ?><tr><th>通知</th><td><label><input type="checkbox" name="imccm_settings[send_admin_email]" value="1" <?php checked(!empty($s['send_admin_email'])); ?>> 新報名／申請寄送通知</label></td></tr><tr><th>個資同意文字</th><td><textarea class="large-text" rows="3" name="imccm_settings[privacy_text]"><?php echo esc_textarea($s['privacy_text']??''); ?></textarea></td></tr></table><?php submit_button(); ?></form></div><?php }
    private function setting($key) { $s=get_option('imccm_settings',[]); return $s[$key]??''; }
    private function status_label($s) { return ['confirmed'=>'已確認','waitlist'=>'候補','checked_in'=>'已簽到','cancelled'=>'已取消'][$s]??$s; }

    public function update_registration_status() { if(!current_user_can('edit_posts')) wp_die('權限不足'); $id=absint($_GET['id']??0); check_admin_referer('imccm_status_'.$id); $status=sanitize_key($_GET['status']??''); if(!in_array($status,['confirmed','checked_in','cancelled'],true)) wp_die('狀態錯誤'); global $wpdb; $wpdb->update($this->registrations_table,['status'=>$status,'updated_at'=>current_time('mysql')],['id'=>$id]); wp_safe_redirect(wp_get_referer()?:admin_url('admin.php?page=imccm-registrations')); exit; }

    public function export_csv() { if(!current_user_can('edit_posts')) wp_die('權限不足'); check_admin_referer('imccm_export'); global $wpdb; $event=absint($_GET['event_id']??0); $where=$event?$wpdb->prepare('WHERE r.event_id=%d',$event):''; $rows=$wpdb->get_results("SELECT r.*,p.post_title FROM {$this->registrations_table} r LEFT JOIN {$wpdb->posts} p ON p.ID=r.event_id $where ORDER BY r.created_at ASC",ARRAY_A); nocache_headers(); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename=imc-registrations-'.wp_date('Ymd-His').'.csv'); $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['報名代碼','活動','姓名','手機','Email','社員編號','人數','飲食','備註','狀態','報名時間']); foreach($rows as $r) fputcsv($out,[$r['registration_code'],$r['post_title'],$r['name'],$r['phone'],$r['email'],$r['member_no'],$r['attendees'],$r['dietary'],$r['note'],$this->status_label($r['status']),$r['created_at']]); fclose($out); exit; }

    public function event_columns($c) { $c['imccm_date']='活動時間'; $c['imccm_reg']='報名狀況'; return $c; }
    public function event_column_value($col,$id) { if($col==='imccm_date') echo esc_html(get_post_meta($id,'_imccm_start',true)); if($col==='imccm_reg'){ $r=$this->remaining_capacity($id); echo $r===null?'不限名額':'剩餘 '.esc_html($r).' 名'; } }

    public function rest_routes() { register_rest_route('imc/v1','/events',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){ $q=$this->event_query(['posts_per_page'=>50]); $out=[]; while($q->have_posts()){ $q->the_post(); $id=get_the_ID(); $out[]=['id'=>$id,'title'=>get_the_title(),'start'=>get_post_meta($id,'_imccm_start',true),'end'=>get_post_meta($id,'_imccm_end',true),'venue'=>get_post_meta($id,'_imccm_venue',true),'url'=>get_permalink($id),'remaining'=>$this->remaining_capacity($id)]; } wp_reset_postdata(); return rest_ensure_response($out); }]); }

    public function download_ics() { $id=absint($_GET['event_id']??0); if(get_post_type($id)!=='imc_event') wp_die('找不到活動'); $start=get_post_meta($id,'_imccm_start',true); $end=get_post_meta($id,'_imccm_end',true)?:$start; $tz=wp_timezone(); $fmt=function($v)use($tz){$d=new DateTime($v,$tz);$d->setTimezone(new DateTimeZone('UTC'));return $d->format('Ymd\THis\Z');}; $esc=function($v){return str_replace(["\\",",",";","\n"],["\\\\","\\,","\\;","\\n"],wp_strip_all_tags($v));}; $ics="BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//IMC//Club Manager//ZH-TW\r\nBEGIN:VEVENT\r\nUID:imc-$id@".wp_parse_url(home_url(),PHP_URL_HOST)."\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:".$fmt($start)."\r\nDTEND:".$fmt($end)."\r\nSUMMARY:".$esc(get_the_title($id))."\r\nLOCATION:".$esc(get_post_meta($id,'_imccm_venue',true))."\r\nURL:".get_permalink($id)."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"; nocache_headers(); header('Content-Type:text/calendar;charset=UTF-8'); header('Content-Disposition:attachment;filename=imc-event-'.$id.'.ics'); echo $ics; exit; }
}

register_activation_hook(__FILE__, ['IMC_Club_Manager','activate']);
register_deactivation_hook(__FILE__, ['IMC_Club_Manager','deactivate']);
IMC_Club_Manager::instance();
IMCCM_Members::instance();
IMCCM_Checkin::instance();
IMCCM_Toolbox::instance();
