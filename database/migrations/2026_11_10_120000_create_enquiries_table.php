<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('type', 20)->index();
            $table->string('status', 20)->default('new')->index();
            $table->timestamp('read_at')->nullable()->index();

            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_title')->nullable();
            $table->string('source_url', 2048)->nullable();

            $table->string('customer_name', 120);
            $table->string('customer_email', 190)->index();
            $table->string('customer_phone', 30)->nullable();
            $table->string('preferred_contact_method', 20)->nullable();

            $table->date('travel_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedTinyInteger('passengers')->nullable();
            $table->unsignedTinyInteger('luggage')->nullable();
            $table->string('pickup_location')->nullable();
            $table->string('dropoff_location')->nullable();
            $table->text('message');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Preserve enquiries submitted through the original trip-only endpoint.
        DB::table('trip_enquiries')->orderBy('id')->each(function (object $legacy): void {
            $title = $legacy->trip_id === null
                ? null
                : DB::table('trips')->where('id', $legacy->trip_id)->value('title');

            DB::table('enquiries')->insert([
                'reference' => 'ENQ-LEGACY-'.$legacy->id,
                'type' => 'trip',
                'status' => $legacy->status,
                'read_at' => $legacy->handled_at,
                'trip_id' => $legacy->trip_id,
                'vehicle_id' => null,
                'booking_id' => null,
                'subject_title' => $title,
                'source_url' => null,
                'customer_name' => $legacy->name,
                'customer_email' => $legacy->email,
                'customer_phone' => $legacy->phone,
                'preferred_contact_method' => null,
                'travel_date' => $legacy->travel_date,
                'return_date' => null,
                'passengers' => $legacy->travellers,
                'luggage' => null,
                'pickup_location' => null,
                'dropoff_location' => null,
                'message' => $legacy->message,
                'assigned_to' => $legacy->handled_by,
                'handled_at' => $legacy->handled_at,
                'internal_notes' => null,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at,
            ]);
        });

        Schema::dropIfExists('trip_enquiries');
    }

    public function down(): void
    {
        Schema::create('trip_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30);
            $table->date('travel_date')->nullable();
            $table->unsignedTinyInteger('travellers')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        DB::table('enquiries')->where('type', 'trip')->orderBy('id')->each(function (object $enquiry): void {
            DB::table('trip_enquiries')->insert([
                'trip_id' => $enquiry->trip_id,
                'name' => $enquiry->customer_name,
                'email' => $enquiry->customer_email,
                'phone' => $enquiry->customer_phone ?? '',
                'travel_date' => $enquiry->travel_date,
                'travellers' => $enquiry->passengers,
                'message' => $enquiry->message,
                'status' => $enquiry->status,
                'handled_by' => $enquiry->assigned_to,
                'handled_at' => $enquiry->handled_at,
                'created_at' => $enquiry->created_at,
                'updated_at' => $enquiry->updated_at,
            ]);
        });

        Schema::dropIfExists('enquiries');
    }
};
