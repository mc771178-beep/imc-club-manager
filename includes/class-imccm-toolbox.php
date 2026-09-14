<?php
if (!defined('ABSPATH')) { exit; }

final class IMCCM_Toolbox {
    private static $instance;
    public static function instance(){if(!self::$instance)self::$instance=new self();return self::$instance;}
    private function __construct(){add_action('init',[$this,'register']);add_action('admin_menu',[$this,'menu'],40);add_action('admin_post_imccm_demo_event',[$this,'demo']);add_shortcode('imc_notices',[$this,'notices']);}
    public function register(){register_post_type('imc_notice',['labels'=>['name'=>'IMC 公告','singular_name'=>'公告','add_new'=>'新增公告','add_new_item'=>'新增公告','menu_name'=>'IMC 公告'],'public'=>true,'show_in_rest'=>true,'show_in_menu'=>'imccm','has_archive'=>true,'rewrite'=>['slug'=>'imc-notices'],'supports'=>['title','editor','excerpt','thumbnail','revisions']]);}
    public function menu(){add_submenu_page('imccm','工具與技能','工具與技能','manage_options','imccm-toolbox',[$this,'page']);}
    public function page(){global$wpdb;$reg=$wpdb->prefix.'imc_registrations';$app=$wpdb->prefix.'imc_applications';$checks=[['活動資料類型',post_type_exists('imc_event')],['社員資料類型',post_type_exists('imc_member')],['公告資料類型',post_type_exists('imc_notice')],['報名資料表',$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$reg))===$reg],['入社申請資料表',$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$app))===$app],['網站時區',wp_timezone_string()!==''],['通知信箱',is_email(get_option('admin_email'))]];$tools=[
        ['活動企劃工具','建立例會、講座、公益、觀摩及次團活動；管理時間、地點、名額、費用與窗口。','已啟用'],
        ['行事曆工具','月曆顯示、近期活動、ICS 匯出，以及供 APP／LINE 使用的活動 API。','已啟用'],
        ['報名與候補工具','站內報名、額滿候補、重複檢查、報名代碼、Email 通知與 CSV。','已啟用'],
        ['現場簽到工具','以報名代碼查詢並完成簽到，保留出席狀態。','已啟用'],
        ['社員名冊工具','社員編號、公司、職稱、屆次、職務、次團與 CSV 匯出。','已啟用'],
        ['入社申請工具','蒐集申請人、公司、產業、推薦人與申請原因。','已啟用'],
        ['公告工具','發布重要消息並以短代碼放入既有頁面。','已啟用'],
        ['社務知識庫','保存章程、歷屆、講師、社刊、相簿與下載文件。','已啟用'],
        ['LINE 推播技能','需另外提供 LINE 官方帳號 Messaging API 憑證。','待串接'],
        ['線上金流技能','需決定綠界、藍新或匯款對帳流程及會計權限。','待串接'],
        ['Google 雙向同步','需 Google OAuth 憑證與同步規則；目前已提供 ICS 匯出。','待串接'],
    ];?>
    <div class="wrap"><h1>IMC 外掛工具與技能</h1><p><strong>這是 WordPress 外掛控制台，不是獨立網站。</strong>所有工具都在既有 WordPress 後台運作。</p><h2>系統檢查</h2><table class="widefat striped" style="max-width:850px"><tbody><?php foreach($checks as$c):?><tr><td><?php echo esc_html($c[0]);?></td><td><?php echo $c[1]?'<span style="color:#08783e">● 正常</span>':'<span style="color:#b42318">● 請檢查</span>';?></td></tr><?php endforeach;?></tbody></table><h2>功能工具</h2><table class="widefat striped"><thead><tr><th>工具／技能</th><th>可以處理的問題</th><th>狀態</th></tr></thead><tbody><?php foreach($tools as$t):?><tr><td><strong><?php echo esc_html($t[0]);?></strong></td><td><?php echo esc_html($t[1]);?></td><td><?php echo esc_html($t[2]);?></td></tr><?php endforeach;?></tbody></table><h2>快速工具</h2><p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=imccm_demo_event'),'imccm_demo_event'));?>">建立一筆示範活動草稿</a> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=imccm_export_members'),'imccm_export_members'));?>">匯出社員 CSV</a></p></div><?php}
    public function demo(){if(!current_user_can('manage_options'))wp_die('權限不足');check_admin_referer('imccm_demo_event');$id=wp_insert_post(['post_type'=>'imc_event','post_status'=>'draft','post_title'=>'IMC 月例會（示範）','post_content'=>'請在此填入流程、講師與注意事項。']);if(!is_wp_error($id)){update_post_meta($id,'_imccm_start',wp_date('Y-m-d\T19:00',strtotime('+14 days')));update_post_meta($id,'_imccm_end',wp_date('Y-m-d\T21:00',strtotime('+14 days')));update_post_meta($id,'_imccm_capacity','100');update_post_meta($id,'_imccm_registration_enabled','1');update_post_meta($id,'_imccm_waitlist_enabled','1');}wp_safe_redirect(admin_url('post.php?action=edit&post='.absint($id)));exit;}
    public function notices($atts){$a=shortcode_atts(['limit'=>'5'],$atts);$q=new WP_Query(['post_type'=>'imc_notice','post_status'=>'publish','posts_per_page'=>min(20,absint($a['limit']))]);ob_start();echo '<div class="imccm-notice-list">';while($q->have_posts()){$q->the_post();echo '<article><time>'.esc_html(get_the_date('Y/m/d')).'</time><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><p>'.esc_html(get_the_excerpt()).'</p></article>';}wp_reset_postdata();echo '</div>';return ob_get_clean();}
}
