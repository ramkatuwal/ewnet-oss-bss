<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\AssetDeviceType;
use App\Models\AssetUnit;
use Illuminate\Database\Seeder;

class AssetModelSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Categories ──
        $network = AssetCategory::updateOrCreate(
            ['code' => 'NETWORK'],
            ['name' => 'Network', 'description' => 'Network devices and equipment', 'is_active' => true, 'sort_order' => 1]
        );
        $power = AssetCategory::updateOrCreate(
            ['code' => 'POWER'],
            ['name' => 'Power', 'description' => 'Power and electrical equipment', 'is_active' => true, 'sort_order' => 2]
        );
        $infra = AssetCategory::updateOrCreate(
            ['code' => 'INFRASTRUCTURE'],
            ['name' => 'Infrastructure', 'description' => 'Physical infrastructure and passive components', 'is_active' => true, 'sort_order' => 3]
        );
        $other = AssetCategory::updateOrCreate(
            ['code' => 'OTHER'],
            ['name' => 'Other', 'description' => 'Uncategorized assets', 'is_active' => true, 'sort_order' => 99]
        );

        // ── Network Device Types ──
        $networkTypes = [
            ['code' => 'ROUTER', 'name' => 'Router', 'sort_order' => 1],
            ['code' => 'SWITCH', 'name' => 'Switch', 'sort_order' => 2],
            ['code' => 'OLT', 'name' => 'OLT', 'sort_order' => 3],
            ['code' => 'ONU', 'name' => 'ONU', 'sort_order' => 4],
            ['code' => 'AP', 'name' => 'Access Point', 'sort_order' => 5],
            ['code' => 'CONTROLLER', 'name' => 'Controller', 'sort_order' => 6],
            ['code' => 'OTHER', 'name' => 'Other', 'sort_order' => 99],
        ];
        foreach ($networkTypes as $t) {
            AssetDeviceType::updateOrCreate(
                ['category_id' => $network->id, 'code' => $t['code']],
                ['name' => $t['name'], 'is_active' => true, 'sort_order' => $t['sort_order']]
            );
        }

        // ── Power Device Types ──
        $powerTypes = [
            ['code' => 'UPS', 'name' => 'UPS', 'sort_order' => 1],
            ['code' => 'BATTERY', 'name' => 'Battery', 'sort_order' => 2],
            ['code' => 'INVERTER', 'name' => 'Inverter', 'sort_order' => 3],
            ['code' => 'SOLAR_PANEL', 'name' => 'Solar Panel', 'sort_order' => 4],
            ['code' => 'PDU', 'name' => 'PDU', 'sort_order' => 5],
            ['code' => 'AC', 'name' => 'AC', 'sort_order' => 6],
            ['code' => 'OTHER', 'name' => 'Other', 'sort_order' => 99],
        ];
        foreach ($powerTypes as $t) {
            AssetDeviceType::updateOrCreate(
                ['category_id' => $power->id, 'code' => $t['code']],
                ['name' => $t['name'], 'is_active' => true, 'sort_order' => $t['sort_order']]
            );
        }

        // ── Infrastructure Device Types ──
        $infraTypes = [
            ['code' => 'RACK', 'name' => 'Rack', 'sort_order' => 1],
            ['code' => 'CABINET', 'name' => 'Cabinet', 'sort_order' => 2],
            ['code' => 'CLOSURE', 'name' => 'Closure', 'sort_order' => 3],
            ['code' => 'FAT', 'name' => 'FAT', 'sort_order' => 4],
            ['code' => 'FDT', 'name' => 'FDT', 'sort_order' => 5],
            ['code' => 'FDH', 'name' => 'FDH', 'sort_order' => 6],
            ['code' => 'ODF', 'name' => 'ODF', 'sort_order' => 7],
            ['code' => 'PATCH_PANEL', 'name' => 'Patch Panel', 'sort_order' => 8],
            ['code' => 'SPLITTER', 'name' => 'Splitter', 'sort_order' => 9],
            ['code' => 'OTHER', 'name' => 'Other', 'sort_order' => 99],
        ];
        foreach ($infraTypes as $t) {
            AssetDeviceType::updateOrCreate(
                ['category_id' => $infra->id, 'code' => $t['code']],
                ['name' => $t['name'], 'is_active' => true, 'sort_order' => $t['sort_order']]
            );
        }

        // ── Other Device Types ──
        AssetDeviceType::updateOrCreate(
            ['category_id' => $other->id, 'code' => 'OTHER'],
            ['name' => 'Other', 'is_active' => true, 'sort_order' => 99]
        );

        // ── Units ──
        $units = [
            ['code' => 'pcs', 'name' => 'Pieces', 'sort_order' => 1],
            ['code' => 'm', 'name' => 'Meters', 'sort_order' => 2],
            ['code' => 'km', 'name' => 'Kilometers', 'sort_order' => 3],
            ['code' => 'pair', 'name' => 'Pair', 'sort_order' => 4],
            ['code' => 'set', 'name' => 'Set', 'sort_order' => 5],
            ['code' => 'roll', 'name' => 'Roll', 'sort_order' => 6],
            ['code' => 'box', 'name' => 'Box', 'sort_order' => 7],
            ['code' => 'unit', 'name' => 'Unit', 'sort_order' => 8],
        ];
        foreach ($units as $u) {
            AssetUnit::updateOrCreate(
                ['code' => $u['code']],
                ['name' => $u['name'], 'is_active' => true, 'sort_order' => $u['sort_order']]
            );
        }
    }
}
