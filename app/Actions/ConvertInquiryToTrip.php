<?php

namespace App\Actions;

use App\Models\Inquiry;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripNote;
use App\Models\TripProgram;
use App\Notifications\CustomerInquiryConfirmedNotification;
use App\Support\TripDayDrafts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

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
        $trip = DB::transaction(function () use ($inquiry, $data) {
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

            // Days with the program's boarding/exit points and default times (day 1 from the
            // confirmed start, later days 9–15) – the same drafts the create wizard uses.
            foreach (TripDayDrafts::build($program, $from->toDateString(), $to->toDateString(), $data['start_time'] ?? null) as $draft) {
                TripDay::create(['trip_id' => $trip->id, ...$draft]);
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

            return $trip;
        });

        // Confirmation e-mail goes out only AFTER the transaction has committed, and a delivery
        // failure must never undo the conversion - QUEUE_CONNECTION=sync sends it in-request, so
        // an unreachable SMTP server would otherwise roll the whole trip back and return a 500.
        if ($inquiry->client_email) {
            try {
                Notification::route('mail', $inquiry->client_email)
                    ->notify(new CustomerInquiryConfirmedNotification($trip));
            } catch (Throwable $e) {
                Log::error('Confirmation e-mail for inquiry could not be sent.', [
                    'inquiry_id' => $inquiry->id,
                    'trip_id' => $trip->id,
                    'recipient' => $inquiry->client_email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $trip;
    }
}
