<?php
class ModelFeedGcrdevSitemap extends Model {

private function createIndexStyle($title,$store_id,$group){
$sitemap_index_file='../sitemap/'.$store_id.'/'.$group.'/xsl/sitemap-index.xsl';
$sitemap_index_handle=fopen($sitemap_index_file, 'w') or die('Cannot open file:  '.$sitemap_index_file);

$decompress = file_get_contents('../sitemap/xsl/sitemap-index/1.xsl');
$decompress .='<title>'.$title.'</title>';
$decompress .= file_get_contents('../sitemap/xsl/sitemap-index/2.xsl');
$decompress .='<h1>'.$title.'</h1>';
$decompress .= file_get_contents('../sitemap/xsl/sitemap-index/3.xsl');
$decompress .='<a href="/sitemap/'.$store_id.'/sitemap-index.xml">sitemap index</a></p>';
$decompress .= file_get_contents('../sitemap/xsl/sitemap-index/4.xsl');
fwrite($sitemap_index_handle, $decompress);
}

private function createStyle($title,$store_id,$group){
$sitemap_index_file='../sitemap/'.$store_id.'/'.$group.'/xsl/sitemap.xsl';
$sitemap_index_handle=fopen($sitemap_index_file, 'w') or die('Cannot open file:  '.$sitemap_index_file);

$decompress = file_get_contents('../sitemap/xsl/map-style/1.xsl');
$decompress .='<title>'.$title.'</title>';
$decompress .= file_get_contents('../sitemap/xsl/map-style/2.xsl');
$decompress .='<h1>'.$title.'</h1>';
$decompress .= file_get_contents('../sitemap/xsl/map-style/3.xsl');
$decompress .='<a href="/sitemap/'.$store_id.'/sitemap-index.xml">sitemap index</a></p>
<a href="/sitemap/'.$store_id.'/'.$group.'/sitemap-index.xml">'.$group.' index</a>';
$decompress .= file_get_contents('../sitemap/xsl/map-style/4.xsl');
fwrite($sitemap_index_handle, $decompress);
}

private function removeFiles($removeFiles){
$files=glob($removeFiles);
foreach($files as $file){
if(is_file($file))
unlink($file);
}
}

private function removeDir($dir){
if (is_dir($dir)){
rmdir($dir);
}
}

private function recurse_copy($src,$dst){
$dir=opendir($src);
@mkdir($dst);
while(false !== ($file=readdir($dir))){
if (($file != '.')&&($file != '..')&&($file != 'backup')){
if (is_dir($src.'/'.$file)){
$this->recurse_copy($src.'/'.$file,$dst.'/'.$file); 
}else{
copy($src.'/'.$file,$dst.'/'.$file); 
}
}
}
closedir($dir); 
}

private function make_dir($src){
if(!file_exists($src)){
mkdir($src, 0777, true);
}
}

private function delete_dir(){
if (is_dir($path) === true){
$files = array_diff(scandir($path), array('.', '..'));
foreach ($files as $file){
delete_dir(realpath($path) . '/' . $file);
}
return rmdir($path);
}
else if (is_file($path) === true){
return unlink($path);
}
}

private function getStoreId(){
$query=$this->db->query("SELECT DISTINCT store_id FROM ".DB_PREFIX."setting");
return $query->rows;
}

private function generateIndex(){
$sitemapIndex='../sitemap/sitemap-index.xml';
$sitemapIndexHandle=fopen($sitemapIndex, 'w') or die('Cannot open file:  '.$sitemapIndex);
$storedata='<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="/sitemap/xsl/stores/sitemap-index.xsl"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

$produpdate='';
$query=$this->db->query("SELECT last_update FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='products'");
$config=$query->rows;
foreach($config as $config){
$produpdate .=date('Y-m-d', strtotime($config['last_update']));
}
$catupdate='';
$query=$this->db->query("SELECT last_update FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='categories'");
$config=$query->rows;
foreach($config as $config){
$catupdate .=date('Y-m-d', strtotime($config['last_update']));
}
$brandupdate='';
$query=$this->db->query("SELECT last_update FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='brands'");
$config=$query->rows;
foreach($config as $config){
$brandupdate .=date('Y-m-d', strtotime($config['last_update']));
}
$infoupdate='';
$query=$this->db->query("SELECT last_update FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='information'");
$config=$query->rows;
foreach($config as $config){
$infoupdate .=date('Y-m-d', strtotime($config['last_update']));
}
$pageupdate='';
$query=$this->db->query("SELECT last_update FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='pages'");
$config=$query->rows;
foreach($config as $config){
$pageupdate .=date('Y-m-d', strtotime($config['last_update']));
}

$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$query=$this->db->query("SELECT value FROM ".DB_PREFIX."setting WHERE `store_id`='$store_id' AND `key`='config_url'");
$config_url=$query->rows;
$store_url='';
foreach($config_url as $config_url){
$store_url .=$config_url['value'];
}

$storeurl=($store_url == '')? HTTP_CATALOG : $store_url;
$file='../sitemap/'.$store_id.'/sitemap-index.xml';
$handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
$storedata .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/sitemap-index.xml</loc><lastmod>'.date('Y-m-d').'</lastmod></sitemap>';

$data='<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/xsl/sitemap-index.xsl"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
if($produpdate != '1970-01-01'){
$data .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/products/sitemap-index.xml</loc><lastmod>'.$produpdate.'</lastmod></sitemap>';
}
if($catupdate != '1970-01-01'){
$data .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/categories/sitemap-index.xml</loc><lastmod>'.$catupdate.'</lastmod></sitemap>';
}
if($brandupdate != '1970-01-01'){
$data .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/brands/sitemap-index.xml</loc><lastmod>'.$brandupdate.'</lastmod></sitemap>';
}
if($infoupdate != '1970-01-01'){
$data .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/information/sitemap-index.xml</loc><lastmod>'.$infoupdate.'</lastmod></sitemap>';
}
if($pageupdate != '1970-01-01'){
$data .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/pages/sitemap.xml</loc><lastmod>'.$pageupdate.'</lastmod></sitemap>';
}
$data .='</sitemapindex>';
fwrite($handle, $data);
}
$storedata .='</sitemapindex>';
fwrite($sitemapIndexHandle, $storedata);
}

public function install(){
@$this->db->query("
CREATE TABLE `".DB_PREFIX."gcrdev_sitemap`(
`id` INT(11) NOT NULL AUTO_INCREMENT,
`groups` varchar(32) NOT NULL,
`changefreq` varchar(64) NOT NULL,
`prio` DECIMAL(2,1) NOT NULL,
`indlim` INT(15) NOT NULL,
`prodstyle` tinyint(1) NOT NULL,
`lastid` INT(126) NOT NULL,
`status` tinyint(1) NOT NULL,
`last_update` datetime NOT NULL,
PRIMARY KEY (`id`)
)ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
");
$this->db->query("INSERT INTO ".DB_PREFIX."gcrdev_sitemap SET groups='products',`changefreq`='never',`prio`='1.0',`indlim`='1000',`prodstyle`='1',`status`='1'");
$this->db->query("INSERT INTO ".DB_PREFIX."gcrdev_sitemap SET groups='categories',`changefreq`='never',`prio`='0.9',`indlim`='1000',`prodstyle`='1',`status`='1'");
$this->db->query("INSERT INTO ".DB_PREFIX."gcrdev_sitemap SET groups='brands',`changefreq`='never',`prio`='0.8',`indlim`='1000',`prodstyle`='1',`status`='1'");
$this->db->query("INSERT INTO ".DB_PREFIX."gcrdev_sitemap SET groups='information',`changefreq`='never',`prio`='0.7',`indlim`='1000',`prodstyle`='1'");
$this->db->query("INSERT INTO ".DB_PREFIX."gcrdev_sitemap SET groups='pages',`changefreq`='never',`prio`='0.6',`indlim`='1000',`prodstyle`='1',`status`='1'");
$this->db->query("INSERT INTO ".DB_PREFIX."setting SET code='gcrdev_sitemap',`key`='gcrdev_sitemap_status',`value`='1'");
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
if(!file_exists('../sitemap/'.$store_id.'/brands/xsl')){
mkdir('../sitemap/'.$store_id.'/brands/xsl', 0777, true);
}
if(!file_exists('../sitemap/'.$store_id.'/categories/xsl')){
mkdir('../sitemap/'.$store_id.'/categories/xsl', 0777, true);
}
if(!file_exists('../sitemap/'.$store_id.'/information/xsl')){
mkdir('../sitemap/'.$store_id.'/information/xsl', 0777, true);
}
if(!file_exists('../sitemap/'.$store_id.'/pages/xsl')){
mkdir('../sitemap/'.$store_id.'/pages/xsl', 0777, true);
}
if(!file_exists('../sitemap/'.$store_id.'/products/xsl')){
mkdir('../sitemap/'.$store_id.'/products/xsl', 0777, true);
}
}
}

public function uninstall(){
$this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."gcrdev_sitemap`");
}

public function getData(){
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."gcrdev_sitemap");
return $query->rows;
}

public function getProdSet(){
$query=$this->db->query("SELECT status FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='products'");
$row=$query->rows;
$check='';
foreach($row as $row){
$check .=($row['status']==1)? ' checked' : '';
}
return $check;
}

public function getCatSet(){
$query=$this->db->query("SELECT status FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='categories'");
$row=$query->rows;
$check='';
foreach($row as $row){
$check .=($row['status']==1)? ' checked' : '';
}
return $check;
}

public function getInfoSet(){
$query=$this->db->query("SELECT status FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='information'");
$row=$query->rows;
$check='';
foreach($row as $row){
$check .=($row['status']==1)? ' checked' : '';
}
return $check;
}

public static function alert($sess,$class){
if(isset($_SESSION[$sess])){
print '<div class="alert alert-'.$class.'">'.$_SESSION[$sess];
unset($_SESSION[$sess]);
print '<button type="button" class="close" data-dismiss="alert">&times;</button></div>';
}
}

public function restore($group,$resp){
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$src='../sitemap/'.$store_id.'/'.$group.'/';
$dst='../sitemap/'.$store_id.'/'.$group.'/restore/';
if(file_exists('../sitemap/'.$store_id.'/'.$group.'/backup/')){
$dir=opendir($src);
@mkdir($dst);
while(false !== ($file=readdir($dir))){
if (($file != '.')&& ($file != '..')&&($file != 'restore')&&($file != 'backup')){
if (is_dir($src.'/'.$file)){
$this->recurse_copy($src.'/'.$file,$dst.'/'.$file);
}else{
copy($src.'/'.$file,$dst.'/'.$file); 
}
}
}
closedir($dir);
$this->removeFiles($src.'*.xml');
$this->removeFiles($src.'xsl/*');
$this->recurse_copy($src.'backup',$src);
$this->removeFiles($src.'backup/*');
$this->removeFiles($src.'backup/xsl/*');
$this->recurse_copy($src.'restore',$src.'backup');
$this->removeFiles($src.'restore/*');
$this->removeFiles($src.'restore/xsl/*');
rmdir($dst.'xsl/');
rmdir($dst);
$_SESSION['resPageTrue']=$resp;
}else{
$_SESSION['resFalse']=$this->language->get('text_resFalse');
$this->response->redirect($this->url->link('feed/gcrdev_sitemap', 'token='.$this->session->data['token'], true));
exit();
}
}
}

public function updateSettings($id,$change,$priority,$style,$indlim){
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `changefreq`='".$change."', `prio`='".$priority."', `prodstyle`='".$style."', `indlim`='".$indlim."'  WHERE `id`='".$id."'");
}

public function updateIncStatus($disprod,$disinfo,$discat){
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `status`='".$disprod."' WHERE `groups`='products'");
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `status`='".$disinfo."' WHERE `groups`='information'");
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `status`='".$discat."' WHERE `groups`='categories'");
}

public function generateRobots($post){
$file='../robots.txt';
$robots_handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
fwrite($robots_handle, str_replace('\\r\\n', '
', $post));
}

//products
public function generateProducts(){
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$query=$this->db->query("SELECT value FROM ".DB_PREFIX."setting WHERE `store_id`='$store_id' AND `key`='config_url'");
$config_url=$query->rows;
$store_url='';
foreach($config_url as $config_url){
$store_url .=$config_url['value'];
}
$storeurl=($store_url == '')? HTTP_CATALOG : $store_url;
$src='../sitemap/'.$store_id.'/products/';
$dst='../sitemap/'.$store_id.'/products/backup/';
$xsl='../sitemap/'.$store_id.'/products/xsl/';
$this->make_dir($src);
$this->make_dir($dst);
$this->removeFiles($dst.'xsl/*');
$this->removeFiles($dst.'*');
$this->recurse_copy($src,$dst);
$this->make_dir($xsl);

$this->removeDir($dst.'backup/xsl/');
$this->removeDir($dst.'backup/');

$this->removeFiles($src.'*');

$priority='';
$statusd='';
$prodstyle='';
$changefreq='';
$indlim='';
$query=$this->db->query("SELECT prio,indlim,changefreq,prodstyle,status FROM ".DB_PREFIX."gcrdev_sitemap WHERE `groups`='products'");
$row=$query->rows;
foreach($row as $row){
$priority .=$row['prio'];
$indlim .=$row['indlim'];
$statusd .=$row['status'];
$prodstyle .=$row['prodstyle'];
$changefreq .=$row['changefreq'];
}
$sitemap_index_file=$src.'sitemap-index.xml';
$sitemap_index_handle=fopen($sitemap_index_file, 'w') or die('Cannot open file:  '.$sitemap_index_file);
$sitemap_index='<?xml version="1.0" encoding="UTF-8"?>';
if($prodstyle == '1'){
$sitemap_index .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/products/xsl/sitemap-index.xsl"?>';
}
$sitemap_index .='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

$status=($statusd==0)? ' AND status=1' : '';


$query=$this->db->query("SELECT * FROM ".DB_PREFIX."product_to_store WHERE store_id='$store_id'");
$productCount=$query->num_rows;
$prodpages=ceil($productCount/$indlim);
for($i=1;$i<=$prodpages;$i++){
$lastid='';
$query=$this->db->query("SELECT lastid FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='products'");
$last_id=$query->rows;
foreach($last_id as $last_id){
$lastid .=$last_id['lastid'];
}
$status=($statusd==0)? ' status=1 AND ' : '';
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."product_to_store WHERE product_id > '$lastid' AND store_id='$store_id' LIMIT $indlim");
$prodrow=$query->rows;
$file='../sitemap/'.$store_id.'/products/sitemap_'.$i.'.xml';
$handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
$data='<?xml version="1.0" encoding="UTF-8"?>';
if($prodstyle == '1'){
$data .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/products/xsl/sitemap.xsl"?>';
}
$data .='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

foreach($prodrow as $prodrow){
$product_id=$prodrow['product_id'];
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."product WHERE".$status." product_id ='$product_id'");
$row=$query->rows;
foreach($row as $row){
$product_id=$row['product_id'];
$producturl='product_id='.$product_id;
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."url_alias WHERE query='$producturl'");
$url_alias=$query->rows;
foreach($url_alias as $alias){
$data .='<url><loc>'.$storeurl.''.str_replace("&", "", $alias['keyword']).'</loc>';


if($row['image'] != ''){
$data .='<image:image><image:loc>'.$storeurl.'/image/'.$row['image'].'</image:loc>';
$query=$this->db->query("SELECT meta_title FROM ".DB_PREFIX."product_description WHERE product_id='$product_id'");
$product_title=$query->rows;
foreach($product_title as $title){
$data .='<image:title>'.$title['meta_title'].'</image:title>';
}
$data .='</image:image>';
}

$query=$this->db->query("SELECT * FROM ".DB_PREFIX."product_image WHERE product_id='$product_id'");
$product_image=$query->rows;
foreach($product_image as $image){
$data .='<image:image><image:loc>'.$storeurl.'image/'.$image['image'].'</image:loc>';
$query=$this->db->query("SELECT meta_title FROM ".DB_PREFIX."product_description WHERE product_id='$product_id'");
$product_title=$query->rows;
foreach($product_title as $title){
$data .='<image:title>'.$title['meta_title'].'</image:title>';
}
$data .='</image:image>';
}
$data .='<changefreq>'.$changefreq.'</changefreq><lastmod>'.date('Y-m-d', strtotime($row['date_modified'])).'</lastmod><priority>'.$priority.'</priority></url>';

$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='".$product_id."' WHERE `groups`='products'");
}
}
}
$data .= '</urlset>';
fwrite($handle, $data);

$sitemap_index .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/products/sitemap_'.$i.'.xml</loc><lastmod>'.date('Y-m-d').'</lastmod></sitemap>';
}
$sitemap_index .='</sitemapindex>';
fwrite($sitemap_index_handle, $sitemap_index);

$this->createIndexStyle('products index',$store_id,'products');
$this->createStyle('products sitemap',$store_id,'products');

$last_update=date('Y-m-d H:i:s');

$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='0', last_update='$last_update' WHERE `groups`='products'");
}
$this->generateIndex();
}
//**products

public function generateCategories(){
//categories
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$query=$this->db->query("SELECT value FROM ".DB_PREFIX."setting WHERE `store_id`='$store_id' AND `key`='config_url'");
$config_url=$query->rows;
$store_url='';
foreach($config_url as $config_url){
$store_url .=$config_url['value'];
}
$storeurl=($store_url == '')? HTTP_CATALOG : $store_url;
$src='../sitemap/'.$store_id.'/categories/';
$dst='../sitemap/'.$store_id.'/categories/backup/';
$xsl='../sitemap/'.$store_id.'/categories/xsl/';
$this->make_dir($src);
$this->make_dir($dst);
$this->removeFiles($dst.'xsl/*');
$this->removeFiles($dst.'*');
$this->recurse_copy($src,$dst);
$this->make_dir($xsl);

if (is_dir($dst.'backup/xsl/')){
rmdir($dst.'backup/xsl/');
}
if (is_dir($dst.'backup/')){
rmdir($dst.'backup/');
}
$this->removeFiles($src.'*');

$indlim='';
$priority='';
$changefreq='';
$statusd='';
$prodstyle='';
$query=$this->db->query("SELECT indlim,prio,changefreq,prodstyle,status FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='categories'");
$row=$query->rows;
foreach($row as $row){
$indlim .=$row['indlim'];
$priority .=$row['prio'];
$statusd .=$row['status'];
$prodstyle .=$row['prodstyle'];
$changefreq .=$row['changefreq'];
}
$sitemap_index_file=$src.'sitemap-index.xml';
$sitemap_index_handle=fopen($sitemap_index_file, 'w') or die('Cannot open file:  '.$sitemap_index_file);
$sitemap_index='<?xml version="1.0" encoding="UTF-8"?>';
if($prodstyle == '1'){
$sitemap_index .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/categories/xsl/sitemap-index.xsl"?>';
}
$sitemap_index .='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

$status=($statusd==0)? 'WHERE status=1' : '';

$query=$this->db->query("SELECT * FROM ".DB_PREFIX."category_to_store WHERE store_id='$store_id'");
$productCount=$query->num_rows;
$pages=ceil($productCount/$indlim);
for($i=1;$i<=$pages;$i++){
$lastid='';
$query=$this->db->query("SELECT lastid FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='categories'");
$last_id=$query->rows;
foreach($last_id as $last_id){
$lastid .=$last_id['lastid'];
}
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."category_to_store WHERE category_id > '$lastid' AND store_id='$store_id' LIMIT $indlim");
$catrow=$query->rows;
$file='../sitemap/'.$store_id.'/categories/sitemap_'.$i.'.xml';
$handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
$data='<?xml version="1.0" encoding="UTF-8"?>';
if($prodstyle == '1'){
$data .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/categories/xsl/sitemap.xsl"?>';
}
$data .='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

$status=($statusd==0)? ' status=1 AND ' : '';
foreach($catrow as $catrow){
$category_id=$catrow['category_id'];
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."category WHERE".$status." category_id ='$category_id'");
$row=$query->rows;
foreach($row as $row){
$category_id=$row['category_id'];
$caturl='category_id='.$category_id;
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."url_alias WHERE query='$caturl'");
$url_alias=$query->rows;

foreach($url_alias as $alias){
$data .='<url><loc>'.$storeurl.''.$alias['keyword'].'</loc>';
}

if($row['image'] != ''){
$data .='<image:image><image:loc>'.$storeurl.'image/'.$row['image'].'</image:loc>';
$query=$this->db->query("SELECT meta_title FROM ".DB_PREFIX."category_description WHERE category_id='$category_id'");
$product_title=$query->rows;
foreach($product_title as $title){
$data .='<image:title>'.$title['meta_title'].'</image:title>';
}
$data .='</image:image>';
}
$data .='<changefreq>'.$changefreq.'</changefreq><lastmod>'.date('Y-m-d', strtotime($row['date_modified'])).'</lastmod><priority>'.$priority.'</priority></url>';

$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='".$category_id."' WHERE `groups`='categories'");
}
}
$data .= '</urlset>';
fwrite($handle, $data);

$sitemap_index .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/categories/sitemap_'.$i.'.xml</loc><lastmod>'.date('Y-m-d').'</lastmod></sitemap>';
}
$sitemap_index .='</sitemapindex>';
fwrite($sitemap_index_handle, $sitemap_index);

$this->createIndexStyle('categories index',$store_id,'categories');
$this->createStyle('categories sitemap',$store_id,'categories');

$last_update=date('Y-m-d H:i:s');
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='0', last_update='$last_update' WHERE `groups`='categories'");
}
$this->generateIndex();
//**categories
}

public function generateBrands(){
//brands
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$query=$this->db->query("SELECT value FROM ".DB_PREFIX."setting WHERE `store_id`='$store_id' AND `key`='config_url'");
$config_url=$query->rows;
$store_url='';
foreach($config_url as $config_url){
$store_url .=$config_url['value'];
}
$storeurl=($store_url == '')? HTTP_CATALOG : $store_url;
$src='../sitemap/'.$store_id.'/brands/';
$dst='../sitemap/'.$store_id.'/brands/backup/';
$xsl='../sitemap/'.$store_id.'/brands/xsl/';
$this->make_dir($src);
$this->make_dir($dst);
$this->removeFiles('../sitemap/'.$store_id.'/brands/backup/xsl/*');
$this->removeFiles('../sitemap/'.$store_id.'/brands/backup/*');
$this->recurse_copy($src,$dst);
$this->make_dir($xsl);

$this->removeFiles('../sitemap/'.$store_id.'/brands/*');

$indlim='';
$prodstyle='';
$query=$this->db->query("SELECT indlim,prodstyle FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='brands'");
$row=$query->rows;
foreach($row as $row){
$indlim .=$row['indlim'];
$prodstyle .=$row['prodstyle'];
}

$sitemap_index_file='../sitemap/'.$store_id.'/brands/sitemap-index.xml';
$sitemap_index_handle=fopen($sitemap_index_file, 'w') or die('Cannot open file:  '.$sitemap_index_file);
$sitemap_index='<?xml version="1.0" encoding="UTF-8"?>';

if($prodstyle==1){
$sitemap_index .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/brands/xsl/sitemap-index.xsl"?>';
}
$sitemap_index .='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."manufacturer_to_store WHERE store_id='$store_id'");

$productCount=$query->num_rows;
$pages=ceil($productCount/$indlim);
for($i=1;$i<=$pages;$i++){
$priority='';
$lastid='';
$changefreq='';
$query=$this->db->query("SELECT prio,lastid,changefreq FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='brands'");
$last_id=$query->rows;
foreach($last_id as $last_id){
$priority .=$last_id['prio'];
$lastid .=$last_id['lastid'];
$changefreq .=$last_id['changefreq'];
}
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."manufacturer_to_store WHERE manufacturer_id > '$lastid' AND store_id='$store_id' LIMIT $indlim");
$brandrow=$query->rows;

$file='../sitemap/'.$store_id.'/brands/sitemap_'.$i.'.xml';
$handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
$data='<?xml version="1.0" encoding="UTF-8"?>';

if($prodstyle==1){
$data .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/brands/xsl/sitemap.xsl"?>';
}
$data .='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';


foreach($brandrow as $brandrow){
$manufacturer_id=$brandrow['manufacturer_id'];
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."manufacturer WHERE manufacturer_id ='$manufacturer_id'");
$row=$query->rows;
foreach($row as $row){
$manufacturer_id=$row['manufacturer_id'];
$brandurl='manufacturer_id='.$manufacturer_id;
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."url_alias WHERE query='$brandurl'");
$url_alias=$query->rows;

foreach($url_alias as $alias){
$data .='<url><loc>'.$storeurl.''.$alias['keyword'].'</loc>';
}

if($row['image'] != ''){
$data .='<image:image><image:loc>'.$storeurl.'image/'.$row['image'].'</image:loc><image:title>'.$row['name'].'</image:title></image:image>';
}
$data .='<changefreq>'.$changefreq.'</changefreq><priority>'.$priority.'</priority></url>';

$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='".$manufacturer_id."' WHERE `groups`='brands'");
}
}
$data .= '</urlset>';
fwrite($handle, $data);

$sitemap_index .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/brands/sitemap_'.$i.'.xml</loc><lastmod>'.date('Y-m-d').'</lastmod></sitemap>';
print '<a href="'.$storeurl.'sitemap/'.$store_id.'/brands/sitemap_'.$i.'.xml" target="_blank">brands sitemap_'.$i.' updated</a><br>';
}
$sitemap_index .='</sitemapindex>';
fwrite($sitemap_index_handle, $sitemap_index);

$this->createIndexStyle('brands index',$store_id,'brands');
$this->createStyle('brands sitemap',$store_id,'brands');

$last_update=date('Y-m-d H:i:s');
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='0', last_update='$last_update' WHERE `groups`='brands'");
}
$this->generateIndex();
//**brands
}

public function generateInformation(){
//information
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$query=$this->db->query("SELECT value FROM ".DB_PREFIX."setting WHERE `store_id`='$store_id' AND `key`='config_url'");
$config_url=$query->rows;
$store_url='';
foreach($config_url as $config_url){
$store_url .=$config_url['value'];
}
$storeurl=($store_url == '')? HTTP_CATALOG : $store_url;
$src='../sitemap/'.$store_id.'/information/';
$dst='../sitemap/'.$store_id.'/information/backup/';
$xsl='../sitemap/'.$store_id.'/information/xsl/';
$this->make_dir($src);
$this->make_dir($dst);
$this->removeFiles('../sitemap/'.$store_id.'/information/backup/xsl/*');
$this->removeFiles('../sitemap/'.$store_id.'/information/backup/*');
$this->recurse_copy($src,$dst);
$this->make_dir($xsl);

$this->removeFiles('../sitemap/'.$store_id.'/information/*');

$priority='';
$indlim='';
$prodstyle='';
$statusd='';
$changefreq='';
$query=$this->db->query("SELECT indlim,status,prio,changefreq,prodstyle FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='information'");
$row=$query->rows;
foreach($row as $row){
$priority .=$row['prio'];
$indlim .=$row['indlim'];
$statusd .=$row['status'];
$changefreq .=$row['changefreq'];
$prodstyle .=$row['prodstyle'];
}
$sitemap_index_file='../sitemap/'.$store_id.'/information/sitemap-index.xml';
$sitemap_index_handle=fopen($sitemap_index_file, 'w') or die('Cannot open file:  '.$sitemap_index_file);
$sitemap_index='<?xml version="1.0" encoding="UTF-8"?>';
if($prodstyle==1){
$sitemap_index .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/information/xsl/sitemap-index.xsl"?>';
}
$sitemap_index .='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."information_to_store WHERE store_id='$store_id'");
$productCount=$query->num_rows;
$pages=ceil($productCount/$indlim);
for($i=1;$i<=$pages;$i++){
$lastid='';
$query=$this->db->query("SELECT lastid FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='information'");
$last_id=$query->rows;
foreach($last_id as $last_id){
$lastid .=$last_id['lastid'];
}
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."information_to_store WHERE information_id > '$lastid' AND store_id='$store_id' ORDER BY information_id ASC LIMIT $indlim");
$inforow=$query->rows;
$file='../sitemap/'.$store_id.'/information/sitemap_'.$i.'.xml';
$handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
$data='<?xml version="1.0" encoding="UTF-8"?>';
if($prodstyle==1){
$data .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/information/xsl/sitemap.xsl"?>';
}
$data .='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
$status=($statusd==0)? ' status=1 AND ' : '';
foreach($inforow as $inforow){
$information_id=$inforow['information_id'];
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."information WHERE".$status." information_id ='$information_id'");
$statusrow=$query->rows;
foreach($statusrow as $statusrow){
$information_id=$statusrow['information_id'];
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."information_description WHERE information_id ='$information_id'");
$row=$query->rows;
foreach($row as $row){
$information_id=$row['information_id'];
$infourl='information_id='.$information_id;
$query=$this->db->query("SELECT * FROM ".DB_PREFIX."url_alias WHERE query='$infourl'");
$url_alias=$query->rows;

foreach($url_alias as $alias){
$data .='<url><loc>'.$storeurl.$alias['keyword'].'</loc>';
}
$data .='<changefreq>'.$changefreq.'</changefreq><priority>'.$priority.'</priority></url>';

$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='".$information_id."' WHERE `groups`='information'");
}
}
}
$data .= '</urlset>';
fwrite($handle, $data);

$sitemap_index .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/information/sitemap_'.$i.'.xml</loc><lastmod>'.date('Y-m-d').'</lastmod></sitemap>';
}
$sitemap_index .='</sitemapindex>';
fwrite($sitemap_index_handle, $sitemap_index);

$this->createIndexStyle('information index',$store_id,'information');
$this->createStyle('information sitemap',$store_id,'information');

$last_update=date('Y-m-d H:i:s');
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET `lastid`='0', last_update='$last_update' WHERE `groups`='information'");
}
$this->generateIndex();
//**information
}

public function generatePages(){
//pages
$stores=$this->getStoreId();
foreach($stores as $store){
$store_id=$store['store_id'];
$query=$this->db->query("SELECT value FROM ".DB_PREFIX."setting WHERE `store_id`='$store_id' AND `key`='config_url'");
$config_url=$query->rows;
$store_url='';
foreach($config_url as $config_url){
$store_url .=$config_url['value'];
}
$storeurl=($store_url == '')? HTTP_CATALOG : $store_url;
$src='../sitemap/'.$store_id.'/pages/';
$dst='../sitemap/'.$store_id.'/pages/backup/';
$xsl='../sitemap/'.$store_id.'/pages/xsl/';
$this->make_dir($src);
$this->make_dir($dst);
$this->removeFiles('../sitemap/'.$store_id.'/pages/backup/xsl/*');
$this->removeFiles('../sitemap/'.$store_id.'/pages/backup/*');
$this->recurse_copy($src,$dst);
$this->make_dir($xsl);
$this->removeFiles('../sitemap/'.$store_id.'/pages/*');

$priority='';
$changefreq='';
$prodstyle='';
$query=$this->db->query("SELECT prio,changefreq,prodstyle FROM ".DB_PREFIX."gcrdev_sitemap WHERE groups='pages'");
$last_id=$query->rows;
foreach($last_id as $last_id){
$priority .=$last_id['prio'];
$changefreq .=$last_id['changefreq'];
$prodstyle .=$last_id['prodstyle'];
}
$file='../sitemap/'.$store_id.'/pages/sitemap.xml';
$handle=fopen($file, 'w') or die('Cannot open file:  '.$file);
$data='<?xml version="1.0" encoding="UTF-8"?>';

if($prodstyle==1){
$data .='<?xml-stylesheet type="text/xsl" href="'.$storeurl.'sitemap/'.$store_id.'/pages/xsl/sitemap.xsl"?>';
}
$data .='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<url><loc>'.rtrim($storeurl,'/').'</loc>
<changefreq>'.$changefreq.'</changefreq>
<priority>'.$priority.'</priority></url>
<url><loc>'.$storeurl.'index.php?route=product/manufacturer</loc>
<changefreq>'.$changefreq.'</changefreq>
<priority>'.$priority.'</priority></url>
<url><loc>'.$storeurl.'index.php?route=account/voucher</loc>
<changefreq>'.$changefreq.'</changefreq>
<priority>'.$priority.'</priority></url>
<url><loc>'.$storeurl.'index.php?route=affiliate/login</loc>
<changefreq>'.$changefreq.'</changefreq>
<priority>'.$priority.'</priority></url>
<url><loc>'.$storeurl.'index.php?route=product/special</loc>
<changefreq>'.$changefreq.'</changefreq>
<priority>'.$priority.'</priority></url>
<url><loc>'.$storeurl.'index.php?route=account/return/add</loc>
<changefreq>'.$changefreq.'</changefreq>
<priority>'.$priority.'</priority></url>
</urlset>';

fwrite($handle, $data);
$pages_index .='<sitemap><loc>'.$storeurl.'sitemap/'.$store_id.'/pages/sitemap_'.$i.'.xml</loc><lastmod>'.date('Y-m-d').'</lastmod></sitemap>';
$this->createIndexStyle('pages index',$store_id,'pages');
$this->createStyle('pages sitemap',$store_id,'pages');
}


$last_update=date('Y-m-d H:i:s');
$this->db->query("UPDATE ".DB_PREFIX."gcrdev_sitemap SET last_update='$last_update' WHERE `groups`='pages'");
$this->generateIndex();
//**pages
}

}