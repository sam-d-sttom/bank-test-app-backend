<?php

namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Wallet;
use App\Models\Transaction;

class TransactionsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    public function test_creates_credit_and_updates_balance() {
        $wallet = Wallet::first();
        $payload = [
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 100.50,
            'reference' => 'TX-CR-1',
            'idempotency_key' => 'key-1'
        ];
        $res = $this->postJson('/api/transactions', $payload);
        $res->assertStatus(201)
            ->assertJsonPath('wallet.balance', number_format(($wallet->balance + 100.50),2,'.',''));
        $this->assertDatabaseHas('transactions', ['reference' => 'TX-CR-1']);
    }

    public function test_prevents_overdraft_on_debit() {
        $wallet = Wallet::first();
        $payload = [
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 999999999.99,
            'reference' => 'TX-OVER',
            'idempotency_key' => 'key-2'
        ];
        $res = $this->postJson('/api/transactions', $payload);
        $res->assertStatus(422);
        $this->assertDatabaseMissing('transactions', ['reference' => 'TX-OVER']);
    }

    public function test_repeating_same_idempotency_returns_same_result() {
        $wallet = Wallet::first();
        $payload = [
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 10,
            'reference' => 'TX-IDEMP-1',
            'idempotency_key' => 'idem-1'
        ];
        $res1 = $this->postJson('/api/transactions', $payload);
        $res1->assertStatus(201);
        $this->assertDatabaseHas('idempotency_keys', ['key' => 'idem-1']);

        // Second call with same key but different reference. should return same original response
        $payload['reference'] = 'TX-IDEMP-1-2';
        $res2 = $this->postJson('/api/transactions', $payload);
        $res2->assertStatus(200);

        // ensuring only one transaction created
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_get_transactions_filters() {
        $wallet = Wallet::first();
        Transaction::create(['wallet_id'=>$wallet->id,'type'=>'credit','amount'=>100,'reference'=>'REF-1']);
        Transaction::create(['wallet_id'=>$wallet->id,'type'=>'debit','amount'=>50,'reference'=>'REF-2']);
        $res = $this->getJson('/api/transactions?type=credit&q=REF-1');
        $res->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.total_in', number_format(100,2,'.',''));
    }
}
