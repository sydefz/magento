<?php
require_once '../magento/app/Mage.php';
umask(0);
Mage::app();
// init api session
$time_start = microtime(true);
require_once 'Zend/XmlRpc/Client.php';
$client = new Zend_XmlRpc_Client('http://magento.local/api/xmlrpc/');
$session = $client->call('login', array('slogsdon', 'apipass'));

// logic
$url = "www.testingsite.com";
$code = explode($url, '.')[1];
$websiteName = $url;
$storeGroupName = $url." Store";
$storeName = $storeGroupName." View";
// create root category
$rootCategoryAttributes = array(
  //"category_id" => "2",
  "is_active"=> "1",
  "position" => "1",
  "level" => "1",
  "parent_id" => null,
  "increment_id" => null,
  //"created_at" => "2012-05-08 14:46:48",
  //"updated_at" => "2012-05-08 14:46:48",
  "name" => $url,
  //"url_key" => "root-category-1",
  "thumbnail" => null,
  "description" => null,
  "image" => null,
  "meta_title" => null,
  "meta_keywords" => null,
  "meta_description" => null,
  "include_in_menu" => "1",
  //"path" => "1/2",
  //"all_children" => "2",
  "path_in_store" => null,
  "children" => "",
  "url_path" => null,
  "children_count" => "0",
  "display_mode" => "PRODUCTS",
  "landing_page" => null,
  "is_anchor" => "1",
  "available_sort_by" => "position,name,price",
  "default_sort_by" => "position",
  "filter_price_range" => null,
  "custom_use_parent_settings" => null,
  "custom_apply_to_products" => null,
  "custom_design" =>  null,
  "custom_design_from" => null,
  "custom_design_to" => null,
  "page_layout" => null,
  "custom_layout_update" => null,
);
echo "Category created with id: ".$rootCategoryID = $client->call(
	'call',
	array(
		$session,
		'category.create',
		array(
			1,
			$rootCategoryAttributes
		)
	)
);
print"<br />";


/* Website information */
$website_data = array(
    'name' => $websiteName,
    'code' => $code,
    'sort_order' => '2',
    'is_active' => 1,
);

/* Save website */
$website = Mage::getModel('core/website');
$website->setData($website_data);
$website->save()->load();

/* Save store */
$storeGroup = Mage::getModel('core/store_group');
$storeGroup->setData(
    array(
        'root_category_id' => $rootCategoryID,
        'website_id' => $website->getId(),
        'name' => $storeGroupName,
    )
);
$storeGroup->save()->load();

$store = Mage::getModel('core/store');
$store->setData(
    array(
        'website_id' => $website->getId(),
        'name' => $storeName,
        'code' => $code."_default",
        'group_id' => $storeGroup->getGroupId(),
        'is_active' => 1,
    )
);
$store->save()->load();

// config settings
mysql_connect('localhost', 'root') or die('mysql couldn\'t connect.');
mysql_select_db('magento') or die('mysql couldn\'t select'.);

$sqls = array(
	"INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('stores', {$store->getid()}, 'web/unsecure/base_url', 'http://{$url}/')",
	"INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('stores', {$store->getid()}, 'web/secure/base_url', 'http://{$url}/')",
	//theme & cutom js/css
	//analytics
);

foreach ($sqls as $sql) {
	if (!mysql_query($sql)) {
		print"\nquery \"{$sql}\" failed.<br />";
	}
}

// close api session
$client->call('endSession', array($session));
$time_end = microtime(true);
$time = $time_end - $time_start;
print"\n<br /><br />took {$time} seconds to execute.";
?>