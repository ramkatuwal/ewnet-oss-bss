<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class NodeFactory extends Factory
{
    protected $model = Node::class;

    public function definition()
    {
        $types = ['pop', 'splice', 'cabinet', 'odf', 'distribution', 'aggregation', 'other'];
        $statuses = ['active', 'inactive', 'planned', 'decommissioned'];

        return [
            'code' => $this->faker->unique()->lexify('NODE-????'),
            'name' => $this->faker->company . ' Node',
            'type' => $this->faker->randomElement($types),
            'status' => $this->faker->randomElement($statuses),
            'description' => $this->faker->optional()->sentence,
            'site_id' => Site::factory(),
        ];
    }
}
