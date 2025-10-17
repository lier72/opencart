<?php  
/* 
Version: 1.0
Author: Artur Sułkowski
Website: http://artursulkowski.pl
*/

class ControllerModuleBlogTags extends Controller {
	public function index($setting) {
		
		$this->load->language('blog/blog');

		$this->load->model('blog/article');
                                
        $data['position'] = $setting['position'];
		
        if(isset($setting['heading_title'][$this->config->get('config_language_id')])){
            $data['heading_title'] = $setting['heading_title'][$this->config->get('config_language_id')];
        }else{
            $data['heading_title'] = $this->language->get('heading_tags_title');
        }
        
        $data['config'] = $this->config;
	
		$data['tags'] = array();
		$tags = $this->model_blog_article->getPopularTags();
        if(!empty($tags)){
            foreach($tags as $tag => $value){
                
                $data['tags'][] = array(
                    'tag'  => trim($tag),
                    'value'  => $value,
                    'href' => $this->url->link('blog/blog', 'tag=' . trim($tag))
                );
            }
        }

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/module/blog_tags.tpl')) {
			return $this->load->view($this->config->get('config_template') . '/template/module/blog_tags.tpl', $data);
		} else {
			return $this->load->view('default/template/module/blog_tags.tpl', $data);
		}
	}
}
?>