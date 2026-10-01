<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transaction;
use App\Models\WasteType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EcoBankFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_and_only_see_their_own_deposits(): void
    {
        $user = $this->makeUser('member@example.test');
        $this->makeUser('other@example.test');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'wasteTypeId' => $wasteType->id,
                'weight' => '2.50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.value', 16250)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.month', now()->format('Y-m'));

        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $user->name);
    }

    public function test_only_admin_can_change_waste_prices(): void
    {
        $member = $this->makeUser('member@example.test');
        $this->actingAs($member)
            ->postJson('/api/prices', [
                'name' => 'Kardus',
                'category' => 'Kertas',
                'price' => 3800,
            ])
            ->assertForbidden();

        $admin = $this->makeUser('admin@example.test', 'admin');
        $this->actingAs($admin)
            ->postJson('/api/prices', [
                'name' => 'Kardus',
                'category' => 'Kertas',
                'price' => 3800,
            ])
            ->assertCreated()
            ->assertJsonPath('data.price', 3800);
    }

    public function test_admin_dashboard_summary_uses_approved_transactions_and_current_periods(): void
    {
        $admin = $this->makeUser('dashboard-admin@example.test', 'admin');
        $member = $this->makeUser('dashboard-member@example.test');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        Transaction::create([
            'transaction_code' => 'EB-DASH-APPROVED',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 2.5,
            'total_value' => 16250,
            'status' => 'approved',
        ]);
        Transaction::create([
            'transaction_code' => 'EB-DASH-PENDING',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 3,
            'total_value' => 19500,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totalTransactions', 2)
            ->assertJsonPath('data.pendingTransactions', 1)
            ->assertJsonPath('data.totalWeight', 2.5)
            ->assertJsonPath('data.totalValue', 16250)
            ->assertJsonPath('data.monthlyWeight', 2.5)
            ->assertJsonPath('data.monthlyTransactions', 1)
            ->assertJsonCount(7, 'data.weeklyWeights');
    }

    public function test_existing_password_hash_can_be_used_to_sign_in(): void
    {
        $user = $this->makeUser('login@example.test');

        $this->postJson('/api/auth', [
            'action' => 'login',
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'user');
    }

    public function test_admin_member_directory_searches_and_summarizes_member_activity(): void
    {
        $admin = $this->makeUser('members-admin@example.test', 'admin');
        $member = $this->makeUser('directory-member@example.test', 'user', 'Nadia Putri');
        $otherMember = $this->makeUser('directory-other@example.test', 'user', 'Rizky Aditya');
        $wasteType = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);

        Transaction::create([
            'transaction_code' => 'EB-MEMBER-APPROVED',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 4,
            'total_value' => 15200,
            'status' => 'approved',
        ]);
        Transaction::create([
            'transaction_code' => 'EB-MEMBER-PENDING',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 2,
            'total_value' => 7600,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.members', ['q' => 'Nadia']))
            ->assertOk()
            ->assertSee('Nadia Putri')
            ->assertSee('directory-member@example.test')
            ->assertSee('15.200')
            ->assertDontSee('Rizky Aditya');
    }

    public function test_admin_monthly_report_summarizes_period_and_exports_csv(): void
    {
        $admin = $this->makeUser('reports-admin@example.test', 'admin');
        $member = $this->makeUser('reports-member@example.test', 'user', 'Lina Sari');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        Transaction::create([
            'transaction_code' => 'EB-REPORT-APPROVED',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 2.5,
            'total_value' => 16250,
            'status' => 'approved',
        ]);
        Transaction::create([
            'transaction_code' => 'EB-REPORT-PENDING',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 3,
            'total_value' => 19500,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.transactions', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertViewHas('reportMonth', now()->format('Y-m'))
            ->assertViewHas('monthSummary.transactionCount', 2)
            ->assertViewHas('monthSummary.pendingCount', 1)
            ->assertViewHas('monthSummary.approvedWeight', 2.5)
            ->assertViewHas('monthSummary.approvedValue', 16250)
            ->assertSee('data-report-month')
            ->assertDontSee('transaction-report-stats');

        $this->get(route('admin.reports', ['month' => now()->format('Y-m')]))
            ->assertRedirect(route('admin.transactions', ['month' => now()->format('Y-m')]));

        $csvResponse = $this->get(route('admin.reports.export', ['month' => now()->format('Y-m')]));
        $csvResponse->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
        $this->assertStringContainsString('EB-REPORT-APPROVED', $csvResponse->streamedContent());
        $this->assertStringContainsString('EB-REPORT-PENDING', $csvResponse->streamedContent());

        $this->get(route('admin.reports', ['month' => 'invalid']))
            ->assertRedirect(route('admin.transactions', ['month' => now()->format('Y-m')]));
    }

    public function test_admin_ranking_lists_up_to_one_hundred_submitted_contributors_for_this_month(): void
    {
        $admin = $this->makeUser('ranking-admin@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        for ($number = 1; $number <= 101; $number++) {
            $user = $this->makeUser(
                sprintf('rank%03d@example.test', $number),
                'user',
                sprintf('Member %03d', $number),
            );

            Transaction::create([
                'transaction_code' => sprintf('EB-RANK-%03d', $number),
                'customer_name' => $user->name,
                'user_id' => $user->id,
                'waste_type_id' => $wasteType->id,
                'weight_kg' => 102 - $number,
                'total_value' => (102 - $number) * 6500,
                'status' => 'approved',
            ]);
        }

        $pendingUser = $this->makeUser('pending-rank@example.test', 'user', 'Pending Member');
        Transaction::create([
            'transaction_code' => 'EB-RANK-PENDING',
            'customer_name' => $pendingUser->name,
            'user_id' => $pendingUser->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 90,
            'total_value' => 90 * 6500,
            'status' => 'pending',
        ]);

        $lastMonthUser = $this->makeUser('old-rank@example.test', 'user', 'Last Month Member');
        $lastMonthTransaction = Transaction::create([
            'transaction_code' => 'EB-RANK-OLD',
            'customer_name' => $lastMonthUser->name,
            'user_id' => $lastMonthUser->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 1000,
            'total_value' => 1000 * 6500,
            'status' => 'approved',
        ]);
        $lastMonthTransaction->created_at = now()->subMonth();
        $lastMonthTransaction->saveQuietly();

        $this->actingAs($admin)
            ->get(route('admin.ranking'))
            ->assertOk()
            ->assertViewHas('rankings', fn($rankings): bool => $rankings->count() === 100)
            ->assertViewHas('totalContributors', 102)
            ->assertSee('ranking-list-scroll')
            ->assertSee('Member 001')
            ->assertSee('Member 099')
            ->assertDontSee('Member 101')
            ->assertDontSee('Member 100')
            ->assertSee('Pending Member')
            ->assertSee('90 kg')
            ->assertSee('menunggu verifikasi')
            ->assertDontSee('Last Month Member');

        $member = User::where('email', 'rank050@example.test')->firstOrFail();
        $this->actingAs($member)
            ->getJson(route('api.rankings'))
            ->assertOk()
            ->assertJsonCount(100, 'data.rankings')
            ->assertJsonPath('data.currentUser.rank', 51)
            ->assertJsonPath('data.currentUser.hasContribution', true)
            ->assertJsonPath('data.wasteRankings.0.name', 'Botol plastik');

        $pendingMember = User::where('email', 'pending-rank@example.test')->firstOrFail();
        $this->actingAs($pendingMember)
            ->getJson(route('api.rankings'))
            ->assertOk()
            ->assertJsonPath('data.currentUser.hasContribution', true)
            ->assertJsonPath('data.currentUser.hasPending', true)
            ->assertJsonPath('data.currentUser.weight_label', '90')
            ->assertJsonPath('data.currentUser.pendingWeightLabel', '90');
    }

    private function makeUser(string $email, string $role = 'user', ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? 'EcoBank Member',
            'email' => $email,
            'phone' => '081234567890',
            'district' => 'Batam Kota',
            'password_hash' => Hash::make('password123'),
            'role' => $role,
        ]);
    }
}
