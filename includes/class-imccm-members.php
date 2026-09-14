<?php
if (!defined('ABSPATH')) { exit; }

final class IMCCM_Members {
    private static $instance;
    public static function instance() { if (!self::$instance) self::$instance = new self(); return self::$instance; }
    private function __construct() {
        add_action('init', [$this, 'register']);
        add_action('add_meta_boxes', [$this, 'meta_box']);
        add_action('save_post_imc_member', [$this, 'save']);
        add_filter('manage_imc_member_posts_columns', [$this, 'columns']);
        add_action('manage_imc_member_posts_custom_column', [$this, 'column'], 10, 2);
        add_action('admin_post_imccm_export_members', [$this, 'export']);
        add_shortcode('imc_member_directory', [$this, 'directory']);
    }
    public function register() {
        register_post_type('imc_member', [
            'labels'=>['name'=>'社員名冊','singular_name'=>'社員','add_new'=>'新增社員','add_new_item'=>'新增社員','edit_item'=>'編輯社員','menu_name'=>'IMC 社員名冊'],
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'imccm','show_in_rest'=>false,'supports'=>['title','thumbnail'],'menu_icon'=>'dashicons-id-alt',
        ]);
    }
    public function meta_box() { add_meta_box('imccm-member','社員資料',[$this,'render'],'imc_member','normal','high'); }
    public function render($post) {
        wp_nonce_field('imccm_member_save','imccm_member_nonce');
        $fields=['number'=>'社員編號','company'=>'公司名稱','job_title'=>'職稱','phone'=>'手機','email'=>'Email','join_date'=>'入社日期','term'=>'屆次','committee'=>'社務職務','groups'=>'所屬次團','status'=>'社員狀態'];
        echo '<div class="imccm-admin-grid">'; foreach($fields as $key=>$label){$v=get_post_meta($post->ID,'_imccm_member_'.$key,true);$type=$key==='email'?'email':($key==='join_date'?'date':'text');printf('<p><label><strong>%s</strong><br><input style="width:100%%" type="%s" name="imccm_member_%s" value="%s"></label></p>',esc_html($label),$type,esc_attr($key),esc_attr($v));} echo '</div><p class="description">社員姓名請填在上方標題。社員資料預設只在後台顯示。</p>';
    }
    public function save($id) {
        if (!isset($_POST['imccm_member_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['imccm_member_nonce'])),'imccm_member_save')) return;
        if ((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||!current_user_can('edit_post',$id)) return;
        foreach(['number','company','job_title','phone','email','join_date','term','committee','groups','status'] as $key){$raw=wp_unslash($_POST['imccm_member_'.$key]??'');$v=$key==='email'?sanitize_email($raw):sanitize_text_field($raw);update_post_meta($id,'_imccm_member_'.$key,$v);}
    }
    public function columns($c){return ['cb'=>$c['cb'],'title'=>'社員姓名','number'=>'社員編號','company'=>'公司／職稱','phone'=>'聯絡方式','committee'=>'職務／次團','status'=>'狀態','date'=>'建檔日期'];}
    public function column($col,$id){$g=function($k)use($id){return get_post_meta($id,'_imccm_member_'.$k,true);};if($col==='number')echo esc_html($g('number'));if($col==='company')echo esc_html(trim($g('company').' '.$g('job_title')));if($col==='phone')echo esc_html($g('phone')).'<br>'.esc_html($g('email'));if($col==='committee')echo esc_html(trim($g('committee').' '.$g('groups')));if($col==='status')echo esc_html($g('status'));}
    public function export(){if(!current_user_can('edit_posts'))wp_die('權限不足');check_admin_referer('imccm_export_members');$q=new WP_Query(['post_type'=>'imc_member','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC']);nocache_headers();header('Content-Type:text/csv;charset=UTF-8');header('Content-Disposition:attachment;filename=imc-members-'.wp_date('Ymd-His').'.csv');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['社員姓名','社員編號','公司','職稱','手機','Email','入社日期','屆次','社務職務','次團','狀態']);while($q->have_posts()){$q->the_post();$id=get_the_ID();$row=[get_the_title()];foreach(['number','company','job_title','phone','email','join_date','term','committee','groups','status']as$k)$row[]=get_post_meta($id,'_imccm_member_'.$k,true);fputcsv($out,$row);}wp_reset_postdata();fclose($out);exit;}
    public function directory($atts){$a=shortcode_atts(['public'=>'0','limit'=>'100'],$atts);if($a['public']!=='1'&&!is_user_logged_in())return '<p class="imccm-notice">此名冊僅供已登入社員查看。</p>';$q=new WP_Query(['post_type'=>'imc_member','post_status'=>'publish','posts_per_page'=>min(500,absint($a['limit'])),'orderby'=>'title','order'=>'ASC']);ob_start();echo '<div class="imccm-member-directory">';while($q->have_posts()){$q->the_post();$id=get_the_ID();echo '<article><h3>'.esc_html(get_the_title()).'</h3><p>'.esc_html(get_post_meta($id,'_imccm_member_company',true)).' · '.esc_html(get_post_meta($id,'_imccm_member_job_title',true)).'</p><small>'.esc_html(get_post_meta($id,'_imccm_member_committee',true)).'</small></article>';}wp_reset_postdata();echo '</div>';return ob_get_clean();}
}
