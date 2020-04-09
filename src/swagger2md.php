<?php
/**
 * Class Swagger2Md
 * @author          godfrey.gan <g854787652@gmail.com>
 * @description     将swagger-json转成markdown文档
 */
/*
示例1：一键转化全部
$cfg    = ['file_path' => '../order-service_order.json', 'request_host' => 'http://order.ganqixin.dev.tgs.com', 'md_dir_path' => 'docs', 'md_tpl_path' => '../tpl.md', 'is_create_menu' => TRUE, 'menu_file_name' => 'SUMMARY.md'];
$s2mObj = new Swagger2Md($cfg);
$s2mObj->transformation();

示例2：针对某一个operationId进行转换
$cfg    = ['file_path' => '../order-service_order.json', 'request_host' => 'http://order.ganqixin.dev.tgs.com', 'md_dir_path' => 'docs', 'md_tpl_path' => '../tpl.md', 'is_create_menu' => TRUE, 'menu_file_name' => 'SUMMARY.md'];
$s2mObj = new Swagger2Md($cfg);
$s2mObj->getInterfaceInfo('v1_activity_lottery_getUserLotteryDrawNum');
$s2mObj->saveMdFile('v1_activity_lottery_getUserLotteryDrawNum');
 */

class Swagger2Md
{

    protected $base_path      = __DIR__;              // 当前文件路径
    protected $file_path      = '';                   // swagger-json文件路径
    protected $request_host   = '';                   // 接口请求的地址，如：http://order.ganqixin.dev.tgs.com/rpc.php
    protected $md_dir_path    = 'docs';               // 用于保存md文件的目录地址
    protected $md_tpl_path    = 'tpl.md';             // md样本文件的地址
    protected $swagger_data   = [];                   // swagger-json的数组
    protected $is_create_menu = FALSE;                // 是否创建md目录：TRUE-创建，FALSE-不创建
    protected $menu_list      = [];                   // md目录列表数据
    protected $menu_file_name = 'SUMMARY.md';         // 目录文件名
    protected $interface_info = [];                   // 接口数据

    /**
     * Swagger2Md constructor.
     * @param $props array
     * @remark
     * 用于初始化一系列参数
     * file_path、request_host、md_dir_path、md_tpl_path、is_create_menu、menu_file_name
     */
    public function __construct($props = [])
    {
        if (count($props) > 0) {
            $this->initialize($props);
        }

        $this->loadJsonFile();      // 加载json文件，并转成数组
    }

    /**
     * @title : 初始化类的变量
     * @param array $props
     * @return bool
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function initialize($props = array())
    {
        if (count($props) > 0) {
            foreach ($props as $key => $val) {
                $this->$key = $val;
            }
//            if($this->isEmpty($props['md_tpl_path'])) $this->md_tpl_path = $this->base_path .'/'. $this->md_tpl_path;
        }

        return TRUE;
    }

    /**
     * @title : 加载json内容的文件
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function loadJsonFile()
    {
        if (!empty($this->file_path)) {
            if (!file_exists($this->file_path)) {
                self::output(1000, '无效的文件路径：', ['file_path' => $this->file_path]);
                exit();
            } else {
                $jsonContent        = file_get_contents($this->file_path);
                $this->swagger_data = json_decode($jsonContent, TRUE);
            }
        } else {
            self::output(1001, '文件路径不能为空', ['file_path' => __DIR__ .'/'. $this->file_path]);
            exit();
        }
    }

    /**
     * @title : 获取tags的映射关系
     * @return array        [name=>[name => '',description => '']]
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function getTagsMap()
    {
        $data = [];
        if ($this->isEmpty($this->swagger_data) || $this->isEmpty($this->swagger_data, 'tags')) {
            return $data;
        }

        foreach ($this->swagger_data['tags'] as $tag) {
            if (!empty($tag['name'])) {
                $data[$tag['name']] = $tag;
            }
        }
        return $data;
    }

    /**
     * @title : 通过name获取tag信息
     * @param $name     string  tag的name（一般是英文）
     * @return array            [name => '',description => '']
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function getTagMapByName($name)
    {
        if (empty($name)) {
            return [];
        }

        $tagsMap = self::getTagsMap();
        return $this->isEmpty($tagsMap, $name) ? [] : $tagsMap[$name];
    }

    /**
     * @title : 获取schemas名称
     * @param $data
     * @return mixed|string
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function getSchemasName($data)
    {
        $schemasName = '';
        if (!$this->isEmpty($data, '$ref')) {
            $schemasTmp  = explode('/', $data['$ref']);
            $schemasName = $schemasTmp[count($schemasTmp) - 1];
        }
        return $schemasName;
    }

    /**
     * @title : 通过schema的名称key获取schema数据
     * @param $schemaName
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     * @remark
     *  例如：components:{schemas:{config_express_getlistresponse:{user_id: xxx},config_express_inforequest:{ono: xxx}}}
     *  $schemaName 为 config_express_getlistresponse 或 config_express_inforequest
     */
    public function getPropertiesByKey($schemaName)
    {
        $Schemas = [];
        if ($this->isEmpty($this->swagger_data, 'components') || $this->isEmpty($this->swagger_data['components'], 'schemas') || $this->isEmpty($schemaName)) {
            return $Schemas;
        }

        foreach ($this->swagger_data['components']['schemas'] as $k1 => $v1) {
            if (!empty($schemaName) && $k1 <> $schemaName) continue;
            $Schemas = $v1;
        }
        return $Schemas;
    }

    /**
     * @title : 将schema信息转成上下级字段形式
     * @param $properties
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     * @remark
     * 例如：components:{schemas:{config_express_getlistresponse:{user_id: xxx},config_express_inforequest:{ono: xxx}}}
     * $properties 为 config_express_getlistresponse 里面的信息
     */
    public function formatSchemasData($properties)
    {
        if ($this->isEmpty($properties)) {
            return [];
        }
        $data     = [];
        $type     = $this->isEmpty($properties, 'type') ? 'string' : $properties['type'];
        $required = isset($properties['required']) ? $properties['required'] : [];
        if (!$this->isEmpty($properties, '$ref')) {
            $schemasName = $this->getSchemasName($properties);
            if (!$this->isEmpty($schemasName)) {
                $childProperties  = $this->getPropertiesByKey($schemasName);
                $childSchemasData = $this->formatSchemasData($childProperties);
                if (!empty($childSchemasData)) {
                    $data = $childSchemasData;
                }
            }
        } else {
            if (!$this->isEmpty($properties, 'properties')) {
                foreach ($properties['properties'] as $k1 => $v1) {
                    if (!$this->isEmpty($v1, 'type') && in_array($v1['type'], ['object', 'array'])) {
                        if (!$this->isEmpty($v1, 'items')) {
                            $data[$k1] = $this->formatField($v1, $k1, $required);
                            if ($this->isEmpty($v1['items'], 'type') || in_array($v1['items']['type'], ['array', 'object'])) {
                                if (!$this->isEmpty($v1['items'], 'items')) {
                                    $childSchemasData = $this->formatSchemasData($v1['items']['items']);
                                    if (!empty($childSchemasData)) {
                                        $data[$k1]['field_items']['field_items'] = $childSchemasData;
                                    }
                                } else {
                                    $childSchemasData = $this->formatSchemasData($v1['items']);
                                    if (!empty($childSchemasData)) {
                                        $data[$k1]['field_items'] = $childSchemasData;
                                    }
                                }
                            }
                        } else if (! $this->isEmpty($v1, 'properties')) {
                            $data[$k1] = $this->formatField($v1, $k1, $required);
                            $childSchemasData = $this->formatSchemasData($v1);
                            if (!empty($childSchemasData)) {
                                $data[$k1]['field_items'] = $childSchemasData;
                            }
                        } else {
                            $data[$k1] = $this->formatField($v1, $k1, $required);
                            if (!$this->isEmpty($v1, '$ref')) {
                                $childSchemasData = $this->formatSchemasData($v1);
                                if (!empty($childSchemasData)) {
                                    $data[$k1]['field_items'] = $childSchemasData;
                                }
                            }
                        }
                    } else {
                        $data[$k1] = $this->formatField($v1, $k1, $required);
                        if (!$this->isEmpty($v1, '$ref')) {
                            $childSchemasData = $this->formatSchemasData($v1);
                            if (!empty($childSchemasData)) {
                                $data[$k1]['field_items'] = $childSchemasData;
                            }
                        }
                    }
                }
            } else if(! $this->isEmpty($properties, 'allOf')) {
                foreach($properties['allOf'] as $k1 => $v1){
                    $schemasName = $this->getSchemasName($v1);
                    if (!$this->isEmpty($schemasName)) {
                        $childProperties  = $this->getPropertiesByKey($schemasName);
                        $childSchemasData = $this->formatSchemasData($childProperties);
                        if (!empty($childSchemasData)) {
                            $data = $childSchemasData;
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @title  : 格式化字段
     * @param array  $propertie
     * @param string $fieldName
     * @param array  $required
     * @return array
     * @author : godfrey.gan <g854787652@gmail.com>
     * @remark 出参说明：
     * filed_name   字段名
     * description  名称&描述
     * field_type   数据类型
     * default      默认值
     * example      示例
     * required     是否必填：0-非必填，1-必填
     */
    public function formatField($propertie, $fieldName, $required = [])
    {
        $fieldType   = empty($propertie['type']) ? 'string' : $propertie['type'];
        $default     = empty($propertie['default']) ? '' : (is_array($propertie['default']) ? json_encode($propertie['default']) : $propertie['default']);
        $default     = $fieldType == 'integer' ? (int)$default : $default;
        $example     = empty($propertie['example']) ? '' : (is_array($propertie['example']) ? json_encode($propertie['example']) : $propertie['example']);
        $example     = $fieldType == 'integer' ? (int)$example : $example;
        $description = empty($propertie['description']) ? '' : str_replace(array("/r/n", "/r", "/n", "\r\n", "\r", "\n"), "", $propertie['description']);
        return [
            'filed_name'  => $fieldName,                                                                        // 字段名
            'description' => $description,                                                                      // 名称
            'field_type'  => $fieldType,                                                                        // 数据类型
            'default'     => $default,                                                                          // 默认值
            'example'     => $example,                                                                          // 示例
            'required'    => empty($required) ? 0 : (in_array($fieldName, $required) ? 1 : 0),                  // 是否必填
        ];
    }

    /**
     * @title : 判断是否不存在且为空
     * @param $data
     * @param $key
     * @return bool     true-空，false-非空
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function isEmpty($data, $key = NULL)
    {
        if (empty($key)) {
            return !isset($data) || empty($data);
        }
        return !isset($data[$key]) || empty($data[$key]);
    }

    /**
     * @title : 获取数组元素
     * @param string $v1
     * @param string $k1
     * @param string $k2
     * @param string $default
     * @return mixed|string
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function arrayGet($data, $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return $data['field_type'] == ['integer'] ? (int)$data[$key] : $data[$key];
            }
        }
        return $data['field_type'] == ['integer'] ? (int)$default : $default;
    }

    /**
     * @title : 统一输出
     * @param integer $code
     * @param string  $msg
     * @param string  $data
     * @author: godfrey.gan <g854787652@gmail.com>
     * @remark:
     * 0           正常
     * 1000～2000  文件异常
     * 2000～3000  系统操作异常
     * 3000～4000  其它异常
     */
    private function output($code, $msg = '', $data = '')
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        ];
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @title : 数组合并
     * @param $arr1
     * @param $arr2
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function arrayMerge($arr1, $arr2)
    {
        $data = [];
        if(is_array($arr1)){
            foreach($arr1 as $k1 => $v1){
                if(is_array($v1)){
                    if ($this->isEmpty($arr2, $k1)) {
                        $data[$k1] = $v1;
                    } else {
                        $tmp = $this->arrayMerge($arr1[$k1], $arr2[$k1]);
                        if(! empty($tmp)){
                            $data[$k1] = $tmp;
                        }
                    }
                } else {
                    $data[$k1] = $v1;
                }
            }
        }
        if(is_array($arr2)){
            foreach($arr2 as $k1 => $v1){
                if(is_array($v1)){
                    if($this->isEmpty($arr1, $k1)){
                        $data[$k1] = $v1;
                    } else {
                        $tmp = $this->arrayMerge($arr2[$k1], $arr1[$k1]);
                        if(! empty($tmp)){
                            $data[$k1] = $tmp;
                        }
                    }
                } else {
                    $data[$k1] = $v1;
                }
            }
        }
        return $data;
    }

    /**
     * @title : 针对接口中的schema信息进行转化
     * @param $interfaceSchema
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     * @remark
     * $interfaceSchema 为 [{"$ref": "#components/schemas/apiprotocol"}, {"$ref": "#components/schemas/apiprotocol2"}]
     */
    private function getSchemas4Interface($interfaceSchema)
    {
        $paramsData = [];
        if (!$this->isEmpty($interfaceSchema, 'schema')) {
            $schema = $this->isEmpty($interfaceSchema['schema'], 'allOf') ? $interfaceSchema['schema'] : $interfaceSchema['schema']['allOf'];
            if(! $this->isEmpty($schema, 'properties') && !$this->isEmpty($schema, 'type') && $schema['type'] == 'object'){
                $paramsData = $this->getParamsData($schema);
            } else if($this->isEmpty($schema, '$ref')) {
                foreach ($schema as $v1) {
                    $tmpData = $this->getParamsData($v1);
                    $paramsData = $this->isEmpty($paramsData) ? $tmpData : $this->arrayMerge($tmpData, $paramsData);
                }
            } else {
                $paramsData = $this->getParamsData($schema);
            }
        }
        return $paramsData;
    }

    /**
     * @title : 拼装接口参数
     * @param $properties
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function getParamsData($properties)
    {
        $paramsData = [];
        if(! $this->isEmpty($properties)) {
            $propertiesTmp = $this->formatSchemasData($properties);
            if (!empty($propertiesTmp)) {
                foreach ($propertiesTmp as $k2 => $v2) {
                    if (!empty($v2)) {
                        $tmpData = $this->isEmpty($paramsData, $k2) ? [] : $paramsData[$k2];
                        if (isset($v2['required']) && isset($tmpData['required']) && $v2['required'] <> $tmpData['required']) {
                            $v2['required'] = 1;
                        }
                        if (isset($v2['description']) && isset($tmpData['description']) && empty($v2['description'])) {
                            $v2['description'] = $tmpData['description'];
                        }
                        $paramsData[$k2] = $this->isEmpty($paramsData, $k2) ? $v2 : $this->arrayMerge($v2, $paramsData[$k2]);
                    }
                }
            }
        }
        return $paramsData;
    }

    /**
     * @title : 将参数转换为md-table
     * @param array   $params
     * @param string  $type
     * @param integer $level
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function params2table($params, $type = 'request', $level = 0)
    {
        $level++;
        $tableInfo = [];
        if(! $this->isEmpty($params)) {
            if (!$this->isEmpty($params, 'field_items') && $this->isEmpty($params, 'filed_name')) {
                $tmpData = $this->params2table($params['field_items'], $type, $level);
                if (!empty($tmpData)) {
                    $tableInfo = array_merge($tableInfo, $tmpData);
                }
            } else {
                foreach ($params as $k1 => $v1) {
                    if ($k1 == 'filed_type' && empty($v1)) {
                        continue;
                    }
                    if (!$this->isEmpty($v1, 'filed_name')) {
                        $v1['filed_name'] = str_repeat('↳ ', $level - 1) . $v1['filed_name'];
                        $tableInfo[]      = ($type == 'request') ? $this->setRequestInfo($v1) : $this->setResponseInfo($v1);
                    }
                    if (!$this->isEmpty($v1, 'field_items')) {
                        $tmpData = $this->params2table($v1['field_items'], $type, $level);
                        if (!empty($tmpData)) {
                            $tableInfo = array_merge($tableInfo, $tmpData);
                        }
                    }
                }
            }
        }
        return $tableInfo;
    }

    /**
     * @title : 将参数字段转为数组
     * @param $params
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function params2Array($params)
    {
        $jsonArr = [];
        if (!$this->isEmpty($params)) {
            if (!$this->isEmpty($params, 'field_items') && $this->isEmpty($params, 'filed_name')) {
                $tmpData = $this->params2Array($params['field_items']);
                if (!empty($tmpData)) {
                    if (! $this->isEmpty($params, 'field_type') && $params['field_type'] == 'object') {
                        $jsonArr[] = $tmpData;
                    } else {
                        $jsonArr[] = [$tmpData];
                    }
                }
            } else {
                foreach ($params as $k1 => $v1) {
                    if ($k1 == 'field_type') continue;
                    if (!$this->isEmpty($v1, 'field_items')) {
                        $tmpData = $this->params2Array($v1['field_items']);
                        if (!empty($tmpData)) {
                            if (isset($v1['field_type']) && $v1['field_type'] == 'array') {
                                $jsonArr[$v1['filed_name']][] = $tmpData;
                            } else {
                                $jsonArr[$v1['filed_name']] = $tmpData;
                            }
                        }
                    } else {
                        $jsonArr[$v1['filed_name']] = $this->arrayGet($v1, ['example', 'default']);
                    }
                }
            }
        }
        return $jsonArr;
    }

    /**
     * @title : 设置入参信息
     * @param $v1
     * @return string
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function setRequestInfo($v1)
    {
        $fileName    = isset($v1['filed_name']) ? $v1['filed_name'] : '';
        $fieldType   = isset($v1['field_type']) ? $v1['field_type'] : '';
        $required    = empty($v1['required']) ? '否' : '是';
        $default     = isset($v1['default']) ? $v1['default'] : '';
        $example     = isset($v1['example']) ? $v1['example'] : '';
        $description = isset($v1['description']) ? $v1['description'] : '';
        if (empty($fieldType) && $fileName == 'params') {
            $fieldType = 'array';
        }
        return $fileName . ' | ' . $fieldType . ' | ' . $required . ' | ' . $default . ' | ' . $example . ' | ' . $description;
    }

    /**
     * @title : 设置出参信息
     * @param $v1
     * @return string
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    private function setResponseInfo($v1)
    {
        $fileName    = isset($v1['filed_name']) ? $v1['filed_name'] : '';
        $fieldType   = isset($v1['field_type']) ? $v1['field_type'] : '';
        $required    = empty($v1['required']) ? '否' : '是';
        $default     = isset($v1['default']) ? $v1['default'] : '';
        $example     = isset($v1['example']) ? $v1['example'] : '';
        $description = isset($v1['description']) ? $v1['description'] : '';
        return $fileName . ' | ' . $fieldType . ' | ' . $required . ' | ' . $default . ' | ' . $example . ' | ' . $description;
    }

    /**
     * @title : 获取接口的信息
     * @param  $targetOperationId
     * @return array
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function getInterfaceInfo($targetOperationId = NULL)
    {
        if ($this->isEmpty($this->swagger_data) || $this->isEmpty($this->swagger_data, 'paths')) {
            return [];
        }

        foreach ($this->swagger_data['paths'] as $path => $v1) {
            foreach ($v1 as $method => $v2) {
                if (!$this->isEmpty($targetOperationId) && $v2['operationId'] <> $targetOperationId) continue;
                $tag         = $this->isEmpty($v2, 'tags') ? 'temp' : current($v2['tags']);                   // 只取第一个tag，主要用于分文件夹
                $summary     = $this->isEmpty($v2, 'summary') ? '未定义' : $v2['summary'];                     // 标题
                $operationId = trim($this->isEmpty($v2, 'operationId') ? time() : $v2['operationId']);    // 标识号

                // 取出开发者信息，一般该字段都会含有开发者信息，特殊情况除外。
                $description = $this->isEmpty($v2, 'description') ? '佚名' : htmlspecialchars_decode($v2['description']);
                $description = trim($description);
                $description = trim($description, '开发者');
                $description = trim($description, ':');
                $description = trim($description, '：');
                $description = rtrim($description, '备注');

                // 入参列表
                $requestParams = [];
                if (!$this->isEmpty($v2, 'requestBody') && !$this->isEmpty($v2['requestBody'], 'content')) {
                    foreach ($v2['requestBody']['content'] as $v3) {
                        $tmpData       = $this->getSchemas4Interface($v3);
                        $requestParams = array_merge($requestParams, $tmpData);
                    }
                }

                // 出参列表
                $responseParams = [];
                if (!$this->isEmpty($v2, 'responses')) {
                    foreach ($v2['responses'] as $k3 => $v3) {
                        if (!$this->isEmpty($v3, 'content')) {
                            foreach ($v3['content'] as $v4) {
                                $tmpData        = $this->getSchemas4Interface($v4);
                                $responseParams = array_merge($responseParams, $tmpData);
                            }
                        }
                    }
                }

                // 数据合并到一起
                $this->interface_info[] = [
                    'path'            => $path,
                    'method'          => $method,
                    'tag'             => $tag,
                    'summary'         => $summary,
                    'description'     => $description,
                    'operation_id'    => $operationId,
                    'request_params'  => $requestParams,
                    'response_params' => $responseParams,
                ];
            }
        }

        return $this->interface_info;
    }

    /**
     * @title : 保存至markdown文件
     * @param  $targetOperationId
     * @return bool
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function saveMdFile($targetOperationId = NULL)
    {
        $count = 0;
        $fail  = 0;
        if ($this->isEmpty($this->interface_info)) {
            $this->output(3000, '没有可用数据', $this->interface_info);
        } else {
            foreach ($this->interface_info as $v1) {
                if (!$this->isEmpty($targetOperationId) && $v1['operation_id'] <> $targetOperationId) continue;
                $mdContent = $this->setMdContent($v1);

                $fileName   = mb_strtolower($v1['operation_id']);
                $mdFilePath = $this->md_dir_path . '/' . $v1['tag'] . '/' . $fileName . '.md';
                $mdDirPath  = dirname($mdFilePath);
                if (!is_dir($mdDirPath)) {
                    $res = mkdir(iconv("UTF-8", "GBK", $mdDirPath), 0777, true);
                    if (!$res) {
                        $this->output(1010, '目录创建失败', $mdDirPath);
                        $fail++;
                        continue;
                    }
                }

                $result = file_put_contents($mdFilePath, $mdContent);
                if (!$result) {
                    $this->output(1020, '保存文件失败', $fileName);
                    $fail++;
                    continue;
                }


                $this->menu_list[$v1['tag']][$v1['operation_id']] = [
                    'title'        => $v1['summary'],
                    'operation_id' => $v1['operation_id'],
                    'file_name'    => $fileName,
                    'path'         => $mdFilePath,
                    'tag'          => $v1['tag']
                ];
                $count++;
            }
            if ($fail > 0 && $count > $fail) {
                $this->output(3050, '部分成功，共' . $count . '个接口', ['total' => $count, 'fail' => $fail]);
            } else if ($fail > 0 && $count == $fail) {
                $this->output(3060, '全部失败，共' . $count . '个接口', ['total' => $count, 'fail' => $fail]);
            } else {
                $this->output(0, '全部成功，共' . $count . '个接口', ['total' => $count, 'fail' => $fail]);
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * @title : 设置markdown内容
     * @param $interfaceInfo
     * @return string
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function setMdContent($interfaceInfo)
    {
        if ($this->isEmpty($interfaceInfo)) {
            return '';
        }

        // 将接口入参参数转换为md的table
        $requestInfoText = <<<REQUEST
REQUEST;
        if (!$this->isEmpty($interfaceInfo['request_params'])) {
            $requestInfo = $this->params2table($interfaceInfo['request_params'], 'request');
            foreach ($requestInfo as $v2) {
                $requestInfoText .= $v2 . PHP_EOL;
            }
        }

        // 将接口出参参数转换为md的table
        $responseInfoText = <<<REQUEST
REQUEST;
        if (!$this->isEmpty($interfaceInfo['response_params'])) {
            $responseInfo = $this->params2table($interfaceInfo['response_params'], 'response');
            foreach ($responseInfo as $v2) {
                $responseInfoText .= $v2 . PHP_EOL;
            }
        }

        $requestJsonInfo  = json_encode($this->params2Array($interfaceInfo['request_params']), JSON_PRETTY_PRINT);
        $responseJsonInfo = json_encode($this->params2Array($interfaceInfo['response_params']), JSON_PRETTY_PRINT);

        if(! file_exists($this->md_tpl_path)){
            self::output(1000, '无效的文件路径：', $this->md_tpl_path);
            exit();
        }
        $tpl        = file_get_contents($this->md_tpl_path);
        $tplContent = str_replace(
            ['{{$summary}}', '{{$method}}', '{{$path}}', '{{$description}}', '{{$host}}', '{{$request_info}}', '{{$response_info}}', '{{$request_json}}', '{{$response_json}}'],
            [$interfaceInfo['summary'], $interfaceInfo['method'], $interfaceInfo['path'], $interfaceInfo['description'], $this->request_host, $requestInfoText, $responseInfoText, $requestJsonInfo, $responseJsonInfo],
            $tpl
        );
        return $tplContent;
    }

    /**
     * @title : 保存导航文件
     * @return bool
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function save2Menu()
    {
        if ($this->isEmpty($this->menu_list)) {
            $this->output(3010, '无有效接口信息', []);
        }

        $content = <<<SUMMARY
# Summary

SUMMARY;
        foreach ($this->menu_list as $k1 => $v1) {
            $tagInfo = $this->getTagMapByName($k1);
            if (!$this->isEmpty($tagInfo, 'description')) {
                $content .= '* ' . $tagInfo['description'] . PHP_EOL;
            } else {
                $content .= '* ' . $k1 . PHP_EOL;
            }

            foreach ($v1 as $k2 => $v2) {
                $content .= '  * [' . $v2['title'] . '](' . $v2['path'] . ')' . PHP_EOL;
            }
        }

        $mdFilePath = $this->menu_file_name;
        $result     = file_put_contents($mdFilePath, $content);
        if (!$result) {
            $this->output(1020, '保存导航文件失败', ['file_name' => $mdFilePath]);
        } else {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @title : 一键转换
     * @author: godfrey.gan <g854787652@gmail.com>
     */
    public function transformation()
    {
        $this->getInterfaceInfo();
        $this->saveMdFile();
        if($this->is_create_menu){
            $this->save2Menu();
        }
    }
}
