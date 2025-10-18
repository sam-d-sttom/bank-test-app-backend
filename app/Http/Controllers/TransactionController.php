<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreTransactionRequest;

class TransactionController extends Controller
{

    public function allTransactions(Request $req)
    {
        // Get all query parameters.
        $transactionsPerPage = (int) $req->query('per_page', 10);
        $reference = $req->query('q');
        $type = $req->query('type');
        $from = $req->query('from');
        $to = $req->query('to');

        // Start query building
        $query = Transaction::query()->orderByDesc('created_at');

        if ($reference) {
            $query->where('reference', 'like', "%{$reference}%");
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Calculate totals
        $totalIn = $query->clone()->where('type', 'credit')->sum('amount');
        $totalOut = $query->clone()->where('type', 'debit')->sum('amount');

        // Pagination
        $paginator = $query->paginate($transactionsPerPage)->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage()
            ],
            'summary' => [
                'total_in' => number_format((float)$totalIn, 2, '.', ','),
                'total_out' => number_format((float)$totalOut, 2, '.', ',')
            ]
        ]);
    }

    public function storeTransaction(StoreTransactionRequest $req)
    {
        $data = $req->validated();

        DB::beginTransaction();

        try {
            $wallet = Wallet::lockForUpdate()->findOrFail($data['wallet_id']);

            $amount = round($data['amount'], 2);

            if ($data['type'] === 'debit' && bccomp((string)$wallet->balance, (string)$amount, 2) < 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient funds',
                ], 422);
            }

            if ($data['type'] === 'credit') {
                $wallet->balance = bcadd((string)$wallet->balance, (string)$amount, 2);
            } else {
                $wallet->balance = bcsub((string)$wallet->balance, (string)$amount, 2);
            }
            $wallet->save();

            $txn = Transaction::create([
                'wallet_id' => $wallet->id,
                'type' => $data['type'],
                'amount' => $amount,
                'reference' => $data['reference'],
            ]);

            // Update idempotency from middlesware
            if ($record = $req->get('idempotency_record')) {
                $record->update([
                    'request_hash' => sha1(json_encode($req->all())),
                    'response' => [
                        'transaction' => $txn->toArray(),
                        'wallet' => ['id' => $wallet->id, 'balance' => (string)$wallet->balance]
                    ]
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => "Transaction successfully created!",
                'transaction' => $txn,
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (string)$wallet->balance,
                    'currency' => $wallet->currency,
                ],
            ], 201);
        } catch (Exception $error) {
            DB::rollBack();
            logger("Something went wrong while recording a transaction: " . $error->getMessage());
        }
    }
}
