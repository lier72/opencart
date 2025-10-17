<?php echo $header; 
$theme_options = $this->registry->get('theme_options');
$config = $this->registry->get('config'); 
include('catalog/view/theme/' . $config->get('config_template') . '/template/new_elements/wrapper_top.tpl'); ?>

<div class="row">
    <div class="col-sm-12">
    <?php if(!empty($sections)):?>
        <div class="faq-area">
        <?php $i = 0; ?>
        <?php foreach($sections as $section):?>
            <?php if(!empty($section['items'])):?>
                <div class="faq-section">
                <?php if(!$section['hidden']):?>
                <div class="section-title"><?php echo $section['title']; ?></div>
                <?php endif; ?>
                <?php foreach($section['items'] as $item):?>
                    <?php if(trim($item['question']) == '') continue; ?>
                    <div class="panel panel-faq">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <?php if($settings['collapse']):?>
                                <a data-toggle="collapse" data-parent="#questions" href="#answer-<?php echo $i; ?>" aria-expanded="false" class="collapsed">
                                    <?php echo $item['question']; ?>
                                </a>
                                <?php else:?>
                                <span><?php echo $item['question']; ?></span>
                                <?php endif; ?>
                            </h4>
                        </div>
                        <div id="answer-<?php echo $i; ?>" <?php if($settings['collapse']):?> class="panel-collapse collapse" aria-expanded="false" <?php endif; ?>>
                            <div class="panel-body">
                                <?php echo $item['answer']; ?>
                            </div>
                        </div>
                    </div>
                <?php $i++; ?>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>    
    </div>
</div>


<?php include('catalog/view/theme/' . $config->get('config_template') . '/template/new_elements/wrapper_bottom.tpl'); ?>
<?php echo $footer; ?>