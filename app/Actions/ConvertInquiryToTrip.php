<?php

namespace App\Actions;

use App\Models\Inquiry;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripNote;
use App\Models\TripProgram;
use App\Notifications\CustomerInquiryConfirmedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Converts an inquiry to a trip: creates a Trip (reward pre-filled from the program), generates the
 * trip days, links the inquiry and AUTOMATICALLY sends the customer a confirmation email (deliverable #6).
 *
 * The admin confirms the start date (date_from) and the start time; the END date is derived from the
 * program's day template (number of configured days = trip length), NOT entered manually.
 */
class ConvertInquiryToTrip
{
    public function handle(Inquiry $inquiry, array $data): Trip
    {
        return DB::transaction(function () use ($inquiry, $data) {
            $from = Carbon::parse($data['date_from']);

            $programId = $data['trip_program_id'] ?? $inquiry->program_id;
            $program = $programId ? TripProgram::find($programId) : null;

            // Trip length = the program's explicit number of days (min 1).
            $dayCount = max(1, $program?->number_of_days ?? 1);
            $to = $from->copy()->addDays($dayCount - 1);

            $trip = Trip::create([
                'trip_program_id' => $programId,
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

            // Boarding-point template from the program (order → location_id); extra days stay empty.
            $dayLocations = $program
                ? $program->programDays()->pluck('location_id', 'order')
                : collect();

            // Day 1 gets the confirmed start time; sraz = start − offset, konec = start + duration.
            $startTime = $data['start_time'] ?? null;
            $meetingTime = null;
            $endTime = null;
            if ($startTime) {
                $start = Carbon::parse($startTime);
                $offset = $program?->meeting_offset_minutes ?? 50;
                $meetingTime = $start->copy()->subMinutes($offset)->format('H:i');
                if ($program?->duration_minutes) {
                    $endTime = $start->copy()->addMinutes($program->duration_minutes)->format('H:i');
                }
            }

            // Generate the trip days.
            for ($order = 1; $order <= $dayCount; $order++) {
                TripDay::create([
                    'trip_id' => $trip->id,
                    'date' => $from->copy()->addDays($order - 1)->toDateString(),
                    'order' => $order,
                    'location_id' => $dayLocations[$order] ?? null,
                    'start_time' => $order === 1 ? $startTime : null,
                    'meeting_time' => $order === 1 ? $meetingTime : null,
                    'end_time' => $order === 1 ? $endTime : null,
                ]);
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
