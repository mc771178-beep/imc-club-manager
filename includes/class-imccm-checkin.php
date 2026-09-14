<?php
if (!defined('ABSPATH')) { exit; }

final class IMCCM_Checkin {
    private static $instance;
    private $table;
    public static function instance(){if(!self::$instance)self::$instance=new self();return self::$instance;}
    private function __construct(){global $wpdb;$this->table=$wpdb->prefix.'imc_registrations';add_action('admin_menu',[$this,'menu'],30);add_action('admin_post_imccm_checkin',[$this,'checkin']);}
    public function menu(){add_submenu_page('imccm','現場簽到','現場簽到','edit_posts','imccm-checkin',[$this,'page']);}
    public function page(){if(!current_user_can('edit_posts'))return;global$wpdb;$code=strtoupper(sanitize_text_field(wp_unslash($_GET['code']??'')));$row=$code?$wpdb->get_row($wpdb->prepare("SELECT r.*,p.post_title FROM {$this->table} r LEFT JOIN {$wpdb->posts} p ON p.ID=r.event_id WHERE r.registration_code=%s",$code)):null;?>
        <div class="wrap"><h1>IMC 現場簽到</h1><form method="get"><input type="hidden" name="page" value="imccm-checkin"><label for="code">輸入或掃描報名代碼</label><input id="code" name="code" class="regular-text" value="<?php echo esc_attr($code);?>" autofocus><button class="button button-primary">查詢</button></form>
        <?php if($code&&!$row):?><div class="notice notice-error"><p>找不到此報名代碼。</p></div><?php endif;if($row):?><div class="card" style="margin-top:20px;max-width:650px"><h2><?php echo esc_html($row->name);?></h2><p><strong>活動：</strong><?php echo esc_html($row->post_title);?><br><strong>代碼：</strong><?php echo esc_html($row->registration_code);?><br><strong>人數：</strong><?php echo esc_html($row->attendees);?><br><strong>狀態：</strong><?php echo esc_html($row->status);?></p><?php if($row->status!=='checked_in'&&$row->status!=='cancelled'):?><a class="button button-primary button-hero" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=imccm_checkin&id='.$row->id),'imccm_checkin_'.$row->id));?>">確認簽到</a><?php endif;?></div><?php endif;?></div><?php}
    public function checkin(){if(!current_user_can('edit_posts'))wp_die('權限不足');$id=absint($_GET['id']??0);check_admin_referer('imccm_checkin_'.$id);global$wpdb;$code=$wpdb->get_var($wpdb->prepare("SELECT registration_code FROM {$this->table} WHERE id=%d",$id));$wpdb->update($this->table,['status'=>'checked_in','updated_at'=>current_time('mysql')],['id'=>$id]);wp_safe_redirect(admin_url('admin.php?page=imccm-checkin&code='.rawurlencode($code)));exit;}
}
