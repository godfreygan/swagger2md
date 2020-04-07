## {{$summary}}


### 功能描述
{{$summary}}


### 请求说明
> 请求方式：{{$method}}<br>
请求URL ：{{$path}} <br>
请求格式：application/json <br>
开发者： {{$description}}


### 请求头
无
> 接口规约《公共请求头》


### 请求参数
参数         | 类型      | 必须       | 默认值     | 示例      | 说明
------------|-----------|-----------|-----------|-----------|-----------
{{$request_info}}


### 返回参数

参数         | 类型      | 必须      | 默认值     | 示例       | 说明
------------|-----------|-----------|-----------|-----------|-----------
{{$response_info}}


### 错误状态码
状态码       |说明
------------|-----------
0           |正常
非0         |发生错误


### 请求示例

```shell
curl --location --request POST '{{$host}}{{$path}}' \
-H 'Content-Type: application/json' \
-d '{{$request_json}}'
```

### 返回结果示例

#### 成功
```json
{{$response_json}}
```

#### 失败
```json
{
  "jsonrpc": "2.0",
  "id": "SFGNGW-0343SD-GEQSOS-544",
  "result": {
    "code": 9999999,
    "msg": "操作失败",
    "data": {
    
    },
    "trace_id": "0171057621fcfa6780a119fe062e0000"
  }
}
```