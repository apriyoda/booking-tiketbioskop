<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
class UserBookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Menampilkan daftar riwayat pesanan tiket untuk pengguna yang sedang login.
     */
    public function index()
    {
        $user = Auth::user();

        $bookings = $user->bookings()
                         ->with(['schedule.film', 'schedule.studio', 'bookedSeats'])
                         ->orderBy('created_at', 'desc')
                         ->paginate(10);

        return view('user.bookings.index', compact('bookings'));
    }

    /**
     * Menampilkan detail pesanan tiket tertentu.
     */
    public function show(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $booking->load(['schedule.film', 'schedule.studio', 'bookedSeats']);

        return view('user.bookings.show', compact('booking'));
    }
}
