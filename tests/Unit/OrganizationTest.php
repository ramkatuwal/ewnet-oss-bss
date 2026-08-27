<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_has_regions()
    {
        $company = Company::factory()->create();
        $region = Region::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($company->regions->contains($region));
        $this->assertEquals(1, $company->regions->count());
    }

    public function test_region_has_branches()
    {
        $region = Region::factory()->create();
        $branch = Branch::factory()->create(['region_id' => $region->id]);

        $this->assertTrue($region->branches->contains($branch));
        $this->assertEquals(1, $region->branches->count());
    }

    public function test_branch_has_departments()
    {
        $branch = Branch::factory()->create();
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $this->assertTrue($branch->departments->contains($department));
        $this->assertEquals(1, $branch->departments->count());
    }



}
