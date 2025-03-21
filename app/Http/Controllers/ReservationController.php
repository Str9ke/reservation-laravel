<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Carbon\Carbon;

class ReservationController extends Controller
{
    private $prioritySlots = [
        'Monday'    => ['08:00', '11:00', '16:00', '18:00'],
        'Wednesday' => ['08:00', '11:00', '16:00', '18:00'],
        'Thursday'  => ['08:00', '11:00', '16:00', '18:00'],
        'Tuesday'   => ['09:00'],
        'Friday'    => ['09:00']
    ];

    private $openingTime = "08:00";
    private $closingTime = "19:00";
    private $maxFutureWeeks = 3;

    public function index()
    {
        $reservations = Reservation::all();
        return view('reservations.index', compact('reservations'));
    }

    public function create()
    {
        $date = now(); // Tu peux rendre ça dynamique en récupérant une date depuis la requête
        $dayOfWeek = $date->format('l');
        $availableTimeSlots = $this->generateAvailableTimeSlots($date, $dayOfWeek);

        return view('reservations.create', compact('availableTimeSlots'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required',
            'email'  => 'required|email',
            'phone'  => 'required',
            'date'   => 'required|date|after_or_equal:today',
            'time'   => 'required',
            'reason' => 'required'
        ]);

        $requestedDate = Carbon::parse($request->date);
        $requestedTime = Carbon::parse($request->time);

        // Vérification de la date max 3 semaines
        if ($requestedDate->greaterThan(Carbon::now()->addWeeks($this->maxFutureWeeks))) {
            return back()->withErrors(['error' => 'Vous ne pouvez pas réserver plus de 3 semaines à l’avance.']);
        }

        // Vérification des horaires d'ouverture
        if ($requestedTime->format('H:i') < $this->openingTime || $requestedTime->format('H:i') > $this->closingTime) {
            return back()->withErrors(['error' => 'Les rendez-vous sont possibles uniquement entre 08:00 et 19:00.']);
        }

        // Vérification du délai minimum
        if (Carbon::now()->diffInMinutes(Carbon::parse($request->date . ' ' . $request->time)) < 90) {
            return back()->withErrors(['error' => 'Impossible de réserver moins de 1h30 à l’avance.']);
        }

        // Vérifie si l'heure choisie fait bien partie des créneaux disponibles
        $dayOfWeek = $requestedDate->format('l');
        $availableTimeSlots = $this->generateAvailableTimeSlots($requestedDate, $dayOfWeek);

        if (!in_array($requestedTime->format('H:i'), $availableTimeSlots)) {
            return back()->withErrors(['error' => 'Ce créneau n’est pas disponible.']);
        }

        // Enregistre la réservation
        Reservation::create($request->all());

        return redirect()->route('reservations.index')->with('success', 'Rendez-vous pris avec succès!');
    }

    private function generateAvailableTimeSlots($date, $dayOfWeek)
    {
        $existingReservations = Reservation::whereDate('date', $date->toDateString())
            ->pluck('time')
            ->map(function($time) { return Carbon::parse($time)->format('H:i'); })
            ->toArray();

        $prioritySlots = $this->prioritySlots[$dayOfWeek] ?? [];
        $possibleSlots = [];

        foreach ($prioritySlots as $slot) {
            $baseSlot = Carbon::parse($date->toDateString() . ' ' . $slot);
            $possibleSlots[] = $baseSlot->format('H:i');

            for ($i = 1; $i <= 10; $i++) {
                $before = $baseSlot->copy()->subMinutes(30 * $i);
                $after  = $baseSlot->copy()->addMinutes(30 * $i);

                if ($before->format('H:i') >= $this->openingTime) {
                    $possibleSlots[] = $before->format('H:i');
                }
                if ($after->format('H:i') <= $this->closingTime) {
                    $possibleSlots[] = $after->format('H:i');
                }
            }
        }

        // Enlève les créneaux déjà pris
        return array_values(array_diff(array_unique($possibleSlots), $existingReservations));
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        return redirect()->route('reservations.index')->with('success', 'Rendez-vous annulé!');
    }
}
