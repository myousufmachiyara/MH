<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\ChartOfAccounts;

class AccountMappingService
{
    // Resolve a role to its account id. Throws if unmapped (the guard).
    public function accountId(string $roleKey): int
    {
        $mapping = AccountMapping::where('role_key', $roleKey)->first();

        if (!$mapping || !$mapping->account_id) {
            throw new \Exception(
                "Account not mapped for role '{$roleKey}'. Set it in Chart of Accounts → Account Mappings."
            );
        }

        return (int) $mapping->account_id;
    }

    // All roles with their current mapping, for the settings screen
    public function all()
    {
        $existing = AccountMapping::pluck('account_id', 'role_key');

        $rows = [];
        foreach (AccountMapping::ROLES as $key => [$label, $hint, $type]) {
            $rows[] = [
                'role_key'   => $key,
                'label'      => $label,
                'hint'       => $hint,
                'type'       => $type,
                'account_id' => $existing[$key] ?? null,
            ];
        }
        return $rows;
    }

    // Save the whole set at once
    public function saveAll(array $mappings): void
    {
        foreach ($mappings as $roleKey => $accountId) {
            if (!array_key_exists($roleKey, AccountMapping::ROLES)) {
                continue; // ignore unknown roles
            }
            AccountMapping::updateOrCreate(
                ['role_key' => $roleKey],
                ['account_id' => $accountId ?: null]
            );
        }
    }
}