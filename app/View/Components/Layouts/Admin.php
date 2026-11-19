<?php

namespace App\View\Components\Layouts;

use App\Models\Booking;
use App\Models\Enquiry;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Admin extends Component
{
    public function __construct(public readonly ?string $title = null) {}

    public function render(): View
    {
        $user = auth()->user();
        $navCounts = [
            'bookings' => $user?->can('bookings.view') ? Booking::query()->unseen()->count() : 0,
            'enquiries' => $user?->can('enquiries.view') ? Enquiry::query()->whereNull('read_at')->count() : 0,
        ];

        return view('layouts.admin', compact('navCounts'));
    }
}
