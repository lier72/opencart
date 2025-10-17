<?php
//heading
$_['heading_title']    = 'GCRdev sitemap';

//text
$_['text_extension']='Extensions';
$_['text_success']='Success: You have modified GCRdev sitemap generator';
$_['text_settingsSaved']='Success: Settings saved';
$_['text_catGenTrue']='Success: categories sitemap updated and indexed. <a href="'.HTTP_CATALOG.'sitemap/sitemap-index.xml" target="_blank">Click here to view the sitemap index.</a>';
$_['text_prodGenTrue']='Success: products sitemap updated and indexed. <a href="'.HTTP_CATALOG.'sitemap/sitemap-index.xml" target="_blank">Click here to view the sitemap index.</a>';
$_['text_brandGenTrue']='Success: brands sitemap updated and indexed. <a href="'.HTTP_CATALOG.'sitemap/sitemap-index.xml" target="_blank">Click here to view the sitemap index.</a>';
$_['text_infoGenTrue']='Success: information sitemap updated and indexed. <a href="'.HTTP_CATALOG.'sitemap/sitemap-index.xml" target="_blank">Click here to view the sitemap index.</a>';
$_['text_pageGenTrue']='Success: pages sitemap updated and indexed. <a href="'.HTTP_CATALOG.'sitemap/sitemap-index.xml" target="_blank">Click here to view the sitemap index.</a>';
$_['text_robGenTrue']='Success: Your robots.txt file has been updated. <a href="'.HTTP_CATALOG.'robots.txt" target="blank">Click here to view your robots file</a>';
$_['text_resProdTrue']='Success: products sitemap restored';
$_['text_resCatTrue']='Success: categories sitemap restored';
$_['text_resBrandTrue']='Success: brands sitemap restored';
$_['text_resInfoTrue']='Success: information sitemap restored';
$_['text_resPageTrue']='Success: pages sitemap restored';
$_['text_resFalse']='Fail: An original copy of your sitemap is yet to be made.';
$_['text_sitemap_header']='Sitemap generator';
$_['text_settings_header']='Settings';
$_['text_robots_header']='robots.txt generator';
$_['text_help_header']='Help';
$_['text_group'] = 'group';
$_['text_change'] = 'change';
$_['text_priority'] = 'priority';
$_['text_limit'] = 'limit';
$_['text_generate_sitemap'] = 'generate sitemap';
$_['text_generate'] = 'generate ';
$_['text_sitemap_gen'] = ' sitemap';
$_['text_view_sitemap'] = 'view';
$_['text_style'] = 'style';
$_['text_sitemap']=(file_exists("../sitemap.xml"))? '<i class="fa fa-check-circle"></i><a href="/sitemap.xml" target="_blank"><b>Click here to view your current sitemap</b></a>' : '<i class="fa fa-info-circle"></i>You don\'t currently have a sitemap';
$_['text_robots']=(file_exists("../robots.txt"))? '' : '<i class="fa fa-info-circle"></i>You don\'t currently have a robots file';

$_['text_sitemap_help']='<b>Sitemap generator</b><br>We have split the OpenCart shopping cart into the following 5 groups:<br><br>Products<br>Categories<br>Brands<br>Information<br>Pages<br><br>You can choose to generate sitemaps for any or all of the groups. To generate a sitemap for your chosen group, all you need to do is click the generate sitemap icon next to the group of your choosing. The software will then go through the database and create your sitemaps with the chosen content, creating separate sitemaps for each store you have created and its respective content.<br><br><b>Sitemap restorer</b><br>When you generate a sitemap, a backup copy of the previously generated sitemap will be saved. If you have made or notice an error with your newly generated sitemap, you can restore back to the previous sitemap by clicking the restore button. Only one backup copy is made for each generated sitemap, so be careful not to click the generate sitemap button twice else you\'ll be left with a backup copy which is identical to your new sitemap.<br><br><b>Submit sitemap</b><br>You\'ve generated your sitemaps, now it\'s time to submit them to search engines. At the bottom panel of the page you\'ll see a list of all your stores along with links to ping your sitemap index to Bing and GoOgle. Of course, the better way to submit your sitemaps to search engines would be to create an account with each of the engines which will give you the ability to monitor the indexing of your site. If you don\'t fancy the idea of creating accounts, simply click on the Bing and GoOgle links next to the store you want to submit.';

$_['text_robots_help']='<b>robots.txt generator</b><br>Quite a straightforward process, here you can either create or modify your robots file without having to open it up in a text editor. Simply place whatever information you want displayed in the file and click the tick underneath the input box.<br><br>Currently this only inserts or updates the robots.txt file in the root directory of your site.';

$_['text_settings_help']='<b>Include disabled</b><br>Here is where you choose whether or not to include disabled content within the sitemap. Currently there are only options to disable products, categories and information content of your site. A green light will include all disabled content within the sitemap, a red light will exclude disabled content from your sitemaps. So if you have any disabled products, categories or information pages you don\'t want indexed by the search engines or included in your sitemap, simply click each group until the light turns red followed by the save button at the bottom of the page. All changes must be saved before you generate the sitemaps in order for them to take effect.<br><br><b>Sitemap properties</b><br>Here you can set the change frequency, priority, limit and style for each group.<br><br><b>Change frequency</b><br>Set how often the particular group is changed. You can set this to either never, always, hourly, daily, weekly, monthly or yearly. Be honest with this, for instance if your product pages never change (not that new products aren\'t added, but if the information contained within each product page doesn\'t change) then set this to never. If you expect to be making changes on a monthly basis then set it to monthly. Search engines can penalise for incorrect information set here.<br><br><b>Priority</b><br>Set the priority for each group. I have pre-set these according to what I believe is the most to least important, feel free to change at will but don\'t set any two or more groups to the same priority.<br><br><b>Limit</b><br>Set how much content you want each sitemap to contain. Say you have 30,000 products and you want each sitemap to list 1,000 products then set the product limit to 1,000. When you save the settings and click to generate the products\' sitemap, it will create 30 pages each containing 1,000 products.<br><br><b>Style</b><br>Choose whether or not your sitemaps will be styled. It\'s not a necessity for a sitemap to contain a stylesheet and search engines will ignore styling anyway so it wouldn\'t make a difference to them; however, a styled sitemap can help you to easily identify any errors within your site such as missing images, wrong titles etc. Also, it could be an advantage should any of your customers choose to view your sitemap.<br><br><b>Remember you must click save before any of the changes you have made take effect.</b>';

//error
$_['error_permission'] = 'Warning: You do not have permission to modify GCRdev sitemap';

//buttons
$_['button_generate_robots'] = '<i class="fa fa-check"></i>';
$_['button_generate_robots_title'] = 'generate robots.txt';
$_['button_restore'] = '<i class="fa fa-rotate-left"></i>';
$_['button_restore_title'] = 'restore from backup';
$_['button_generate_sitemap'] = '<i class="fa fa-sitemap"></i>';
$_['button_generate_sitemap_title'] = 'generate sitemap';
$_['button_save_settings'] = '<i class="fa fa-save"></i>';
$_['button_save_settings_title'] = 'save sitemap settings';
$_['button_view_sitemap'] = '<i class="fa fa-eye"></i>';
$_['button_cancel'] = 'feeds';