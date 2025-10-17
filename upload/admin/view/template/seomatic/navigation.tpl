<?php

  /**
  *
  *   SEOMatic Navigation
  *     - Adds the SEOMatic Dropdown to the Top Navigation
  *
  **/



  //Load the Language
  $this->registry->get('language')->load('seomatic/navigation');

  //Prepare URLS
  $urls = array(
    'account'   => $this->registry->get('url')->link('seomatic/account', 'token=' . $this->registry->get('session')->data['token'], 'SSL'),
    'profile'   => $this->registry->get('url')->link('seomatic/account', 'token=' . $this->registry->get('session')->data['token'], 'SSL') . '#tab-profile',
    'campaign'  => $this->registry->get('url')->link('seomatic/account', 'token=' . $this->registry->get('session')->data['token'], 'SSL') . '#tab-account',
    'plans'     => $this->registry->get('url')->link('seomatic/account', 'token=' . $this->registry->get('session')->data['token'], 'SSL') . '#tab-plans',
    'cards'     => $this->registry->get('url')->link('seomatic/account', 'token=' . $this->registry->get('session')->data['token'], 'SSL') . '#tab-cards',
    'invoices'  => $this->registry->get('url')->link('seomatic/account', 'token=' . $this->registry->get('session')->data['token'], 'SSL') . '#tab-invoices',
    'keywords'  => $this->registry->get('url')->link('seomatic/keywords', 'token=' . $this->registry->get('session')->data['token'], 'SSL'),
    'changelog' => $this->registry->get('url')->link('seomatic/changelog', 'token=' . $this->registry->get('session')->data['token'], 'SSL'),
    'caching'   => $this->registry->get('url')->link('seomatic/caching', 'token=' . $this->registry->get('session')->data['token'], 'SSL'),
    'qna'       => $this->registry->get('url')->link('seomatic/qna', 'token=' . $this->registry->get('session')->data['token'], 'SSL'),
    'register'  => $this->registry->get('url')->link('seomatic/register', 'token=' . $this->registry->get('session')->data['token'], 'SSL')
  );

  //Load the Language
  $this->registry->get('language')->load('seomatic/navigation');


?>

        <li id="seomatic">
          <a class="parent"><i class="fa fa-line-chart fa-fw"></i> <span>SEOMatic</span></a>
          <ul>
            <?php if( $this->registry->get('config')->get('seomatic_email') && $this->registry->get('SEOMatic')->connected ){ ?>
              <li>
                <a class="parent"><span><?php echo $this->registry->get('language')->get('text_account'); ?></a>
                <ul>
                  <li><a href="<?php echo $urls['profile']; ?>"><?php echo $this->registry->get('language')->get('text_profile'); ?></a></li>
                  <li><a href="<?php echo $urls['campaign']; ?>"><?php echo $this->registry->get('language')->get('text_campaign'); ?></a></li>
                  <li><a href="<?php echo $urls['plans']; ?>"><?php echo $this->registry->get('language')->get('text_plans'); ?></a></li>
                  <li><a href="<?php echo $urls['cards']; ?>"><?php echo $this->registry->get('language')->get('text_cards'); ?></a></li>
                  <li><a href="<?php echo $urls['invoices']; ?>"><?php echo $this->registry->get('language')->get('text_invoices'); ?></a></li>
                </ul>
              </li>
              <?php if($this->registry->get('config')->get('seomatic_account') ){ ?>
                <li><a href="<?php echo $urls['keywords']; ?>"><?php echo $this->registry->get('language')->get('text_keywords'); ?></a></li>
                <!--
                <li><a href="<?php echo $urls['changelog']; ?>"><?php echo $this->registry->get('language')->get('text_changelog'); ?></a></li>
                <li><a href="<?php echo $urls['caching']; ?>"><?php echo $this->registry->get('language')->get('text_caching'); ?></a></li>
                <li><a href="<?php echo $urls['qna']; ?>"><?php echo $this->registry->get('language')->get('text_qna'); ?></a></li>
                -->
              <?php } ?>
            <?php }else{ ?>
              <li><a href="<?php echo $urls['register']; ?>"><?php echo $this->registry->get('language')->get('text_register'); ?></a></li>
              <li><a href="<?php echo $urls['account']; ?>"><?php echo $this->registry->get('language')->get('text_signin'); ?></a></li>
            <?php } ?>
          </ul>
        </li>
      