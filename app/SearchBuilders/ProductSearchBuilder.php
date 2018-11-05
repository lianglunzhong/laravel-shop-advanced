<?php
namespace App\SearchBuilders;

use App\Models\Category;

class ProductSearchBuilder
{
    // 初始化查询
    protected $params = [
        'index' => 'products',
        'type'  => '_doc',
        'body'  => [
            'query' => [
                'bool' => [
                    'filter' => [],
                    'must'   => [],
                ],
            ],
        ],
    ];

    // 添加分页查询
    public function paginate($size, $page)
    {
        $this->params['body']['from'] = ($page - 1) * $size;
        $this->params['body']['size'] = $size;

        return $this;
    }

    // 筛选上架状态的商品
    public function onSale()
    {
        $this->params['body']['query']['bool']['filter'][] = ['term' => ['on_sale' => true]];

        return $this;
    }

    // 按类目筛选商品
    public function category(Category $category)
    {
        if ($category->is_directory) {
            // 如果是一个父类目，则使用 category_path 来筛选
            $this->params['body']['query']['bool']['filter'][] = [
                // prefix 前缀查询，与 Mysql 的 like '${path}%' 是等价的
                'prefix' => ['category_path' => $category->path.$category->id.'-'],
            ];
        } else {
            $this->params['body']['query']['bool']['filter'][] = ['term' => ['category_id' => $category->id]];
        }
    }

    // 添加搜索词
    public function keywords($keywords)
    {
        // 如果参数不是数组则转为数组
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        foreach ($keywords as $keyword) {
            $this->params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query'  => $keyword,
                    'fields' => [
                        'title^3',
                        'long_title^2',
                        'category^2',
                        'description',
                        'skus_title',
                        'skus_description',
                        'properties_value',
                    ],
                ],
            ];
        }

        return $this;
    }

    // 分面搜索的聚合
    public function aggregateProperties()
    {
        $this->params['body']['aggs'] = [
            // 这里的 properties 是我们给这个聚合操作的命名
            // 可以是其他字符串，与商品结构里的 properties 没有必然联系
            'properties' => [
                 // 由于我们要聚合的属性是在 nested 类型字段下的属性，需要在外面套一层 nested 聚合查询
                'nested' => [
                    // 代表我们要查询的 nested 字段名为 properties
                    'path' => 'properties',
                ],
                // 在 nested 聚合下嵌套聚合
                'aggs'   => [
                    // 聚合的名称
                    'properties' => [
                        'terms' => [
                            // 我们要聚合的字段名
                            'field' => 'properties.name',
                        ],
                        // 第三层聚合
                        'aggs'  => [
                            // 聚合的名称
                            'value' => [
                                'terms' => [
                                    'field' => 'properties.value',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $this;
    }

    // 添加一个按商品属性筛选的条件
    public function propertyFilter($name, $value, $type = 'filter')
    {
        $this->params['body']['query']['bool'][$type][] = [
            // 由于我们要筛选的是 nested 类型下的属性，因此需要用 nested 查询
            'nested' => [
                // 只能 nested 字段
                'path'  => 'properties',
                'query' => [
                    ['term' => ['properties.search_value' => $name.':'.$value]],
                ],
            ],
        ];

        return $this;
    }

    // 添加排序
    public function orderBy($field, $direction)
    {
        if (!isset($this->params['body']['sort'])) {
            $this->params['body']['sort'] = [];
        }
        $this->params['body']['sort'][] = [$field => $direction];

        return $this;
    }

    // 返回构造好的查询参数
    public function getParams()
    {
        return $this->params;
    }

    // 设置 minimum_should_match 参数
    public function minShouldMatch($count)
    {
        $this->params['body']['query']['bool']['minimum_should_match'] = (int)$count;

        return $this;
    }
}