<?php

namespace App\Console\Commands\Elasticsearch;

use Illuminate\Console\Command;
use App\Models\Product;

class SyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将商品数据同步到 Elasticsearch';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 获取 Elasticsearch 对象
        $es = app('es');
        Product::query()
            ->with(['skus', 'properties'])
            // 使用 chunkById 避免一次性加载过多数据
            ->chunkById(100, function ($products) use ($es) {
                $this->info(sprintf('正在同步 ID 范围为 %s 至 %s 的商品', $products->first()->id, $products->last()->id));

                // 初始化请求体
                $req = ['body' => []];

                foreach ($products as $product) {
                    // 将商品模型转为 Elasticsearch 所用的数组
                    $data = $product->toESArray();
                    $req['body'][] = [
                        'index' => [
                            '_index' => 'products',
                            '_type' => '_doc',
                            '_id' => $data['id'],
                        ],
                    ];
                    $req['body'][] = $data;
                }

                try {
                    // 使用 bluk 方法批量创建, 方法的参数是一个数组，数组的第一行描述了我们要做的操作，第二行则代表这个操作所需要的数据，第三行操作描述，第四行数据，依次类推，当然如果是删除操作则没有数据行
                    $es->bulk($req);
                } catch(\Exception $e) {
                    $this->error($e->getMessage());
                }
            });

        $this->info('同步完成');
    }
}
