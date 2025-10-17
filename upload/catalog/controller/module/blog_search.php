<?php  
/* 
Version: 1.0
Author: Artur Sułkowski
Website: http://artursulkowski.pl
*/

class ControllerModuleBlogSearch extends Controller {
	public function index($setting) {
		
		$this->load->language('blog/blog');

		$this->load->model('blog/category');
                                
        $data['position'] = $setting['position'];

        if(isset($setting['heading_title'][$this->config->get('config_language_id')])){
            $data['heading_title'] = $setting['heading_title'][$this->config->get('config_language_id')];
        }else{
            $data['heading_title'] = $this->language->get('heading_search_title');
        }
        
        $data['text_search'] = $this->language->get('text_search');
        $data['text_enter_keywords'] = $this->language->get('text_enter_keywords');
        
        $data['form_action'] = $this->url->link('blog/blog');

	
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/module/blog_search.tpl')) {
			return $this->load->view($this->config->get('config_template') . '/template/module/blog_search.tpl', $data);
		} else {
			return $this->load->view('default/template/module/blog_search.tpl', $data);
		}
	}
}
?>