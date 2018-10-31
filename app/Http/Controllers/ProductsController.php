<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderItem;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $page    = $request->input('page', 1);
        $perPage = 16;

        // 构建查询
        $params = [
            'index' => 'products',
            'type'  => '_doc',
            'body'  => [
                'from'  => ($page - 1) * $perPage, // 通过当前页数与每页数量计算偏移值
                'size'  => $perPage,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['on_sale' => true]],
                        ],
                    ],
                ],
            ],
        ];

        // 是否有提交 order 参数，如果有就赋值给 $order 变量
        // order 参数用来控制商品的排序规则
        if ($order = $request->input('order', '')) {
            // 是否是以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 根据传入的排序值来构造排序参数
                    $params['body']['sort'] = [[$m[1] => $m[2]]];
                }
            }
        }

        // 类目筛选
        if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
            if ($category->is_directory) {
                // 如果是一个福类目，则使用 category_path 来筛选
                $params['body']['query']['bool']['filter'][] = [
                    // prefix 前缀查询，与 Mysql 的 like '${path}%' 是等价的
                    'prefix' => ['category_path' => $category->path.$category->id.'-'],
                ];
            } else {
                // 直接通过 category_id 筛选
                $params['body']['query']['bool']['filter'][] = [
                    'term' => ['category_id' => $category->id],
                ];
            }
        }

        // 关键字筛选
        if ($search = $request->input('search', '')) {
            // 将搜索词根据空格拆分成数组，并过滤掉空项
            $keywords = array_filter(explode(' ', $search));

            $params['body']['query']['bool']['must'] = [];
            // 遍历搜索词数组，分别添加到 must 查询中
            foreach ($keywords as $keyword) {
                $params['body']['query']['bool']['must'][] = [
                    'multi_match' => [
                        'query' => $keyword,
                        'fields' => [
                            'title^3',
                            'long_title^2',
                            'category^2', // 类目名称
                            'description',
                            'skus_title',
                            'skus_description',
                            'properties_value',
                        ],
                    ],
                ];
            }
        }

        // 只有当用户有输入搜索词或者使用了类目筛选的时候才会做聚合
        if ($search || isset($category)) {
            $params['body']['aggs'] = [
                // 这里的 properties 是我们给这个聚合操作的命名
                // 可以是其他字符串，与商品结构里的 properties 没有必然联系
                'properties' => [
                    // 由于我们要聚合的属性是在 nested 类型字段下的属性，需要在外面套一层 nested 聚合查询
                    'nested' => [
                        // 代表我们要查询的 nested 字段名为 properties
                        'path' => 'properties',
                    ],
                    // 在 nested 聚合下嵌套聚合
                    'aggs' => [
                        // 聚合的名称
                        'properties' => [
                            // terms 聚合，用于聚合相同的值
                            'terms' => [
                                // 我们要聚合的字段名
                                'field' => 'properties.name',
                            ],
                            // 第三层聚合
                            'aggs' => [
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
        }

        // 从用户请求参数获取 filters
        $propertyFilters = [];
        if ($filterString = $request->input('filters')) {
            // 将获取到的字符串用符号 | 拆分成数组
            $filterArray = explode('|', $filterString);
            foreach ($filterArray as $filter) {
                // 将字符串用符号 : 拆分成两部分并且分别赋值给 $name 和 $value 两个变量
                list($name, $value) = explode(':', $filter);
                // 将用户筛选的属性添加到数组中
                $propertyFilters[$name] = $value;

                // 添加到 filter 类型中
                $params['body']['query']['bool']['filter'][] = [
                    // 由于我们要筛选的是 nested 类型下的属性，因此需要用 nested 查询
                    'nested' => [
                        // 只能 nested 字段
                        'path' => 'properties',
                        'query' => [
                            ['term' => ['properties.search_value' => $filter]],
                        ],
                    ],
                ];
            }
        }

        $result = app('es')->search($params);

        // 通过 collect 函数将返回结果转为集合，并通过集合的 pluck 方法取到返回的商品 ID 数组
        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();
        // 通过 whereIn 方法从数据库中读取商品数据
        $products = Product::query()
            // $productIds 是按查询条件查询到的正确的排序的结果，但是用 whereIn 时，不会按照 ID 的顺序去查询
            ->whereIn('id', $productIds)
            // orderByRaw 可以让我们用原生的 SQL 来给查询结果排序
            ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $productIds)))
            ->get();
        // 返回一个 LengthAwarePaginator 对象
        $pager = new LengthAwarePaginator($products, $result['hits']['total'], $perPage, $page, [
            'path' => route('products.index', false), // 手动构建分页的 url
        ]);

        $properties = [];
        // 如果返回的结果有 aggregations 字段，说明做了分面搜索
        if (isset($result['aggregations'])) {
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])
                ->map(function ($bucket) {
                    // 通过 map 方法取出我们需要的字段
                    return [
                        'key' => $bucket['key'],
                        'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                    ];
                })->filter(function ($property) use ($propertyFilters) {
                    // 过滤掉只剩下一个值 或者 已经在筛选条件里的属性
                    return count($property['values']) > 1 && !isset($propertyFilters[$property['key']]);
                });
        }

        return view('products.index', [
            'products' => $pager,
            'filters'  => [
                'search' => '',
                'order'  => $order,
            ],
            'category' => $category ?? null,
            'properties' => $properties,
            'propertyFilters' => $propertyFilters,
        ]);
    }

    public function show(Product $product, Request $request)
    {
    	// 判断商品是否已经上架，没有上架则抛出异常
    	if ( ! $product->on_sale) {
    		throw new InvalidRequestException('商品未上架');
    	}

        $favored = false;

        if ($user = $request->user()) {
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }

        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku'])
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at', 'desc')
            ->limit(10)
            ->get();

    	return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews,
        ]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);

        return [];
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', ['products' => $products]);
    }
}
