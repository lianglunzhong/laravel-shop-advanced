<?php

use Illuminate\Database\Seeder;
use App\Models\UserAddress;
use App\Models\User;

class UserAddressesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 所有用户 ID， 如：[1,2,3,4]
        $user_ids = User::all()->pluck('id')->toArray();

        // 获取 Faker 实例
        $faker = app(Faker\Generator::class);

        $addresses = factory(UserAddress::class)
                        ->times(50)
                        ->make()
                        ->each(function ($address, $index)
                            use ($user_ids, $faker)
        {
            // 从用户 ID 数组中随机取出一个并赋值
            $address->user_id = $faker->randomElement($user_ids);
        });

        UserAddress::insert($addresses->toArray());
    }
}
