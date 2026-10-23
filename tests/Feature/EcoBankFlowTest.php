<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WasteType;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EcoBankFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_and_only_see_their_own_deposits(): void
    {
        $user = $this->makeUser('member@example.test');
        $otherUser = $this->makeUser('other@example.test');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        $secondWasteType = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'wasteTypeIds' => [$wasteType->id, $secondWasteType->id],
                'deliveryMethod' => 'self_delivery',
            ])
            ->assertCreated()
            ->assertJsonPath('data.weight', 0)
            ->assertJsonPath('data.value', 0)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.deliveryMethod', 'self_delivery')
            ->assertJsonPath('data.month', now()->format('Y-m'))
            ->assertJsonCount(2, 'items');

        Transaction::create([
            'transaction_code' => 'EB-OTHER-APPROVED',
            'customer_name' => $otherUser->name,
            'user_id' => $otherUser->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 10,
            'total_value' => 65000,
            'status' => 'approved',
        ]);

        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $user->name)
            ->assertJsonPath('summary.balance', 0)
            ->assertJsonPath('summary.pendingCount', 2);
    }

    public function test_pickup_deposit_requires_confirmation_and_actual_weight_before_credit(): void
    {
        $member = $this->makeUser('pickup-member@example.test');
        $admin = $this->makeUser('pickup-admin@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->postJson('/api/transactions', [
                'wasteTypeId' => $wasteType->id,
                'deliveryMethod' => 'pickup',
                'pickupAddress' => 'Jl. Melati No. 10, Batam Kota',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.stage', 'requested')
            ->assertJsonPath('data.deliveryMethod', 'pickup');

        $transaction = Transaction::firstOrFail();
        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $transaction->id, [
                'action' => 'finalize_weighing',
                'actualWeight' => '2.50',
            ])
            ->assertConflict();

        $this->putJson('/api/transactions/' . $transaction->id, ['action' => 'confirm_pickup'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.stage', 'pickup_confirmed');

        $this->putJson('/api/transactions/' . $transaction->id, ['action' => 'start_weighing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.stage', 'weighing');
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
            'process_stage' => 'weighing',
            'total_value' => 0,
        ]);

        $this->putJson('/api/transactions/' . $transaction->id, [
            'action' => 'finalize_weighing',
            'actualWeight' => '3.25',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.stage', 'completed')
            ->assertJsonPath('data.weight', 3.25)
            ->assertJsonPath('data.value', 21125);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'delivery_method' => 'pickup',
            'weight_kg' => 3.25,
            'total_value' => 21125,
            'status' => 'approved',
        ]);
    }

    public function test_self_delivery_can_enter_weighing_and_credit_only_after_finalization(): void
    {
        $member = $this->makeUser('self-delivery-member@example.test');
        $admin = $this->makeUser('self-delivery-admin@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->postJson('/api/transactions', [
                'wasteTypeId' => $wasteType->id,
                'deliveryMethod' => 'self_delivery',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'requested')
            ->assertJsonPath('data.deliveryMethod', 'self_delivery');

        $transaction = Transaction::firstOrFail();
        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $transaction->id, ['action' => 'start_weighing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.stage', 'weighing');

        $this->putJson('/api/transactions/' . $transaction->id, [
            'action' => 'finalize_weighing',
            'actualWeight' => '3.50',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.value', 13300);

        $this->actingAs($member)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('summary.balance', 13300)
            ->assertJsonPath('summary.approvedWeight', 3.5)
            ->assertJsonPath('summary.pendingCount', 0)
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.value', 13300);
    }

    public function test_admin_can_weigh_each_type_in_a_multi_type_request_together(): void
    {
        $member = $this->makeUser('multi-weigh-member@example.test');
        $admin = $this->makeUser('multi-weigh-admin@example.test', 'admin');
        $plastic = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        $cardboard = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);

        $response = $this->actingAs($member)
            ->postJson('/api/transactions', [
                'wasteTypeIds' => [$plastic->id, $cardboard->id],
                'deliveryMethod' => 'self_delivery',
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'items');
        $items = $response->json('items');
        $this->assertSame($items[0]['groupId'], $items[1]['groupId']);
        $weightsById = [
            $items[0]['dbId'] => '2.50',
            $items[1]['dbId'] => '3.25',
        ];

        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $items[0]['dbId'], [
                'action' => 'start_weighing',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'weighing')
            ->assertJsonCount(2, 'items');

        $this->putJson('/api/transactions/' . $items[0]['dbId'], [
            'action' => 'finalize_weighing',
            'actualWeights' => $weightsById,
        ])
            ->assertOk()
            ->assertJsonCount(2, 'items');

        $this->assertDatabaseHas('transactions', [
            'id' => $items[0]['dbId'],
            'status' => 'approved',
            'weight_kg' => 2.5,
            'total_value' => 16250,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $items[1]['dbId'],
            'status' => 'approved',
            'weight_kg' => 3.25,
            'total_value' => 12350,
        ]);

        $this->actingAs($member)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('summary.balance', 28600)
            ->assertJsonPath('summary.approvedWeight', 5.75)
            ->assertJsonPath('summary.pendingCount', 0);
    }

    public function test_user_can_edit_and_cancel_only_their_unprocessed_deposits(): void
    {
        $member = $this->makeUser('deposit-actions-member@example.test');
        $otherMember = $this->makeUser('deposit-actions-other@example.test');
        $admin = $this->makeUser('deposit-actions-admin@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        $otherWasteType = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->postJson('/api/transactions', [
                'wasteTypeId' => $wasteType->id,
                'deliveryMethod' => 'self_delivery',
            ])
            ->assertCreated();
        $transaction = Transaction::firstOrFail();

        $this->actingAs($otherMember)
            ->putJson('/api/transactions/' . $transaction->id, [
                'action' => 'cancel',
            ])
            ->assertNotFound();

        $this->actingAs($member)
            ->putJson('/api/transactions/' . $transaction->id, [
                'action' => 'edit',
                'wasteTypeId' => $otherWasteType->id,
                'deliveryMethod' => 'pickup',
                'pickupAddress' => 'Jl. Melati No. 10',
            ])
            ->assertOk()
            ->assertJsonPath('data.weight', 0)
            ->assertJsonPath('data.value', 0);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'waste_type_id' => $otherWasteType->id,
            'delivery_method' => 'pickup',
            'pickup_address' => 'Jl. Melati No. 10',
            'unit_price' => 3800,
            'weight_kg' => 0,
            'total_value' => 0,
        ]);

        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $transaction->id, ['action' => 'confirm_pickup'])
            ->assertOk();
        $this->actingAs($member)
            ->putJson('/api/transactions/' . $transaction->id, ['action' => 'cancel'])
            ->assertConflict();
        $this->actingAs($admin);
        $this->putJson('/api/transactions/' . $transaction->id, ['action' => 'start_weighing'])
            ->assertOk();
        $this->actingAs($member)
            ->putJson('/api/transactions/' . $transaction->id, [
                'action' => 'edit',
                'wasteTypeId' => $wasteType->id,
                'weight' => '4',
                'deliveryMethod' => 'self_delivery',
            ])
            ->assertConflict();

        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $transaction->id, ['action' => 'cancel'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_user_can_cancel_self_delivery_while_admin_is_weighing_before_approval(): void
    {
        $member = $this->makeUser('self-cancel-member@example.test');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        $transaction = Transaction::create([
            'transaction_code' => 'EB-SELF-CANCEL',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 2,
            'total_value' => 13000,
            'delivery_method' => 'self_delivery',
            'process_stage' => 'weighing',
            'status' => 'pending',
        ]);

        $this->actingAs($member)
            ->putJson('/api/transactions/' . $transaction->id, ['action' => 'cancel'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_admin_cannot_edit_weight_and_completed_deposits_are_final(): void
    {
        $member = $this->makeUser('deposit-correction-member@example.test');
        $admin = $this->makeUser('deposit-correction-admin@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        $transaction = Transaction::create([
            'transaction_code' => 'EB-EDIT-COMPLETED',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 2,
            'total_value' => 13000,
            'unit_price' => 6500,
            'process_stage' => 'weighing',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $transaction->id, [
                'action' => 'edit_weight',
                'actualWeight' => '3.25',
            ])
            ->assertUnprocessable();

        $this->putJson('/api/transactions/' . $transaction->id, [
            'action' => 'finalize_weighing',
            'actualWeight' => '3.25',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.stage', 'completed');

        $this->actingAs($member)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('summary.balance', 21125)
            ->assertJsonPath('summary.approvedWeight', 3.25);

        $this->putJson('/api/transactions/' . $transaction->id, ['action' => 'cancel'])
            ->assertConflict();

        $this->actingAs($admin)
            ->putJson('/api/transactions/' . $transaction->id, [
                'action' => 'edit_weight',
                'actualWeight' => '4',
            ])
            ->assertUnprocessable();
        $this->putJson('/api/transactions/' . $transaction->id, ['action' => 'cancel'])
            ->assertConflict();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'approved',
            'process_stage' => 'completed',
            'weight_kg' => 3.25,
            'total_value' => 21125,
        ]);
    }

    public function test_pickup_address_is_required_only_for_pickup_deposits(): void
    {
        $user = $this->makeUser('pickup-address@example.test');
        $wasteType = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'wasteTypeId' => $wasteType->id,
                'deliveryMethod' => 'pickup',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pickupAddress');
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
        Transaction::create([
            'transaction_code' => 'EB-DASH-WEIGHING',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 1.5,
            'total_value' => 9750,
            'delivery_method' => 'pickup',
            'status' => 'weighing',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totalTransactions', 3)
            ->assertJsonPath('data.totalMembers', 1)
            ->assertJsonPath('data.pendingTransactions', 2)
            ->assertJsonPath('data.totalWeight', 2.5)
            ->assertJsonPath('data.totalValue', 16250)
            ->assertJsonPath('data.monthlyWeight', 2.5)
            ->assertJsonPath('data.monthlyTransactions', 1)
            ->assertJsonCount(7, 'data.chartWeights')
            ->assertJsonPath('data.chartPeriod', '7d');
    }

    public function test_admin_navigation_shows_the_server_rendered_pending_request_count(): void
    {
        $admin = $this->makeUser('pending-count-admin@example.test', 'admin');
        $member = $this->makeUser('pending-count-member@example.test');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'GROUP-1-A', 'group' => 'EBG-GROUP-1', 'status' => 'pending'],
            ['code' => 'GROUP-1-B', 'group' => 'EBG-GROUP-1', 'status' => 'pending'],
            ['code' => 'PICKUP', 'group' => null, 'status' => 'pickup_confirmed'],
            ['code' => 'WEIGHING', 'group' => null, 'status' => 'weighing'],
            ['code' => 'APPROVED', 'group' => null, 'status' => 'approved'],
        ] as $fixture) {
            Transaction::create([
                'transaction_code' => 'EB-PENDING-COUNT-' . $fixture['code'],
                'request_group_code' => $fixture['group'],
                'customer_name' => $member->name,
                'user_id' => $member->id,
                'waste_type_id' => $wasteType->id,
                'weight_kg' => 1,
                'total_value' => 6500,
                'status' => $fixture['status'],
            ]);
        }

        $this->actingAs($admin)
            ->get('/dashboard_bank_sampah.html')
            ->assertOk()
            ->assertSee('data-pending-count>3</span>', false);

        $this->get('/transaksi.html')
            ->assertOk()
            ->assertSee('data-pending-count>3</span>', false);
    }

    public function test_admin_dashboard_chart_changes_to_the_selected_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        $admin = $this->makeUser('dashboard-period-admin@example.test', 'admin');
        $member = $this->makeUser('dashboard-period-member@example.test');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'TODAY', 'daysAgo' => 0, 'weight' => 1.5],
            ['code' => 'EIGHT-DAYS', 'daysAgo' => 8, 'weight' => 2],
            ['code' => 'THIRTY-ONE-DAYS', 'daysAgo' => 31, 'weight' => 3],
            ['code' => 'TWO-MONTHS', 'daysAgo' => 61, 'weight' => 4],
        ] as $fixture) {
            $transaction = Transaction::create([
                'transaction_code' => 'EB-CHART-' . $fixture['code'],
                'customer_name' => $member->name,
                'user_id' => $member->id,
                'waste_type_id' => $wasteType->id,
                'weight_kg' => $fixture['weight'],
                'total_value' => $fixture['weight'] * 6500,
                'status' => 'approved',
            ]);
            $transaction->forceFill([
                'created_at' => now()->subDays($fixture['daysAgo']),
            ])->save();
        }

        $this->actingAs($admin)
            ->getJson('/api/dashboard?period=7d')
            ->assertOk()
            ->assertJsonCount(7, 'data.chartWeights')
            ->assertJsonPath('data.chartPeriod', '7d')
            ->assertJsonPath('data.chartWeights.6.weight', 1.5);

        $this->getJson('/api/dashboard?period=30d')
            ->assertOk()
            ->assertJsonCount(30, 'data.chartWeights')
            ->assertJsonPath('data.chartPeriod', '30d')
            ->assertJsonPath('data.chartWeights.21.weight', 2);

        $this->getJson('/api/dashboard?period=12m')
            ->assertOk()
            ->assertJsonCount(12, 'data.chartWeights')
            ->assertJsonPath('data.chartPeriod', '12m')
            ->assertJsonPath('data.chartWeights.9.weight', 4)
            ->assertJsonPath('data.chartWeights.10.weight', 3);

        $this->getJson('/api/dashboard?period=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
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

    public function test_user_and_admin_can_update_their_own_profile(): void
    {
        foreach (['user', 'admin'] as $role) {
            $user = $this->makeUser($role . '-profile@example.test', $role);

            $this->actingAs($user)
                ->putJson('/api/profile', [
                    'name' => 'Updated ' . $role,
                    'email' => $user->email,
                    'phone' => '081234567891',
                    'district' => 'Nongsa',
                ])
                ->assertOk()
                ->assertJsonPath('data.name', 'Updated ' . $role)
                ->assertJsonPath('data.phone', '081234567891')
                ->assertJsonPath('data.district', 'Nongsa');

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => 'Updated ' . $role,
                'phone' => '081234567891',
                'district' => 'Nongsa',
            ]);

            $this->putJson('/api/profile', [
                'name' => 'Updated ' . $role,
                'email' => $user->email,
                'phone' => '081234567891',
                'district' => 'Nongsa',
                'password' => str_repeat('é', 40),
                'currentPassword' => 'password123',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');

            $this->putJson('/api/profile', [
                'name' => 'Updated ' . $role,
                'email' => $user->email,
                'phone' => '081234567891',
                'district' => 'Nongsa',
                'password' => 'new-password-123',
                'currentPassword' => 'password123',
            ])->assertOk();
            $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password_hash));
        }
    }

    public function test_user_pages_are_separate_and_profile_is_shared_with_admins(): void
    {
        $user = $this->makeUser('pages-user@example.test');
        $this->actingAs($user)
            ->get(route('user.deposit'))
            ->assertOk()
            ->assertSee('Ajukan setoran')
            ->assertSee('data-deposit-types', false)
            ->assertDontSee('deposit-weight')
            ->assertDontSee('name="weight"', false)
            ->assertDontSee('Semua setoran');
        $this->get(route('user.history'))
            ->assertOk()
            ->assertSee('Semua setoran')
            ->assertSee('Saldo tersedia')
            ->assertSee('data-deposit-history-scroll', false)
            ->assertDontSee('data-withdrawal-history', false)
            ->assertDontSee('Ajukan setoran');
        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('Informasi pribadi')
            ->assertSee('data-user-profile-rank')
            ->assertSee('href="setor_sampah.html"', false)
            ->assertDontSee('data-admin-total-transactions');

        $admin = $this->makeUser('pages-admin@example.test', 'admin');
        $this->actingAs($admin)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Informasi akun pengurus')
            ->assertSee('data-admin-total-transactions')
            ->assertSee('href="listing_limbah_sampah.html"', false)
            ->assertDontSee('data-user-profile-rank');
        $this->get(route('user.deposit'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_ranking_page_has_user_navigation_without_admin_controls(): void
    {
        $user = $this->makeUser('ranking-page-user@example.test');

        $this->actingAs($user)
            ->get(route('user.ranking'))
            ->assertOk()
            ->assertSee('Peringkat bulan ini')
            ->assertSee('Peringkat Anda')
            ->assertSee('href="peringkat_user.html"', false)
            ->assertSee('href="setor_sampah.html"', false)
            ->assertDontSee('transaksi.html')
            ->assertDontSee('pengguna.html')
            ->assertDontSee('listing_limbah_sampah.html');

        $admin = $this->makeUser('ranking-page-admin@example.test', 'admin');
        $this->actingAs($admin)
            ->get(route('user.ranking'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_can_withdraw_approved_balance_through_admin_review_and_transfer(): void
    {
        $user = $this->makeUser('withdraw-member@example.test', 'user', 'Alza Pratama');
        $admin = $this->makeUser('withdraw-admin@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        Transaction::create([
            'transaction_code' => 'EB-WITHDRAW-BALANCE',
            'customer_name' => $user->name,
            'user_id' => $user->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 20,
            'total_value' => 130000,
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->get(route('user.withdrawals'))
            ->assertOk()
            ->assertSee('Tarik saldo')
            ->assertSee('Saldo tersedia')
            ->assertSee('Riwayat penarikan')
            ->assertSee('Kembali ke beranda')
            ->assertDontSee('href="penarikan_saldo.html"', false)
            ->assertDontSee('penarikan_admin.html');

        $this->postJson(route('api.withdrawals.store'), [
            'amount' => 4000,
            'payoutBank' => 'BCA',
            'accountNumber' => '1234567890',
            'accountName' => $user->name,
        ])->assertUnprocessable();

        $this->postJson(route('api.withdrawals.store'), [
            'amount' => 50000,
            'payoutBank' => 'BCA',
            'accountNumber' => '1234567890',
            'accountName' => $user->name,
            'saveAccount' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.accountNumber', '••••••7890')
            ->assertJsonPath('summary.availableBalance', 80000);
        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('summary.balance', 80000);

        $withdrawal = Withdrawal::firstOrFail();
        $this->assertSame('1234567890', $withdrawal->payout_account_number);
        $this->assertNotSame('1234567890', $withdrawal->getRawOriginal('payout_account_number'));
        $this->assertSame('1234567890', $user->fresh()->payout_account_number);

        $otherUser = $this->makeUser('withdraw-other@example.test');
        $this->actingAs($otherUser)
            ->getJson(route('api.withdrawals'))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.availableBalance', 0);
        $this->postJson(route('api.withdrawals.cancel', $withdrawal->id))
            ->assertNotFound();

        $this->actingAs($user);
        $this->postJson(route('api.withdrawals.store'), [
            'amount' => 80001,
            'payoutBank' => 'BCA',
            'accountNumber' => '1234567890',
            'accountName' => $user->name,
        ])->assertUnprocessable();

        $this->actingAs($user)
            ->putJson(route('api.withdrawals.update', $withdrawal->id), [
                'action' => 'approve',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.withdrawals'))
            ->assertOk()
            ->assertSee('Semua permintaan');
        $this->getJson(route('api.withdrawals'))
            ->assertOk()
            ->assertJsonPath('data.0.accountNumber', '1234567890');
        $this->putJson(route('api.withdrawals.update', $withdrawal->id), [
            'action' => 'mark_paid',
        ])->assertConflict();

        $this->putJson(route('api.withdrawals.update', $withdrawal->id), [
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.accountNumber', '1234567890');

        $this->actingAs($user)
            ->getJson(route('api.withdrawals'))
            ->assertOk()
            ->assertJsonPath('summary.availableBalance', 80000)
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.accountNumber', '••••••7890');

        $this->actingAs($admin)
            ->putJson(route('api.withdrawals.update', $withdrawal->id), [
                'action' => 'mark_paid',
                'reference' => 'TRF-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.transferReference', 'TRF-001');
        $this->putJson(route('api.withdrawals.update', $withdrawal->id), [
            'action' => 'mark_paid',
        ])->assertConflict();

        $this->actingAs($user)
            ->getJson(route('api.withdrawals'))
            ->assertJsonPath('summary.availableBalance', 80000)
            ->assertJsonPath('data.0.status', 'paid');
        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('summary.balance', 80000);
    }

    public function test_rejected_or_cancelled_withdrawal_releases_reserved_balance(): void
    {
        $user = $this->makeUser('withdraw-release@example.test');
        $admin = $this->makeUser('withdraw-reviewer@example.test', 'admin');
        $wasteType = WasteType::create([
            'name' => 'Kardus',
            'category' => 'Kertas',
            'price' => 3800,
            'is_active' => true,
        ]);
        Transaction::create([
            'transaction_code' => 'EB-WITHDRAW-RELEASE',
            'customer_name' => $user->name,
            'user_id' => $user->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 5,
            'total_value' => 19000,
            'status' => 'approved',
        ]);

        $this->actingAs($user)->postJson(route('api.withdrawals.store'), [
            'amount' => 10000,
            'payoutBank' => 'DANA',
            'accountNumber' => '081234567890',
            'accountName' => $user->name,
        ])->assertCreated();
        $withdrawal = Withdrawal::firstOrFail();

        $this->postJson(route('api.withdrawals.cancel', $withdrawal->id))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->getJson(route('api.withdrawals'))
            ->assertJsonPath('summary.availableBalance', 19000);

        $this->postJson(route('api.withdrawals.store'), [
            'amount' => 10000,
            'payoutBank' => 'DANA',
            'accountNumber' => '081234567890',
            'accountName' => $user->name,
        ])->assertCreated();
        $secondWithdrawal = Withdrawal::latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->putJson(route('api.withdrawals.update', $secondWithdrawal->id), [
                'action' => 'reject',
                'reason' => 'Nama rekening tidak sesuai.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejectionReason', 'Nama rekening tidak sesuai.');

        $this->actingAs($user)
            ->getJson(route('api.withdrawals'))
            ->assertJsonPath('summary.availableBalance', 19000);

        $this->actingAs($user)
            ->get(route('admin.withdrawals'))
            ->assertRedirect(route('user.home'));
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
            ->assertSee('href="penarikan_admin.html"', false)
            ->assertDontSee('Kembali ke beranda')
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

    public function test_admin_transaction_history_is_separate_from_transaction_management(): void
    {
        $admin = $this->makeUser('history-admin@example.test', 'admin');
        $member = $this->makeUser('history-member@example.test', 'user', 'Lina Sari');
        $wasteType = WasteType::create([
            'name' => 'Botol plastik',
            'category' => 'Plastik',
            'price' => 6500,
            'is_active' => true,
        ]);
        Transaction::create([
            'transaction_code' => 'EB-HISTORY-APPROVED',
            'customer_name' => $member->name,
            'user_id' => $member->id,
            'waste_type_id' => $wasteType->id,
            'weight_kg' => 2.5,
            'total_value' => 16250,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.history', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Riwayat transaksi')
            ->assertSee('EB-HISTORY-APPROVED')
            ->assertSee('Lina Sari');

        $this->get(route('admin.transactions'))
            ->assertOk()
            ->assertSee('<h1>Transaksi</h1>', false)
            ->assertSee('Setoran masuk')
            ->assertSee('data-transaction-rows', false)
            ->assertSee('data-waste-types-dialog', false)
            ->assertSee('data-waste-types-list', false)
            ->assertSee('Tidak ada setoran yang menunggu')
            ->assertDontSee('<option value="approved">', false)
            ->assertDontSee('<option value="cancelled">', false)
            ->assertDontSee('EB-HISTORY-APPROVED');
    }

    public function test_admin_history_groups_multi_type_deposits_and_shows_colored_status(): void
    {
        $admin = $this->makeUser('grouped-history-admin@example.test', 'admin');
        $member = $this->makeUser('grouped-history-member@example.test', 'user', 'Dina Putri');
        $groupCode = 'EBG-HISTORY-MULTI';
        $wasteTypes = [
            ['name' => 'Botol plastik', 'category' => 'Plastik', 'weight' => 1.5, 'value' => 9750],
            ['name' => 'Kardus', 'category' => 'Kertas', 'weight' => 2, 'value' => 7600],
            ['name' => 'Kaleng aluminium', 'category' => 'Logam', 'weight' => 0.5, 'value' => 4250],
            ['name' => 'Botol kaca bening', 'category' => 'Kaca', 'weight' => 1, 'value' => 1800],
        ];

        foreach ($wasteTypes as $index => $fixture) {
            $wasteType = WasteType::create([
                'name' => $fixture['name'],
                'category' => $fixture['category'],
                'price' => (int) ($fixture['value'] / $fixture['weight']),
                'is_active' => true,
            ]);
            Transaction::create([
                'transaction_code' => sprintf('EB-HISTORY-MULTI-%d', $index + 1),
                'request_group_code' => $groupCode,
                'customer_name' => $member->name,
                'user_id' => $member->id,
                'waste_type_id' => $wasteType->id,
                'weight_kg' => $fixture['weight'],
                'total_value' => $fixture['value'],
                'status' => 'pending',
                'process_stage' => 'pickup_confirmed',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.history', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('1 transaksi pada', false)
            ->assertSee($groupCode)
            ->assertSee('Lihat jenis sampah')
            ->assertSee('data-history-waste-types-dialog', false)
            ->assertSee('status-pickup_confirmed', false)
            ->assertSee('Penjemputan dikonfirmasi')
            ->assertViewHas('transactions', fn($transactions): bool => $transactions->count() === 1
                && count($transactions->first()->items) === 4
                && $transactions->first()->weight_kg === 5.0
                && $transactions->first()->total_value === 23400);
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
        $this->get(route('user.ranking'))
            ->assertOk()
            ->assertSee('Member 050 (Anda)')
            ->assertSee('current-user-rank');

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
