<?php

namespace App\Actions;

use App\Models\Inquiry;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripNote;
use App\Notifications\CustomerInquiryConfirmedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Converts an inquiry to a trip: creates a Trip (reward pre-filled from the program), generates the
 * trip days from the date range, links the inquiry and AUTOMATICALLY sends the customer a
 * confirmation email (deliverable #6).
 */
class ConvertInquiryToTrip
{
    public function handle(Inquiry $inquiry, array $data): Trip
    {
        return DB::transaction(function () use ($inquiry, $data) {
            $from = Carbon::parse($data['date_from']);
            $to = Carbon::parse($data['date_to'] ?? $data['date_from']);
            if ($to->lt($from)) {
                $to = $from->copy();
            }

            $trip = Trip::create([
                'trip_program_id' => $data['trip_program_id'] ?? $inquiry->program_id,
                'type' => $data['type'] ?? 'guided',
                'name' => $inquiry->program?->name ?? $inquiry->proposed_program_name ?? 'Výlet z poptávky',
                'client_name' => $inquiry->client_name,
                'client_email' => $inquiry->client_email,
                'client_phone' => $inquiry->client_phone,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'planned_paying_count' => $data['planned_paying_count'] ?? $inquiry->people_count,
                'status' => 'planned',
                'inquiry_id' => $inquiry->id,
            ]);

            // Location template from the program (order → location_id); extra days stay without a location.
            $dayLocations = $trip->program
                ? $trip->program->programDays()->pluck('location_id', 'order')
                : collect();

            // Generate a day for every calendar day in the range.
            $order = 1;
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                TripDay::create([
                    'trip_id' => $trip->id,
                    'date' => $day->toDateString(),
                    'order' => $order,
                    'location_id' => $dayLocations[$order] ?? null,
                ]);
                $order++;
            }

            // Carry the inquiry note over as the first post in the trip's note thread.
            if (filled($inquiry->note)) {
                TripNote::create([
                    'trip_id' => $trip->id,
                    'author_id' => auth()->id(),
                    'text' => 'Z poptávky: ' . $inquiry->note,
                ]);
            }

            $inquiry->update(['trip_id' => $trip->id, 'status' => 'confirmed']);

            // Automatic confirmation email to the customer (queued).
            if ($inquiry->client_email) {
                Notification::route('mail', $inquiry->client_email)
                    ->notify(new CustomerInquiryConfirmedNotification($trip));
            }

            return $trip;
        });
    }
}
