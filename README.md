# swagger2md
swagger 转 markdown，就是将swagger生成的json文件解析内容，整合到一个markdown文件里面。保存该文件，并生成一个导航目录文件。

## swagger2markdown

1)  首先需要有一个json内容的文件，只要是json内容即可。（是swagger产生的json文件）

2） 生成json内容的文件，在本地有swagger的话，把 ->toYaml() 换成 ->toJson() ，就可以执行生成json内容的文件

3） 手里有yml文件？自己进官网在线转换：https://swagger.io/tools/swagger-editor/

4） 编写php执行文件（或者直接在 swagger2md.php 中编写）
```php
<?php
$path = __DIR__;
require(__DIR__ ."/swagger2md.php");
// 实际参数根据自己情况来配
$cfg    = ['file_path' => 'swagger.json', 'request_host' => 'http://godfrey.cloud.com', 'md_dir_path' => 'docs', 'md_tpl_path' => 'tpl.md', 'is_create_menu' => TRUE, 'menu_file_name' => 'SUMMARY.md'];
$s2mObj = new Tools\Swagger2Md($cfg);
$s2mObj->transformation();
?>
```

5） 在浏览器执行该文件，浏览器没有报错误，且全部成功。

6） 查看文件夹中是否有生成接口文件和导航文件。文件都齐全？OK，恭喜你，已经操作完成了！！！


**ps：swagger2md是在极短时间内写出来的，可能存在缺陷。如有发现问题，可联系本人（g854787652@gmail.com）修正。 谢谢！！！**