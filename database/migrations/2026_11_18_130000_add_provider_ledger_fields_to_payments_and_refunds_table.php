<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider_charge_reference')->nullable()->unique()->after('provider_payment_intent');
            $table->string('provider_balance_transaction_reference')->nullable()->unique()->after('provider_charge_reference');
            $table->bigInteger('provider_balance_amount')->nullable()->after('provider_balance_transaction_reference');
            $table->char('provider_balance_currency', 3)->nullable()->after('provider_balance_amount');
            $table->unsignedInteger('provider_fee_amount')->nullable()->after('provider_balance_currency');
            $table->json('provider_fee_details')->nullable()->after('provider_fee_amount');
            $table->bigInteger('provider_net_amount')->nullable()->after('provider_fee_details');
            $table->decimal('provider_exchange_rate', 18, 10)->nullable()->after('provider_net_amount');
            $table->dateTime('provider_ledger_synced_at')->nullable()->after('provider_exchange_rate');
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('provider_balance_transaction_reference')->nullable()->unique()->after('provider_reference');
            $table->bigInteger('provider_balance_amount')->nullable()->after('provider_balance_transaction_reference');
            $table->char('provider_balance_currency', 3)->nullable()->after('provider_balance_amount');
            $table->unsignedInteger('provider_fee_amount')->nullable()->after('provider_balance_currency');
            $table->json('provider_fee_details')->nullable()->after('provider_fee_amount');
            $table->bigInteger('provider_net_amount')->nullable()->after('provider_fee_details');
            $table->decimal('provider_exchange_rate', 18, 10)->nullable()->after('provider_net_amount');
            $table->dateTime('provider_ledger_synced_at')->nullable()->after('provider_exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropUnique(['provider_balance_transaction_reference']);
            $table->dropColumn([
                'provider_balance_transaction_reference', 'provider_balance_amount', 'provider_balance_currency',
                'provider_fee_amount', 'provider_fee_details', 'provider_net_amount', 'provider_exchange_rate',
                'provider_ledger_synced_at',
            ]);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['provider_charge_reference']);
            $table->dropUnique(['provider_balance_transaction_reference']);
            $table->dropColumn([
                'provider_charge_reference', 'provider_balance_transaction_reference', 'provider_balance_amount',
                'provider_balance_currency', 'provider_fee_amount', 'provider_fee_details', 'provider_net_amount',
                'provider_exchange_rate', 'provider_ledger_synced_at',
            ]);
        });
    }
};
