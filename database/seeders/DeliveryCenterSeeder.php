<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DeliveryCenter;

class DeliveryCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $deliveryCenters = [
            [
                'name' => 'Mumbai Central',
                'owner_name' => 'Rajesh Kumar',
                'location' => 'Mumbai, Maharashtra',
                'mobile_number' => '9876543210',
                'is_active' => true,
            ],
            [
                'name' => 'Delhi North',
                'owner_name' => 'Amit Sharma',
                'location' => 'Delhi, NCR',
                'mobile_number' => '9876543211',
                'is_active' => true,
            ],
            [
                'name' => 'Bangalore South',
                'owner_name' => 'Priya Patel',
                'location' => 'Bangalore, Karnataka',
                'mobile_number' => '9876543212',
                'is_active' => true,
            ],
            [
                'name' => 'Chennai Central',
                'owner_name' => 'Kumar Raja',
                'location' => 'Chennai, Tamil Nadu',
                'mobile_number' => '9876543213',
                'is_active' => false,
            ],
            [
                'name' => 'Kolkata East',
                'owner_name' => 'Sanjay Roy',
                'location' => 'Kolkata, West Bengal',
                'mobile_number' => '9876543214',
                'is_active' => true,
            ],
            [
                'name' => 'Hyderabad Central',
                'owner_name' => 'Anjali Reddy',
                'location' => 'Hyderabad, Telangana',
                'mobile_number' => '9876543215',
                'is_active' => true,
            ],
            [
                'name' => 'Ahmedabad West',
                'owner_name' => 'Rakesh Patel',
                'location' => 'Ahmedabad, Gujarat',
                'mobile_number' => '9876543216',
                'is_active' => true,
            ],
            [
                'name' => 'Pune Main',
                'owner_name' => 'Suresh Deshmukh',
                'location' => 'Pune, Maharashtra',
                'mobile_number' => '9876543217',
                'is_active' => true,
            ],
            [
                'name' => 'Jaipur City',
                'owner_name' => 'Mahesh Sharma',
                'location' => 'Jaipur, Rajasthan',
                'mobile_number' => '9876543218',
                'is_active' => false,
            ],
            [
                'name' => 'Lucknow Central',
                'owner_name' => 'Anand Kumar',
                'location' => 'Lucknow, Uttar Pradesh',
                'mobile_number' => '9876543219',
                'is_active' => true,
            ],
            [
                'name' => 'Chandigarh Hub',
                'owner_name' => 'Gurpreet Singh',
                'location' => 'Chandigarh, Punjab',
                'mobile_number' => '9876543220',
                'is_active' => true,
            ],
            [
                'name' => 'Bhubaneswar East',
                'owner_name' => 'Ramesh Behera',
                'location' => 'Bhubaneswar, Odisha',
                'mobile_number' => '9876543221',
                'is_active' => true,
            ],
            [
                'name' => 'Kochi Central',
                'owner_name' => 'Thomas Varghese',
                'location' => 'Kochi, Kerala',
                'mobile_number' => '9876543222',
                'is_active' => false,
            ],
            [
                'name' => 'Indore Main',
                'owner_name' => 'Rahul Sharma',
                'location' => 'Indore, Madhya Pradesh',
                'mobile_number' => '9876543223',
                'is_active' => true,
            ],
        ];

        foreach ($deliveryCenters as $center) {
            DeliveryCenter::create($center);
        }
    }
}
