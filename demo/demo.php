<?php
require(__DIR__ ."/../src/swagger2md.php");

// 实际参数根据自己情况来配
$cfg    = ['file_path' => __DIR__ .'/swagger.json', 'request_host' => 'http://godfrey.cloud.com', 'md_dir_path' => 'md/docs', 'md_tpl_path' => __DIR__ .'/tpl.md', 'is_create_menu' => TRUE, 'menu_file_name' => 'md/SUMMARY.md'];
$s2mObj = new Swagger2Md($cfg);
$s2mObj->transformation();
